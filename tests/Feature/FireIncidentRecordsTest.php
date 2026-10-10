<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FireIncidentAlertService;
use App\Services\FireIncidentRecordImporter;
use App\Services\NearestFireHydrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FireIncidentRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    public function test_workbook_import_preserves_all_rows_without_fabricating_confirmed_fields(): void
    {
        $result = app(FireIncidentRecordImporter::class)->import();
        $this->assertSame(['imported' => 117, 'preserved' => 0, 'dataset_rows' => 117, 'previous_records' => 0], $result);
        $this->assertDatabaseCount('fire_incidents', 117);
        $this->assertSame(117, FireIncident::count());
        $this->assertSame(80, FireIncident::withoutGlobalScope('operational_records')->where('source_origin', 'Modeled')->count());
        $first = FireIncident::where('incident_number', 'FIR-0001')->sole();
        $this->assertSame('2026-03-15 03:03:00', $first->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(46, $first->duration_minutes);
        $this->assertSame(35, $first->individuals_affected);
        $this->assertNull($first->severity);
        $this->assertNull($first->responded_at);
        $this->assertNull($first->alarm_level);
        $this->assertNull($first->cause);
        $this->assertSame('Electrical fault', $first->cause_reference);
        $this->assertSame('1st', $first->alarm_reference);
        $this->assertSame('Approximate', $first->coordinate_accuracy);
        $this->assertTrue($first->source_record['reported_at_is_fallback']);
        $this->assertSame('Plainview', $first->barangay->name);
        $ambiguous = FireIncident::where('incident_number', 'FIR-0014')->sole();
        $this->assertNull($ambiguous->barangay_id);
        $this->assertSame('Zañiga (unspecified)', $ambiguous->source_barangay);
        $this->assertNull($ambiguous->latitude);
        $overnight = FireIncident::where('incident_number', 'FIR-0036')->sole();
        $this->assertSame('2020-06-02 02:10', $overnight->fire_out_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $this->assertSame(516, $overnight->duration_minutes);
        $this->assertSame('2nd', FireIncident::where('incident_number', 'FIR-0005')->sole()->alarm_level);
        $this->assertSame('Mabini-J. Rizal', FireIncident::where('source_barangay', 'Mabini–J. Rizal')->firstOrFail()->barangay->name);
        $this->assertSame('Hagdang Bato Itaas', FireIncident::withoutGlobalScope('operational_records')->where('source_barangay', 'Hagdan Bato Itaas')->firstOrFail()->barangay->name);
        $this->assertDatabaseCount('sms_logs', 0);
        Http::assertNothingSent();
    }

    public function test_repeat_import_preserves_existing_records_edits_and_soft_deletions(): void
    {
        $manual = $this->incident();
        $old = $this->incident(['incident_number' => 'OLD-1', 'data_source' => 'Historical FireData.xlsx', 'status' => 'Resolved']);
        $importer = app(FireIncidentRecordImporter::class);
        $importer->import();
        $record = FireIncident::where('incident_number', 'FIR-0001')->sole();
        $record->update(['remarks' => 'Staff review preserved']);
        $record->delete();
        $this->artisan('fire:import')->assertSuccessful();
        $this->assertDatabaseCount('fire_incidents', 119);
        $this->assertDatabaseHas('fire_incidents', ['id' => $record->id, 'remarks' => 'Staff review preserved']);
        $this->assertSoftDeleted($record);
        $this->assertDatabaseHas('fire_incidents', ['id' => $manual->id, 'record_classification' => 'Superseded']);
        $this->assertDatabaseHas('fire_incidents', ['id' => $old->id, 'record_classification' => 'Superseded']);
        $this->assertSame(116, FireIncident::count()); // Only current dataset rows; prior manual incidents are archived.
        $new = $this->incident(['incident_number' => 'NEW-AFTER-REPLACEMENT']);
        $this->artisan('fire:import')->assertSuccessful();
        $this->assertSame(117, FireIncident::count());
        $this->assertSame('Reported', $new->fresh()->record_classification);
        $this->assertDatabaseCount('fire_dataset_activations', 1);
    }

    public function test_upgrade_promotes_previous_samples_and_replaces_all_earlier_fire_records_once(): void
    {
        $importer = app(FireIncidentRecordImporter::class);
        $importer->import();
        // Simulate the prior release: 37 source rows and 80 separately classified samples.
        DB::table('fire_dataset_activations')->delete();
        DB::table('fire_incidents')->where('source_origin', 'Modeled')->update(['record_classification' => 'Example',
            'remarks' => 'Modeled example. Excluded from operational totals and alerts.']);
        DB::table('fire_incidents')->where('source_origin', 'Transcribed')->update(['record_classification' => 'Reported']);
        for ($i = 1; $i <= 7; $i++) $this->incident(['incident_number' => 'PREVIOUS-'.$i]);
        $result = $importer->import();
        $this->assertSame(0, $result['imported']);
        $this->assertSame(117, $result['preserved']);
        $this->assertSame(117, FireIncident::count());
        $this->assertSame(117, FireIncident::where('record_classification', 'Dataset')->count());
        $this->assertSame(80, FireIncident::where('source_origin', 'Modeled')->count());
        $this->assertDatabaseCount('fire_incidents', 124);
        $this->assertSame(7, FireIncident::withoutGlobalScope('operational_records')->where('record_classification', 'Superseded')->count());
        $this->assertSame(0, FireIncident::active()->count());
        $sample = FireIncident::where('incident_number', 'FIR-EX-0001')->sole();
        $this->assertSame('Modeled', $sample->source_record['record_origin']);
        $this->assertStringNotContainsString('Excluded', $sample->remarks);
        $this->actingAs($this->staff())->get(route('operational-records.index', ['dataset' => 'fire-incidents', 'record_classification' => 'Superseded']))
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 7);
        $new = $this->incident(['incident_number' => 'NEW-REPORT']);
        $importer->import();
        $this->assertSame(118, FireIncident::count());
        $this->assertSame('Reported', $new->fresh()->record_classification);
        Http::assertNothingSent();
    }

    public function test_incomplete_replacement_does_not_archive_existing_incidents(): void
    {
        $existing = $this->incident();
        $source = json_decode(file_get_contents(database_path('data/fire-incident-records.json')), true);
        array_pop($source['records']);
        $path = tempnam(sys_get_temp_dir(), 'maps-fire-incomplete-');
        file_put_contents($path, json_encode($source));
        try {
            $this->artisan('fire:import', ['file' => $path])->assertFailed();
            $this->assertDatabaseCount('fire_incidents', 1);
            $this->assertDatabaseCount('fire_dataset_activations', 0);
            $this->assertSame('Reported', $existing->fresh()->record_classification);
        } finally {
            unlink($path);
        }
    }

    public function test_archived_history_keeps_audit_links_and_reserves_existing_incident_numbers(): void
    {
        $year = now()->format('Y');
        $old = $this->incident(['incident_number' => "FI-{$year}-0010", 'data_source' => 'Historical FireData.xlsx', 'status' => 'Resolved']);
        app(FireIncidentRecordImporter::class)->import();
        $log = new \App\Models\SmsLog(['fire_incident_id' => $old->id]);
        $this->assertSame($old->id, $log->fireIncident->id);
        $report = new \App\Models\PublicIncidentReport;
        $report->fire_incident_id = $old->id;
        $this->assertSame($old->id, $report->fireIncident->id);
        $this->actingAs($this->staff())->post(route('fire-incidents.store'), $this->input())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fire_incidents', ['incident_number' => "FI-{$year}-0011"]);
    }

    public function test_invalid_source_is_atomic(): void
    {
        $source = json_decode(file_get_contents(database_path('data/fire-incident-records.json')), true);
        $source['records'][5]['Individuals Affected'] = -1;
        $path = tempnam(sys_get_temp_dir(), 'maps-fire-invalid-');
        file_put_contents($path, json_encode($source));
        try {
            app(FireIncidentRecordImporter::class)->import($path);
            $this->fail('Malformed data must not be imported.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Invalid fire impact', $error->getMessage());
            $this->assertDatabaseCount('fire_incidents', 0);
            $this->assertDatabaseCount('barangays', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_current_analytics_include_all_uploaded_rows_and_keep_unresolved_barangays(): void
    {
        app(FireIncidentRecordImporter::class)->import();
        $analytics = app(FireAnalyticsService::class)->getDashboardData();
        $this->assertSame(117, $analytics['kpis']['total_incidents']);
        $this->assertSame(40221, $analytics['kpis']['individuals_affected']);
        $this->assertSame(4873, $analytics['kpis']['houses_destroyed']);
        $this->assertSame(100.0, $analytics['kpis']['resolution_rate']);
        $this->assertSame(117, array_sum($analytics['monthly_trend']['incidents']));
        $this->assertSame(117, array_sum($analytics['time_distribution']['values']));
        $this->assertSame([0, 0, 0, 117], $analytics['severity_distribution']['values']);
        $alarms = array_combine($analytics['alarm_distribution']['labels'], $analytics['alarm_distribution']['values']);
        $this->assertSame(115, $alarms['Unspecified']);
        $this->assertSame(1, $alarms['2nd']);
        $this->assertSame(1, $alarms['3rd']);
        $this->assertSame(117, array_sum(app(FireAnalyticsService::class)->getTopBarangays(null, null, 100)['incidents']));
    }

    public function test_analytics_use_manila_calendar_dates_and_hours(): void
    {
        $record = $this->incident(['occurred_at' => '2025-12-31 16:30:00']);
        $service = app(FireAnalyticsService::class);
        $this->assertSame(2026, $record->incident_year);
        $this->assertSame(1, $record->incident_month);
        $this->assertSame(0, $record->incident_hour);
        $this->assertSame(1, $service->getDashboardStats(2026)['total_incidents']);
        $this->assertSame(0, $service->getDashboardStats(2025)['total_incidents']);
        $this->assertSame(1, $service->getMonthlyTrend(2026)['incidents'][0]);
        $this->assertSame([0, 0, 0, 1], $service->getTimeOfDayDistribution(2026)['values']);
        $this->assertSame([2026], $service->getAvailableYears());
    }

    public function test_records_lists_exports_and_details_use_the_complete_current_dataset(): void
    {
        app(FireIncidentRecordImporter::class)->import();
        $this->actingAs($this->staff());
        $response = $this->get(route('operational-records.index', ['dataset' => 'fire-incidents']))->assertOk();
        $this->assertSame(117, $response->viewData('records')->total());
        $this->assertSame(117, $response->viewData('datasetCounts')['fire-incidents']);
        $this->get(route('operational-records.index', ['dataset' => 'fire-incidents', 'record_classification' => 'Current']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 117);
        $csv = $this->get(route('operational-records.export', ['dataset' => 'fire-incidents']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Time Occurred (PHT)"', $csv);
        $this->assertStringContainsString('"2026-03-15 11:03:00"', $csv);
        $this->assertStringContainsString('"Alarm (unconfirmed reference)"', $csv);
        $this->assertStringContainsString('FIR-EX-', $csv);
        $this->assertStringContainsString('Modeled', $csv);
        $this->assertStringContainsString('FIR-0014', $csv);
        $this->get(route('fire-incidents.index'))->assertOk()->assertViewHas('incidents', fn ($rows) => $rows->total() === 117);
        $this->get(route('fire-incidents.index', ['record_classification' => 'Current']))->assertOk()->assertViewHas('incidents', fn ($rows) => $rows->total() === 117);
        $example = FireIncident::withoutGlobalScope('operational_records')->where('incident_number', 'FIR-EX-0001')->sole();
        $this->get(route('fire-incidents.show', $example))->assertOk()->assertSee('Source origin: Modeled')->assertSee('Original source row');
        $this->get(route('fire-incidents.edit', $example))->assertForbidden();
        $this->get(route('fire-incidents.create'))->assertOk()->assertSee('name="occurred_at"', false)->assertSee('name="houses_destroyed"', false)->assertSee('name="alarm_level"', false);
    }

    public function test_imported_records_are_history_only_and_never_generate_alerts_or_routing(): void
    {
        app(FireIncidentRecordImporter::class)->import();
        $this->actingAs($this->staff());
        $this->getJson(route('gis.data'))->assertOk()->assertJsonCount(0, 'incidents');
        $this->get(route('gis.index'))->assertOk()->assertSee('Incident history');
        $this->get(route('public.flood-map'))->assertOk()->assertViewHas('fires', fn ($fires) => $fires->isEmpty());
        $this->getJson(route('gis.data', ['fire_layer' => 'history']))->assertOk()->assertJsonCount(116, 'incidents')
            ->assertJsonPath('incidents.0.coordinate_accuracy', 'Approximate');
        foreach (FireIncident::withoutGlobalScope('operational_records')->get() as $record) {
            $this->assertSame(0, app(FireIncidentAlertService::class)->sendCreatedAlert($record, null)['sent']);
            $this->assertNull(app(NearestFireHydrantService::class)->findForIncident($record));
        }
        $this->assertDatabaseCount('sms_logs', 0);
        Http::assertNothingSent();
    }

    public function test_new_reports_capture_impact_and_close_with_correct_overnight_duration(): void
    {
        $this->actingAs($this->staff());
        $data = $this->input();
        $this->post(route('fire-incidents.store'), $data)->assertSessionHasNoErrors();
        $incident = FireIncident::sole();
        $this->assertSame('2026-10-09 15:50:00', $incident->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(42, $incident->individuals_affected);
        $this->assertSame(7, $incident->houses_destroyed);
        $this->assertSame('2nd', $incident->alarm_level);
        $this->assertSame('Verified', $incident->coordinate_accuracy);
        $data['status'] = 'Resolved';
        $data['fire_out_at'] = '2026-10-10T01:20';
        $this->put(route('fire-incidents.update', $incident), $data)->assertSessionHasNoErrors();
        $incident->refresh();
        $this->assertSame(90, $incident->duration_minutes);
        $this->assertTrue($incident->resolved_at->eq($incident->fire_out_at));
        $this->getJson(route('gis.data'))->assertJsonCount(0, 'incidents');
        $this->put(route('fire-incidents.update', $incident), $data)->assertForbidden();
    }

    public function test_fire_out_and_impact_validation_cannot_fake_a_closed_record(): void
    {
        $this->actingAs($this->staff());
        $data = $this->input();
        $this->post(route('fire-incidents.store'), array_replace($data, ['individuals_affected' => -1]))->assertSessionHasErrors('individuals_affected');
        $this->post(route('fire-incidents.store'), array_replace($data, ['status' => 'Resolved']))->assertSessionHasErrors('fire_out_at');
        $this->post(route('fire-incidents.store'), array_replace($data, ['status' => 'Resolved', 'fire_out_at' => '2026-10-09T20:00']))->assertSessionHasErrors('fire_out_at');
        $this->post(route('fire-incidents.store'), array_replace($data, ['record_classification' => 'Example']))->assertSessionHasErrors('record_classification');
        $this->assertDatabaseCount('fire_incidents', 0);
    }

    private function staff(): User
    {
        $role = Role::firstOrCreate(['slug' => 'fire-responder'], ['name' => 'Fire responder', 'is_active' => true]);
        foreach (['fire.view', 'fire.create', 'fire.edit', 'fire.delete', 'records.view', 'records.export', 'gis.view'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => explode('.', $slug)[0], 'is_active' => true]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function incident(array $attributes = []): FireIncident
    {
        return FireIncident::create(array_replace(['barangay_id' => Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true])->id,
            'incident_number' => 'MANUAL-1', 'incident_type' => 'Residential Fire', 'location' => 'Test street',
            'severity' => 'Minor', 'status' => 'Reported', 'reported_at' => '2026-10-09 16:00:00',
            'occurred_at' => '2026-10-09 15:50:00', 'latitude' => 14.58, 'longitude' => 121.03], $attributes));
    }

    private function input(): array
    {
        return ['barangay_id' => Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true])->id,
            'incident_type' => 'Residential Fire', 'location' => 'Test street', 'severity' => 'Minor', 'status' => 'Reported',
            'reported_at' => '2026-10-10T00:00', 'occurred_at' => '2026-10-09T23:50', 'latitude' => 14.58, 'longitude' => 121.03,
            'individuals_affected' => 42, 'houses_destroyed' => 7, 'alarm_level' => '2nd', 'cause' => 'Confirmed electrical fault'];
    }
}
