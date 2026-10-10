<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
use App\Models\FireHydrant;
use App\Models\FireIncident;
use App\Models\Permission;
use App\Models\PredictionExecution;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\SmsRecipient;
use App\Models\User;
use App\Services\FloodIncidentRecordImporter;
use App\Services\LiveWeatherService;
use App\Services\WeatherObservationRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OperationalRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_exactly_seven_sections_use_the_requested_record_sources(): void
    {
        $user = $this->staff();
        $response = $this->actingAs($user)->get(route('operational-records.index'))->assertOk()
            ->assertSee('Operational Records')->assertSee('Flood Incident Records')
            ->assertDontSee('Flood Analytics Dataset')->assertDontSee('Prediction Runs')->assertDontSee('Barangay Profiles');
        $data = $response->viewData('datasets');
        $this->assertSame(['flood-records', 'fire-incidents', 'fire-hydrants', 'prediction-results', 'sms-logs', 'public-recipients', 'weather-observations'], array_keys($data));
        $this->assertSame(6874, $response->viewData('datasetCounts')['flood-records']);
        $this->assertSame(6874, $response->viewData('records')->total());
        foreach (array_keys($data) as $key) {
            $this->actingAs($user)->get(route('operational-records.index', ['dataset' => $key]))->assertOk();
        }
        foreach (['prediction-runs', 'flood-predictions', 'barangays', 'sms-recipients'] as $removed) {
            $this->actingAs($user)->get(route('operational-records.index', ['dataset' => $removed]))->assertNotFound();
            $this->actingAs($user)->get(route('operational-records.export', ['dataset' => $removed]))->assertNotFound();
        }
        Http::assertNothingSent();
    }

    public function test_all_spreadsheet_rows_are_preserved_and_import_is_idempotent(): void
    {
        $path = database_path('data/flood-incident-records.csv.gz');
        $this->assertSame(6874, app(FloodIncidentRecordImporter::class)->import($path));
        $this->assertSame(6874, DB::table('flood_incident_records')->count());
        $this->assertSame(4239, DB::table('flood_incident_records')->distinct()->count('event_id'));
        $this->assertSame(['A' => 3482, 'B' => 2043, 'C' => 1208, 'D' => 141], DB::table('flood_incident_records')->selectRaw('flood_code, COUNT(*) as total')->groupBy('flood_code')->orderBy('flood_code')->pluck('total', 'flood_code')->all());
        $first = DB::table('flood_incident_records')->where('source_row', 1)->first();
        $this->assertSame('2016-05-16 15:10:00', $first->flood_start_datetime);
        $this->assertSame('2016-05-17 20:02:00', $first->flood_subsided_datetime);
        $this->assertEquals(28.87, $first->duration_hours);
        // Preserve staff edits and soft deletions when the same source is imported again.
        DB::table('flood_incident_records')->where('id', $first->id)->update(['flood_code' => 'B', 'deleted_at' => now()]);
        app(FloodIncidentRecordImporter::class)->import($path);
        $this->assertDatabaseHas('flood_incident_records', ['id' => $first->id, 'flood_code' => 'B']);
        $this->assertSame(6874, DB::table('flood_incident_records')->count());
    }

    public function test_malformed_import_does_not_partially_write_records(): void
    {
        $source = gzdecode(file_get_contents(database_path('data/flood-incident-records.csv.gz')));
        $lines = explode("\n", str_replace("\r\n", "\n", $source));
        $path = tempnam(sys_get_temp_dir(), 'maps-invalid-flood-');
        file_put_contents($path, $lines[0]."\n".$lines[1]."\nmalformed,row\n");
        try {
            app(FloodIncidentRecordImporter::class)->import($path);
            $this->fail('Invalid row must fail the import.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Invalid flood CSV row', $error->getMessage());
            $this->assertSame(6874, DB::table('flood_incident_records')->count());
        } finally {
            unlink($path);
        }
    }

    public function test_flood_search_barangay_aliases_code_dates_pagination_and_export_match(): void
    {
        $barangay = Barangay::create(['name' => 'Old Zaniga', 'district' => 2, 'is_active' => true]);
        $user = $this->staff();
        $filters = ['dataset' => 'flood-records', 'barangay_id' => $barangay->id, 'status' => 'Subsided', 'flood_code' => 'B', 'date_from' => '2025-01-01', 'date_to' => '2025-12-31', 'search' => 'Maysilo'];
        $expected = DB::table('flood_incident_records')->where('barangay', 'Old Zañiga')->where('status', 'Subsided')->where('flood_code', 'B')->where('year', 2025)->where('nearest_waterway', 'like', '%Maysilo%')->count();
        $this->assertGreaterThan(0, $expected);
        $response = $this->actingAs($user)->get(route('operational-records.index', $filters))->assertOk();
        $this->assertSame($expected, $response->viewData('records')->total());
        $this->assertLessThanOrEqual(25, $response->viewData('records')->count());
        $export = $this->actingAs($user)->get(route('operational-records.export', $filters))->assertOk()->streamedContent();
        $rows = $this->csvRows($export);
        $this->assertCount($expected + 1, $rows);
        $this->assertContains('Flood Code', $rows[0]);
        $this->assertContains('Flood Subsided (PHT)', $rows[0]);
        $this->assertNotContains('Risk Level', $rows[0]);
        foreach (array_slice($rows, 1) as $row) {
            $this->assertSame('Old Zañiga', $row[7]);
            $this->assertSame('B', $row[8]);
        }
    }

    public function test_flood_crud_uses_incident_fields_and_soft_deletes(): void
    {
        $user = $this->staff(['flood.create', 'flood.edit', 'flood.delete']);
        $payload = ['event_id' => 'MANUAL-NEW', 'observation_datetime' => '2026-10-10T14:00:00', 'flood_start_datetime' => '2026-10-10T14:00:00', 'status' => 'Active', 'barangay' => 'New Zañiga', 'flood_code' => 'B', 'rainfall_24h_mm' => 21];
        $this->actingAs($user)->get(route('operational-records.flood.create'))->assertOk()->assertDontSee('Risk Level');
        $this->actingAs($user)->post(route('operational-records.flood.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $id = DB::table('flood_incident_records')->where('event_id', 'MANUAL-NEW')->value('id');
        $this->assertDatabaseHas('flood_incident_records', ['id' => $id, 'year' => 2026, 'month' => 10, 'status' => 'Active', 'flood_code' => 'B']);
        $this->actingAs($user)->get(route('operational-records.flood.edit', $id))->assertOk()->assertSee('New Zañiga');
        $payload['status'] = 'Subsided';
        $this->actingAs($user)->put(route('operational-records.flood.update', $id), $payload)->assertSessionHasErrors('flood_subsided_datetime');
        $payload['flood_subsided_datetime'] = '2026-10-10T15:00:00';
        $this->actingAs($user)->put(route('operational-records.flood.update', $id), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($user)->delete(route('operational-records.flood.destroy', $id))->assertRedirect();
        $this->assertSoftDeleted('flood_incident_records', ['id' => $id]);
        $this->actingAs($user)->get(route('operational-records.flood.edit', $id))->assertNotFound();
        $response = $this->actingAs($user)->get(route('operational-records.index', ['search' => 'MANUAL-NEW']))->assertOk();
        $this->assertSame(0, $response->viewData('records')->total());
    }

    public function test_prediction_results_are_the_saved_history_runs_and_include_remarks(): void
    {
        $user = $this->staff(['prediction.view']);
        $run = PredictionExecution::create(['requested_by_user_id' => $user->id, 'requested_by_name' => 'Forecast Operator', 'kind' => 'Forecast', 'forecast_hours' => 48, 'status' => 'Completed', 'requested_at' => '2026-10-09 16:30:00', 'completed_at' => '2026-10-09 16:31:00', 'result_snapshot' => ['predictions' => [['barangay' => 'Hulo', 'predicted_flood_code' => 'C']]]]);
        $run->remarks()->create(['author_name' => 'Reviewer', 'body' => 'Review completed.']);
        PredictionExecution::create(['requested_by_name' => 'Other Operator', 'kind' => 'Simulation', 'forecast_hours' => 24, 'status' => 'Failed', 'requested_at' => '2026-10-09 15:59:00']);
        $filters = ['dataset' => 'prediction-results', 'forecast_hours' => 48, 'status' => 'Completed', 'date_from' => '2026-10-10', 'date_to' => '2026-10-10', 'search' => 'Forecast Operator'];
        $response = $this->actingAs($user)->get(route('operational-records.index', $filters))->assertOk()->assertSee('1 saved results')->assertSee(route('prediction.history.show', $run), false);
        $this->assertSame(1, $response->viewData('records')->total());
        $this->assertSame(2, $response->viewData('datasetCounts')['prediction-results']);
        $this->assertSame(1, (int) $response->viewData('records')->first()->remarks_count);
        $this->assertSame('2026-10-10 00:30:00', $response->viewData('records')->first()->requested_at);
        $export = $this->actingAs($user)->get(route('operational-records.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('predicted_flood_code', $export);
        $this->assertStringContainsString('Hulo', $export);
        $this->actingAs($user)->get(route('prediction.history.show', $run))->assertOk()->assertSee('Review completed.');
    }

    public function test_public_recipient_count_search_and_export_exclude_internal_officers(): void
    {
        $user = $this->staff();
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 2, 'is_active' => true]);
        $resident = User::factory()->create(['role_id' => Role::where('slug', 'public-resident')->value('id')]);
        SmsRecipient::create(['user_id' => $resident->id, 'full_name' => 'Public Contact', 'phone_number' => '+639170000001', 'barangay_id' => $barangay->id, 'is_active' => true]);
        SmsRecipient::create(['user_id' => User::factory()->create()->id, 'full_name' => 'Inactive Public', 'phone_number' => '+639170000002', 'is_active' => false]);
        SmsRecipient::create(['full_name' => 'Internal Officer', 'phone_number' => '+639170000003']);
        $response = $this->actingAs($user)->get(route('operational-records.index', ['dataset' => 'public-recipients']))->assertOk()->assertDontSee('Internal Officer');
        $this->assertSame(2, $response->viewData('records')->total());
        $this->assertSame(2, $response->viewData('datasetCounts')['public-recipients']);
        $filters = ['dataset' => 'public-recipients', 'status' => '1', 'barangay_id' => $barangay->id, 'search' => 'Public Contact'];
        $filtered = $this->actingAs($user)->get(route('operational-records.index', $filters))->assertOk();
        $this->assertSame(1, $filtered->viewData('records')->total());
        $export = $this->actingAs($user)->get(route('operational-records.export', ['dataset' => 'public-recipients']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Public Contact', $export);
        $this->assertStringNotContainsString('Internal Officer', $export);
    }

    public function test_weather_snapshots_populate_observations_once_with_correct_units_and_dates(): void
    {
        $weather = ['observed_at' => '2026-10-10T00:15:00+08:00', 'location' => 'Mandaluyong City', 'current_temperature_c' => 28.2, 'avg_rh_pct' => 82, 'avg_wind_speed' => 2, 'avg_wind_direction_deg' => 270, 'rainfall_24h_mm' => 23, 'rainfall_3d_mm' => 50, 'rainfall_7d_mm' => 91, 'weather_description' => 'Rain showers'];
        $snapshot = DailyWeatherSnapshot::create(['snapshot_date' => '2026-10-10', 'source' => 'Open-Meteo', 'weather_data' => $weather, 'fetched_at' => now(), 'expires_at' => now()->addDay()]);
        app(WeatherObservationRecorder::class)->record($snapshot);
        $snapshot->touch();
        $this->assertDatabaseCount('weather_observations', 1);
        $this->assertDatabaseHas('weather_observations', ['daily_weather_snapshot_id' => $snapshot->id, 'observed_at' => '2026-10-09 16:15:00', 'wind_speed_kph' => 7.2, 'rainfall_24h_mm' => 23]);
        DailyWeatherSnapshot::create(['snapshot_date' => '2026-10-11', 'source' => 'Open-Meteo-Attempt-Failed', 'weather_data' => [], 'fetched_at' => now(), 'expires_at' => now()->addDay()]);
        $this->assertDatabaseCount('weather_observations', 1);
        $user = $this->staff();
        $filters = ['dataset' => 'weather-observations', 'search' => 'Rain showers', 'date_from' => '2026-10-10', 'date_to' => '2026-10-10'];
        $response = $this->actingAs($user)->get(route('operational-records.index', $filters))->assertOk()->assertSee('Mandaluyong City');
        $this->assertSame(1, $response->viewData('records')->total());
        $this->assertSame('2026-10-10 00:15:00', $response->viewData('records')->first()->observed_at);
        $export = $this->actingAs($user)->get(route('operational-records.export', $filters))->assertOk()->streamedContent();
        $this->assertStringContainsString('7.2', $export);
        Http::assertNothingSent();
    }

    public function test_real_daily_weather_fetch_records_one_observation_without_extra_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 08:00:00', 'Asia/Manila'));
        try {
            $days = [];
            for ($day = 0; $day < 7; $day++) {
                $days[] = now('Asia/Manila')->addDays($day)->toDateString();
            }
            Http::fake(['https://api.open-meteo.com/v1/forecast*' => Http::response([
                'current' => ['time' => '2026-10-10T08:00', 'temperature_2m' => 29.5, 'relative_humidity_2m' => 82, 'wind_speed_10m' => 4.5, 'wind_direction_10m' => 225, 'weather_code' => 61],
                'hourly' => ['time' => ['2026-10-10T07:00', '2026-10-10T09:00'], 'temperature_2m' => [28, 30], 'precipitation' => [3, 4], 'wind_speed_10m' => [4, 5], 'relative_humidity_2m' => [82, 81]],
                'daily' => ['time' => $days, 'temperature_2m_max' => array_fill(0, 7, 31), 'temperature_2m_min' => array_fill(0, 7, 25), 'precipitation_sum' => array_fill(0, 7, 7), 'rain_sum' => array_fill(0, 7, 7)],
            ])]);
            app(LiveWeatherService::class)->getCurrentWeather();
            app(LiveWeatherService::class)->getCurrentWeather();
            Http::assertSentCount(1);
            $this->assertDatabaseCount('weather_observations', 1);
            $this->assertDatabaseHas('weather_observations', ['temperature_c' => 29.5, 'wind_speed_kph' => 16.2, 'relative_humidity_pct' => 82]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_existing_daily_snapshots_are_backfilled_without_duplicate_observations(): void
    {
        $snapshot = DailyWeatherSnapshot::create(['snapshot_date' => '2026-10-08', 'source' => 'Open-Meteo', 'weather_data' => ['observed_at' => '2026-10-08T08:00:00+08:00', 'current_temperature_c' => 30], 'fetched_at' => now(), 'expires_at' => now()->addDay()]);
        DB::table('weather_observations')->delete();
        $migration = require database_path('migrations/2026_10_10_090100_connect_weather_observations_to_daily_snapshots.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('weather_observations', 1);
        $this->assertDatabaseHas('weather_observations', ['daily_weather_snapshot_id' => $snapshot->id, 'temperature_c' => 30]);
        Http::assertNothingSent();
    }

    public function test_fire_hydrant_and_sms_sections_filter_export_and_exclude_deleted_records(): void
    {
        $user = $this->staff();
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 2, 'is_active' => true]);
        $incident = FireIncident::create(['barangay_id' => $barangay->id, 'incident_number' => 'OPS-FIRE-1', 'incident_type' => 'Structural Fire', 'location' => 'Test Street', 'severity' => 'Minor', 'status' => 'Reported', 'reported_at' => '2026-10-09 17:00:00']);
        $deleted = $incident->replicate();
        $deleted->incident_number = 'OPS-FIRE-DELETED';
        $deleted->save();
        $deleted->delete();
        $hydrant = FireHydrant::create(['barangay_id' => $barangay->id, 'hydrant_code' => 'OPS-HYDRANT-1', 'location' => 'Test Street', 'status' => 'Maintenance']);
        $deletedHydrant = $hydrant->replicate();
        $deletedHydrant->hydrant_code = 'OPS-HYDRANT-DELETED';
        $deletedHydrant->save();
        $deletedHydrant->delete();
        SmsLog::create(['recipient_name' => 'Test Recipient', 'phone_number' => '+639170000004', 'message' => '=Unsafe spreadsheet formula', 'source' => 'manual', 'status' => 'failed', 'failure_reason' => 'Offline', 'created_at' => now()]);
        foreach ([['fire-incidents', 'Reported', 'OPS-FIRE-1'], ['fire-hydrants', 'Maintenance', 'OPS-HYDRANT-1'], ['sms-logs', 'failed', 'Test Recipient']] as [$dataset, $status, $search]) {
            $filters = ['dataset' => $dataset, 'status' => $status, 'search' => $search];
            $response = $this->actingAs($user)->get(route('operational-records.index', $filters))->assertOk();
            $this->assertSame(1, $response->viewData('records')->total());
            $this->assertSame(1, $response->viewData('datasetCounts')[$dataset]);
            $export = $this->actingAs($user)->get(route('operational-records.export', $filters))->assertOk()->streamedContent();
            $this->assertStringContainsString($search, $export);
            $this->assertStringNotContainsString('DELETED', $export);
            if ($dataset === 'sms-logs') {
                $this->assertStringContainsString("'=Unsafe spreadsheet formula", $export);
            }
        }
        Http::assertNothingSent();
    }

    public function test_flood_report_uses_new_incident_codes_and_dates(): void
    {
        $user = $this->staff();
        $response = $this->actingAs($user)->get(route('operational-records.report-builder', ['barangay' => 'Old Zañiga', 'flood_code' => 'D', 'date_from' => '2025-01-01', 'date_to' => '2025-12-31']))->assertOk();
        $this->assertArrayHasKey('flood_code', $response->viewData('availableDimensions'));
        $this->assertArrayNotHasKey('risk_level', $response->viewData('availableDimensions'));
        $this->assertArrayNotHasKey('flood_depth_mm', $response->viewData('availableMeasures'));
        $expected = DB::table('flood_incident_records')->where('barangay', 'Old Zañiga')->where('year', 2025)->where('flood_code', 'D')->count();
        $this->assertGreaterThan(0, $expected);
        $this->assertSame((float) $expected, array_sum(array_column(array_map(fn ($row) => $row['values'], $response->viewData('pivotRows')), 'D')));
        $this->actingAs($user)->get(route('operational-records.report-builder.export', ['barangay' => 'Old Zañiga', 'flood_code' => 'D']))->assertOk();
    }

    public function test_record_and_export_permissions_are_enforced(): void
    {
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($user)->get(route('operational-records.index'))->assertForbidden();
        $this->actingAs($user)->get(route('operational-records.export'))->assertForbidden();
        $viewer = $this->staff([], false);
        $this->actingAs($viewer)->get(route('operational-records.index'))->assertOk();
        $this->actingAs($viewer)->get(route('operational-records.export'))->assertForbidden();
        $this->actingAs($viewer)->post(route('operational-records.flood.store'), [])->assertForbidden();
    }

    private function staff(array $extra = [], bool $export = true): User
    {
        $role = Role::create(['name' => 'Operations Officer', 'slug' => 'operations-officer-'.uniqid(), 'is_active' => true]);
        foreach (array_merge(['records.view'], $export ? ['records.export'] : [], $extra) as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'records', 'is_active' => true]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function csvRows(string $content): array
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        } fclose($handle);

        return $rows;
    }
}
