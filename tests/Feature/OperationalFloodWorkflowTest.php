<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
use App\Models\FloodIncidentRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LiveWeatherService;
use App\Services\OperationalFloodEnrichmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalFloodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-09T16:30:00Z'));
        Http::preventStrayRequests();
        Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true, 'latitude' => 14.58,
            'longitude' => 121.03, 'elevation_m' => 6, 'nearest_waterway' => 'Pasig River',
            'distance_to_waterway_m' => 120, 'drainage_index' => .4, 'impervious_surface_ratio' => .8,
            'population_density_per_km2' => 10000, 'historical_flood_count_5y' => 80]);
    }

    public function test_minimal_report_stamps_today_in_manila_and_automatically_fills_profile_and_weather(): void
    {
        $user = $this->staff(['records.view', 'gis.view', 'flood.create', 'flood.edit', 'prediction.view']);
        $this->mockWeather();
        $this->actingAs($user)->get(route('flood-operation.index'))->assertOk()
            ->assertDontSee('Training Readiness')->assertDontSee('Retry Enrichment')->assertDontSee('Pending Enrichment');
        $this->actingAs($user)->get(route('operational-records.flood.create'))->assertOk()
            ->assertSee('Today only')->assertSee('flood-record-map', false)
            ->assertDontSee('name="event_id"', false)->assertDontSee('name="wind_speed_kph"', false)
            ->assertDontSee('name="flood_start_datetime"', false);
        $payload = array_merge($this->payload(), ['wind_speed_kph' => 999, 'elevation_m' => 999,
            'latitude' => -90, 'longitude' => -180, 'duration_hours' => 999, 'storm_signal' => 5]);
        $this->post(route('operational-records.flood.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $record = $this->record();
        $this->assertSame('2026-10-10', $record->event_date);
        $this->assertSame('2026-10-10 00:30:00', $record->flood_start_datetime);
        $this->assertSame('Active', $record->status);
        $this->assertSame('Complete', $record->enrichment_status);
        $this->assertEquals(6, $record->elevation_m);
        $this->assertEquals(7.2, $record->wind_speed_kph);
        $this->assertEquals(225, $record->wind_direction_deg);
        $this->assertEquals(28.5, $record->temperature_c);
        $this->assertEquals(29, $record->temp_max_c);
        $this->assertEquals(27, $record->temp_min_c);
        $this->assertEquals(90, $record->rainfall_7d_mm);
        $this->assertEquals(10000, $record->population_density_per_km2);
        $this->assertNull($record->duration_hours);
        $this->assertNull($record->storm_signal);
        $this->assertGreaterThan(100, $record->extent_length_m);
        $this->assertGreaterThan(14, $record->latitude);
        $this->assertStringStartsWith('MAPS-', $record->event_id);
        $this->get(route('operational-records.flood.edit', $record))->assertOk()->assertSee('Weather & barangay details', false);
        $this->assertSame(6875, FloodIncidentRecord::count());
    }

    public function test_dates_status_and_identifiers_cannot_be_submitted_or_backdated(): void
    {
        $this->actingAs($this->staff());
        foreach (['event_id' => 'CUSTOM', 'observation_datetime' => '2025-01-01',
            'flood_start_datetime' => '2026-10-09', 'flood_subsided_datetime' => '2026-10-11', 'status' => 'Subsided'] as $field => $value) {
            $this->post(route('operational-records.flood.store'), array_merge($this->payload(), [$field => $value]))
                ->assertSessionHasErrors($field);
        }
        $this->assertSame(6874, FloodIncidentRecord::count());
    }

    public function test_only_valid_nonzero_lines_and_active_barangays_are_accepted(): void
    {
        $this->actingAs($this->staff());
        foreach ([null, ['type' => 'Point', 'coordinates' => [121, 14]],
            ['type' => 'LineString', 'coordinates' => [[121, 14]]],
            ['type' => 'LineString', 'coordinates' => [[181, 14], [121, 14]]],
            ['type' => 'LineString', 'coordinates' => [[121, 91], [121, 14]]],
            ['type' => 'LineString', 'coordinates' => [[121, 14], [121, 14]]],
            ['type' => 'LineString', 'coordinates' => [['bad', 14], [121, 14]]],
            ['type' => 'LineString', 'coordinates' => ['x' => [121, 14], 'y' => [121, 15]]]] as $geometry) {
            $this->post(route('operational-records.flood.store'), array_merge($this->payload(), ['geometry_geojson' => $geometry]))
                ->assertSessionHasErrors();
        }
        foreach (['Missing Barangay', 'Inactive'] as $name) {
            Barangay::firstOrCreate(['name' => $name], ['district' => 1, 'is_active' => false]);
            $this->post(route('operational-records.flood.store'), array_merge($this->payload(), ['barangay' => $name]))
                ->assertSessionHasErrors('barangay');
        }
        $this->post(route('operational-records.flood.store'), array_merge($this->payload(), ['flood_code' => 'E']))
            ->assertSessionHasErrors('flood_code');
        $this->assertSame(6874, FloodIncidentRecord::count());
    }

    public function test_code_can_only_increase_while_active_and_is_logged_without_replacing_original_report(): void
    {
        $user = $this->staff(); $record = $this->createReport($user);
        foreach (['A', 'B', 'E'] as $code) $this->post(route('operational-records.flood.raise', $record), ['flood_code' => $code])->assertSessionHasErrors('flood_code');
        $this->travelTo(Carbon::parse('2026-10-10T17:30:00Z'));
        $this->post(route('operational-records.flood.raise', $record), ['flood_code' => 'C'])->assertSessionHasNoErrors();
        $this->assertSame('C', $record->fresh()->flood_code);
        $this->assertSame('2026-10-10 00:30:00', $record->fresh()->flood_start_datetime);
        $this->assertDatabaseHas('flood_incident_updates', ['flood_incident_record_id' => $record->id,
            'user_id' => $user->id, 'action' => 'Raised', 'previous_code' => 'B', 'flood_code' => 'C']);
        $this->post(route('operational-records.flood.raise', $record), ['flood_code' => 'D'])->assertSessionHasNoErrors();
        $this->get(route('operational-records.flood.edit', $record))->assertOk()->assertDontSee('Raise code');
        $this->post(route('operational-records.flood.raise', $record), ['flood_code' => 'D'])->assertSessionHasErrors('flood_code');
    }

    public function test_subsidence_computes_overnight_duration_locks_code_and_removes_both_map_lines(): void
    {
        $record = $this->createReport($this->staff());
        $this->get(route('gis.data', ['hazard' => 'flood']))->assertOk()->assertJsonPath('floods.0.id', 'record-'.$record->id);
        $this->get(route('public.flood-map'))->assertOk()->assertViewHas('floods', fn ($rows) => $rows->count() === 1);
        $this->travelTo(Carbon::parse('2026-10-10T16:30:00Z'));
        $this->post(route('operational-records.flood.subside', $record))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-11 00:30:00', $record->fresh()->flood_subsided_datetime);
        $this->assertEquals(24, $record->fresh()->duration_hours);
        $this->post(route('operational-records.flood.raise', $record), ['flood_code' => 'D'])->assertSessionHasErrors('flood_code');
        $this->post(route('operational-records.flood.subside', $record))->assertSessionHasErrors('status');
        $payload = $this->payload(); unset($payload['flood_code']);
        $this->put(route('operational-records.flood.update', $record), $payload)->assertSessionHasErrors('status');
        $this->get(route('operational-records.flood.edit', $record))->assertOk()->assertDontSee('Raise code')->assertDontSee('Mark subsided')->assertDontSee('Save location');
        $this->get(route('gis.data', ['hazard' => 'flood']))->assertJsonCount(0, 'floods');
        $this->get(route('public.flood-map'))->assertViewHas('floods', fn ($rows) => $rows->isEmpty());
        $this->assertSame(1, DB::table('flood_incident_updates')->where('flood_incident_record_id', $record->id)->count());
        $this->assertDatabaseHas('flood_incident_records', ['id' => $record->id, 'status' => 'Subsided', 'flood_code' => 'B']);
    }

    public function test_location_edits_cannot_change_code_or_dates_and_geojson_from_html_is_accepted(): void
    {
        $record = $this->createReport($this->staff());
        $payload = $this->payload();
        $this->put(route('operational-records.flood.update', $record), $payload)->assertSessionHasErrors('flood_code');
        unset($payload['flood_code']);
        $payload['geometry_geojson'] = json_encode(['type' => 'LineString', 'coordinates' => [[121.02, 14.59], [121.021, 14.592]]]);
        $this->put(route('operational-records.flood.update', $record), $payload)->assertSessionHasNoErrors();
        $this->assertEquals(121.02, $record->fresh()->geometry_geojson['coordinates'][0][0]);
        $this->assertSame('B', $record->fresh()->flood_code);
        $this->assertSame('2026-10-10', $record->fresh()->event_date);
    }

    public function test_missing_or_stale_weather_keeps_incident_mapped_and_retry_fills_only_real_data(): void
    {
        $user = $this->staff();
        $this->mock(LiveWeatherService::class)->shouldReceive('getCurrentWeather')->once()->andReturn(array_merge($this->weather(), ['daily_snapshot_date' => '2026-10-09', 'weather_is_stale' => true]));
        $this->actingAs($user)->post(route('operational-records.flood.store'), $this->payload())->assertSessionHasNoErrors();
        $record = $this->record();
        $this->assertEquals(6, $record->elevation_m);
        $this->assertNull($record->wind_speed_kph);
        $this->assertSame('Pending data', $record->enrichment_status);
        $this->get(route('gis.data', ['hazard' => 'flood']))->assertJsonCount(1, 'floods');
        $this->get(route('operational-records.index'))->assertDontSee('Retry data')->assertDontSee('Automatic Data')->assertDontSee('Pending data');
        $this->mockWeather();
        $this->post(route('operational-records.flood.enrich', $record))->assertSessionHasNoErrors();
        $this->assertSame('Complete', $record->fresh()->enrichment_status);
        $this->assertEquals(7.2, $record->fresh()->wind_speed_kph);
    }

    public function test_enrichment_uses_the_existing_daily_snapshot_without_extra_weather_requests(): void
    {
        $weather = $this->weather();
        $weather['forecast_windows'] = ['24' => [], '48' => [], '72' => []];
        $weather['seven_day_forecast']['time'] = array_map(fn ($n) => Carbon::parse('2026-10-10')->addDays($n)->toDateString(), range(0, 6));
        DailyWeatherSnapshot::create(['snapshot_date' => '2026-10-10', 'source' => 'Open-Meteo', 'weather_data' => $weather, 'fetched_at' => now(), 'expires_at' => now()->addDay()]);
        $this->actingAs($this->staff())->post(route('operational-records.flood.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('operational-records.flood.enrich', $this->record()))->assertSessionHasNoErrors();
        Http::assertNothingSent();
        $this->assertSame('Complete', $this->record()->enrichment_status);
    }

    public function test_historical_records_stay_intact_and_a_retry_never_uses_todays_weather_for_an_older_report(): void
    {
        $record = $this->createReport($this->staff());
        $before = DB::table('flood_incident_records')->where('source_row', 1)->first();
        $this->travelTo(Carbon::parse('2026-10-11T00:30:00Z'));
        $record->update(['wind_speed_kph' => null]);
        app(OperationalFloodEnrichmentService::class)->enrich($record->fresh());
        $this->assertNull($record->fresh()->wind_speed_kph);
        $this->assertEquals($before, DB::table('flood_incident_records')->where('source_row', 1)->first());
    }

    public function test_role_permissions_and_deleted_records_are_enforced_on_every_action(): void
    {
        $record = $this->createReport($this->staff());
        $this->actingAs($this->staff([]));
        foreach (['raise', 'subside', 'enrich'] as $action) $this->post(route('operational-records.flood.'.$action, $record), ['flood_code' => 'C'])->assertForbidden();
        $this->post(route('operational-records.flood.store'), $this->payload())->assertForbidden();
        $this->get(route('gis.index', ['hazard' => 'flood']))->assertForbidden();
        $this->actingAs($this->staff());
        $this->delete(route('operational-records.flood.destroy', $record))->assertRedirect();
        foreach (['raise', 'subside', 'enrich'] as $action) $this->post(route('operational-records.flood.'.$action, $record), ['flood_code' => 'C'])->assertNotFound();
        $this->get(route('gis.data', ['hazard' => 'flood']))->assertJsonCount(0, 'floods');
        $this->get(route('public.flood-map'))->assertViewHas('floods', fn ($rows) => $rows->isEmpty());
    }

    public function test_flood_staff_without_operational_records_permission_returns_to_their_internal_gis_map(): void
    {
        $this->mockWeather();
        $user = $this->staff(['gis.view', 'flood.create', 'flood.edit']);
        $this->actingAs($user)->post(route('operational-records.flood.store'), $this->payload())
            ->assertRedirect(route('gis.index', ['hazard' => 'flood']));
        $this->get(route('gis.index', ['hazard' => 'flood']))->assertOk()->assertViewIs('gis.flood')->assertSee('Plot flood');
        $this->get(route('operational-records.flood.edit', $this->record()))->assertOk()
            ->assertDontSee(route('operational-records.index', ['dataset' => 'flood-records']), false);
        $this->post(route('operational-records.flood.raise', $this->record()), ['flood_code' => 'C'])
            ->assertRedirect(route('gis.index', ['hazard' => 'flood']));
    }

    public function test_active_operational_floods_enter_the_staff_dashboard_follow_up_queue(): void
    {
        $user = $this->staff(['records.view', 'gis.view', 'flood.create', 'flood.edit', 'prediction.view']);
        $user->role->update(['slug' => 'flood-analyst']);
        $record = $this->createReport($user);
        $data = app(\App\Services\ProcessDashboardService::class)->build($user->fresh(), $user->role->fresh()->load('permissions'));
        $this->assertSame(1, collect($data['workflowCards'])->firstWhere('key', 'flood')['count']);
        $task = $data['workQueue']->firstWhere('key', 'flood-record-'.$record->id);
        $this->assertSame(route('operational-records.flood.edit', $record), $task['url']);
        $this->post(route('operational-records.flood.subside', $record))->assertSessionHasNoErrors();
        $data = app(\App\Services\ProcessDashboardService::class)->build($user->fresh(), $user->role->fresh()->load('permissions'));
        $this->assertSame(0, collect($data['workflowCards'])->firstWhere('key', 'flood')['count']);
    }

    private function createReport(User $user): FloodIncidentRecord
    {
        $this->mockWeather();
        $this->actingAs($user)->post(route('operational-records.flood.store'), $this->payload())->assertSessionHasNoErrors();
        return $this->record();
    }

    private function record(): FloodIncidentRecord
    {
        return FloodIncidentRecord::whereNotNull('created_by')->latest('id')->firstOrFail();
    }

    private function mockWeather(): void
    {
        $this->mock(LiveWeatherService::class)->shouldReceive('getCurrentWeather')->andReturn($this->weather());
    }

    private function weather(): array
    {
        return ['date' => '2026-10-10', 'daily_snapshot_date' => '2026-10-10', 'source' => 'Open-Meteo',
            'observed_at' => '2026-10-10T00:15:00+08:00', 'avg_temp_mean_c' => 28.5, 'avg_rh_pct' => 80,
            'avg_wind_speed' => 2, 'avg_wind_direction_deg' => 225, 'observed_temp_max_c' => 29,
            'observed_temp_min_c' => 27, 'rainfall_24h_mm' => 20, 'rainfall_3d_mm' => 50, 'rainfall_7d_mm' => 90];
    }

    private function payload(): array
    {
        return ['barangay' => 'Hulo', 'flood_code' => 'B',
            'geometry_geojson' => ['type' => 'LineString', 'coordinates' => [[121.03, 14.58], [121.031, 14.581]]]];
    }

    private function staff(?array $permissions = null): User
    {
        $permissions ??= ['records.view', 'gis.view', 'flood.create', 'flood.edit', 'flood.delete'];
        $role = Role::create(['slug' => 'workflow-'.uniqid(), 'name' => 'Flood Staff '.uniqid(), 'is_active' => true]);
        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'flood', 'is_active' => true]);
            $role->permissions()->attach($permission);
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }
}
