<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FireIncidentRecordImporter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FireRecordFinalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(3, 0));
        Barangay::create(['name' => 'Hulo', 'district' => 2, 'is_active' => true]);
    }

    public function test_fire_out_then_assessment_then_finalization_preserves_response_details_and_locks_the_record(): void
    {
        $user = $this->staff('operations-manager');
        $this->actingAs($user);
        $incident = $this->createIncident();
        $this->assertSame('Open', $incident->record_status);
        $this->assertNull($incident->individuals_affected);
        $this->assertNull($incident->houses_destroyed);
        $this->get(route('fire-incidents.create'))->assertOk()->assertDontSee('name="houses_destroyed"', false);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('Record Fire Out')->assertSee('Not yet assessed');
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])
            ->assertRedirect(route('fire-incidents.show', $incident))->assertSessionHasNoErrors();
        $incident->refresh();
        $this->assertSame('Resolved', $incident->status);
        $this->assertSame('For Assessment', $incident->record_status);
        $this->assertSame('2026-10-09 16:55:00', $incident->fire_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(45, $incident->duration_minutes);
        $this->assertTrue($incident->resolved_at->eq($incident->fire_out_at));
        $this->assertNull($incident->individuals_affected);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('Finalize Record');
        $this->get(route('fire-incidents.assessment', $incident))->assertOk()->assertSee('Impact Assessment')->assertSee('name="houses_destroyed"', false);
        $this->get(route('fire-incidents.edit', $incident))->assertForbidden();
        $this->put(route('fire-incidents.update', $incident), $this->input())->assertForbidden();
        $this->delete(route('fire-incidents.destroy', $incident))->assertForbidden();
        $this->getJson(route('gis.data'))->assertJsonCount(0, 'incidents');
        $this->getJson(route('gis.data', ['fire_layer' => 'history']))->assertJsonCount(1, 'incidents')->assertJsonPath('incidents.0.record_status', 'For Assessment');
        $this->get(route('public.flood-map'))->assertOk()->assertViewHas('fires', fn ($fires) => $fires->isEmpty());
        $this->get(route('operational-records.index', ['dataset' => 'fire-incidents']))->assertOk()->assertSee('Not yet assessed');
        $csv = $this->get(route('operational-records.export', ['dataset' => 'fire-incidents']))->streamedContent();
        $this->assertStringContainsString('Not yet assessed', $csv);

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(1, collect($dashboard->viewData('workflowCards'))->firstWhere('key', 'fire-assessment')['count']);
        $task = $dashboard->viewData('workQueue')->firstWhere('key', 'fire-assessment-'.$incident->id);
        $this->assertSame('Finalize Record', $task['action']);
        $this->assertSame(route('fire-incidents.assessment', $incident), $task['url']);
        $this->get(route('fire-incidents.index', ['record_status' => 'For Assessment']))->assertOk()
            ->assertViewHas('incidents', fn ($incidents) => $incidents->total() === 1);
        $before = app(FireAnalyticsService::class)->getDashboardData();
        $this->assertSame(1, $before['kpis']['total_incidents']);
        $this->assertSame(1, $before['kpis']['resolved_incidents']);
        $this->assertSame(1, $before['kpis']['for_assessment']);
        $this->assertSame(0, $before['kpis']['individuals_affected']);

        $responseDetails = $incident->only(['occurred_at', 'reported_at', 'responded_at', 'fire_out_at', 'resolved_at', 'duration_minutes', 'barangay_id', 'latitude', 'longitude', 'status']);
        $this->post(route('fire-incidents.finalize', $incident), $this->assessment())
            ->assertRedirect(route('fire-incidents.show', $incident))->assertSessionHasNoErrors();
        $incident->refresh();
        $this->assertEquals($responseDetails, $incident->only(array_keys($responseDetails)));
        $this->assertSame('Finalized', $incident->record_status);
        $this->assertSame(42, $incident->individuals_affected);
        $this->assertSame(7, $incident->houses_destroyed);
        $this->assertSame('Electrical fault', $incident->cause);
        $this->assertSame($user->id, $incident->finalized_by);
        $this->assertNotNull($incident->finalized_at);
        $audit = \App\Models\ActivityLog::where('route_name', 'fire-incidents.finalize')->sole();
        $this->assertSame('finalize_fire_record', $audit->action);
        $this->assertSame('For Assessment', $audit->old_values['record_status']);
        $this->assertSame($user->id, $audit->user_id);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('This record is locked.')->assertDontSee('>Finalize Record<', false);
        $this->get(route('fire-incidents.assessment', $incident))->assertForbidden();
        $this->post(route('fire-incidents.finalize', $incident), ['individuals_affected' => 100, 'houses_destroyed' => 50])->assertForbidden();
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T01:00'])->assertForbidden();
        $this->put(route('fire-incidents.update', $incident), $this->input())->assertForbidden();
        $this->delete(route('fire-incidents.destroy', $incident))->assertForbidden();
        $this->assertSame(42, $incident->fresh()->individuals_affected);
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(0, collect($dashboard->viewData('workflowCards'))->firstWhere('key', 'fire-assessment')['count']);
        $after = app(FireAnalyticsService::class)->getDashboardData();
        $this->assertSame(42, $after['kpis']['individuals_affected']);
        $this->assertSame(7, $after['kpis']['houses_destroyed']);
        $this->assertSame(1, $after['kpis']['finalized_records']);
        $this->assertSame(0, $after['kpis']['for_assessment']);
        $this->assertSame(42, array_sum($after['monthly_trend']['individuals_affected']));
        $this->assertSame(7, array_sum($after['top_barangays']['houses_destroyed']));
        $this->assertSame(42, array_sum($after['affected_by_barangay']['values']));
        $this->assertSame(7, array_sum($after['houses_destroyed_by_barangay']['values']));
        $this->assertDatabaseCount('sms_logs', 0);
        Http::assertNothingSent();
    }

    public function test_fire_out_requires_a_valid_time_and_cannot_close_twice(): void
    {
        $this->actingAs($this->staff());
        $incident = $this->createIncident();
        $this->post(route('fire-incidents.fire-out', $incident), [])->assertSessionHasErrors('fire_out_at');
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:05'])->assertSessionHasErrors('fire_out_at');
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:18'])->assertSessionHasErrors('fire_out_at');
        $this->assertSame('Open', $incident->fresh()->record_status);
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])->assertSessionHasNoErrors();
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T01:55'])->assertForbidden();
        $this->assertSame(45, $incident->fresh()->duration_minutes);
    }

    public function test_older_incomplete_closed_record_can_supply_missing_fire_out_without_reopening_the_fire(): void
    {
        $this->actingAs($this->staff());
        $incident = $this->createIncident();
        $incident->update(['status' => 'Resolved']);
        $this->get(route('fire-incidents.edit', $incident))->assertForbidden();
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $task = $dashboard->viewData('workQueue')->firstWhere('key', 'fire-assessment-'.$incident->id);
        $this->assertSame('Record Fire Out', $task['action']);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('missing fire-out time')->assertSee('Record Fire Out');
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])->assertSessionHasNoErrors();
        $this->assertSame('Resolved', $incident->fresh()->status);
        $this->assertSame('For Assessment', $incident->fresh()->record_status);
        $this->assertSame(45, $incident->fresh()->duration_minutes);
        $this->post(route('fire-incidents.finalize', $incident), $this->assessment())->assertSessionHasNoErrors();
        $this->assertSame('Finalized', $incident->fresh()->record_status);
    }

    public function test_finalization_requires_fire_out_and_explicit_integer_totals_but_accepts_confirmed_zero(): void
    {
        $this->actingAs($this->staff());
        $incident = $this->createIncident();
        $this->get(route('fire-incidents.assessment', $incident))->assertForbidden();
        $this->post(route('fire-incidents.finalize', $incident), $this->assessment())->assertForbidden();
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])->assertSessionHasNoErrors();
        $this->post(route('fire-incidents.finalize', $incident), [])->assertSessionHasErrors(['individuals_affected', 'houses_destroyed']);
        foreach ([-1, 1.5, 4294967296] as $invalid) {
            $this->post(route('fire-incidents.finalize', $incident), array_replace($this->assessment(), ['houses_destroyed' => $invalid]))->assertSessionHasErrors('houses_destroyed');
        }
        $this->assertSame('For Assessment', $incident->fresh()->record_status);
        $this->post(route('fire-incidents.finalize', $incident), ['individuals_affected' => 0, 'houses_destroyed' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Finalized', $incident->fresh()->record_status);
        $this->assertSame(0, $incident->fresh()->individuals_affected);
        $this->assertSame(0, $incident->fresh()->houses_destroyed);
    }

    public function test_record_stage_and_finalization_identity_cannot_be_forged_and_assessment_cannot_change_response(): void
    {
        $this->actingAs($this->staff());
        $this->post(route('fire-incidents.store'), array_replace($this->input(), ['record_status' => 'Finalized']))->assertSessionHasErrors('record_status');
        $incident = $this->createIncident();
        $this->put(route('fire-incidents.update', $incident), array_replace($this->input(), ['finalized_by' => 999]))->assertSessionHasErrors('finalized_by');
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])->assertSessionHasNoErrors();
        foreach (['status' => 'Reported', 'fire_out_at' => '2026-10-10T01:00', 'record_status' => 'Finalized', 'finalized_by' => 999, 'latitude' => 14.6] as $field => $value) {
            $this->post(route('fire-incidents.finalize', $incident), array_replace($this->assessment(), [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertSame('For Assessment', $incident->fresh()->record_status);
        $this->assertNull($incident->fresh()->finalized_by);
    }

    public function test_assessments_are_role_scoped_and_view_only_users_receive_no_finalize_action(): void
    {
        $this->actingAs($this->staff());
        $incident = $this->createIncident();
        $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T00:55'])->assertSessionHasNoErrors();
        foreach (['flood-analyst', 'public-resident'] as $slug) {
            $this->actingAs($this->staff($slug));
            $this->get(route('fire-incidents.assessment', $incident))->assertForbidden();
            $this->post(route('fire-incidents.finalize', $incident), $this->assessment())->assertForbidden();
            $this->post(route('fire-incidents.fire-out', $incident), ['fire_out_at' => '2026-10-10T01:00'])->assertForbidden();
        }
        $viewer = $this->staff();
        $viewer->role->permissions()->sync(Permission::whereIn('slug', ['dashboard.view', 'fire.view'])->pluck('id'));
        $this->actingAs($viewer);
        $dashboard = $this->get(route('dashboard'))->assertOk();
        $task = $dashboard->viewData('workQueue')->firstWhere('key', 'fire-assessment-'.$incident->id);
        $this->assertSame('View Assessment', $task['action']);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertDontSee('>Finalize Record<', false);
        $this->get(route('fire-incidents.assessment', $incident))->assertForbidden();
    }

    public function test_preliminary_counts_do_not_enter_finalized_impact_totals(): void
    {
        $this->actingAs($this->staff());
        $this->post(route('fire-incidents.store'), array_replace($this->input(), ['individuals_affected' => 300, 'houses_destroyed' => 100]))->assertSessionHasNoErrors();
        $data = app(FireAnalyticsService::class)->getDashboardData();
        $this->assertSame(1, $data['kpis']['total_incidents']);
        $this->assertSame(0, $data['kpis']['individuals_affected']);
        $this->assertSame(0, array_sum($data['monthly_trend']['houses_destroyed']));
        $this->assertSame(0, array_sum($data['top_barangays']['individuals_affected']));
        $this->assertSame(0, array_sum($data['affected_by_barangay']['values']));
        $this->assertSame(0, array_sum($data['houses_destroyed_by_barangay']['values']));
    }

    public function test_complete_imported_dataset_stays_finalized_and_out_of_assessment_queues(): void
    {
        app(FireIncidentRecordImporter::class)->import();
        $this->assertSame(117, FireIncident::where('record_status', 'Finalized')->count());
        $this->actingAs($this->staff());
        $data = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(0, collect($data->viewData('workflowCards'))->firstWhere('key', 'fire-assessment')['count']);
        $record = FireIncident::where('incident_number', 'FIR-0001')->sole();
        $this->get(route('fire-incidents.assessment', $record))->assertForbidden();
        app(FireIncidentRecordImporter::class)->import();
        $this->assertSame(117, FireIncident::where('record_status', 'Finalized')->count());
        $this->assertSame(40221, app(FireAnalyticsService::class)->getDashboardStats()['individuals_affected']);
    }

    public function test_migration_preserves_complete_closed_records_and_marks_incomplete_records_for_assessment(): void
    {
        $migration = require database_path('migrations/2026_10_10_160000_add_fire_record_finalization.php');
        $migration->down();
        $base = ['barangay_id' => Barangay::first()->id, 'incident_type' => 'Residential', 'location' => 'Hulo',
            'severity' => 'Minor', 'status' => 'Resolved', 'reported_at' => '2026-10-09 16:15:00',
            'fire_out_at' => '2026-10-09 16:55:00', 'individuals_affected' => 42, 'houses_destroyed' => 7];
        DB::table('fire_incidents')->insert(array_replace($base, ['incident_number' => 'COMPLETE']));
        DB::table('fire_incidents')->insert(array_replace($base, ['incident_number' => 'INCOMPLETE', 'houses_destroyed' => null]));
        DB::table('fire_incidents')->insert(array_replace($base, ['incident_number' => 'DELETED', 'deleted_at' => now(), 'record_classification' => 'Superseded']));
        DB::table('fire_incidents')->insert(array_replace($base, ['incident_number' => 'ONGOING', 'status' => 'Responding', 'fire_out_at' => null]));
        $migration->up();
        $this->assertDatabaseHas('fire_incidents', ['incident_number' => 'COMPLETE', 'record_status' => 'Finalized', 'individuals_affected' => 42, 'houses_destroyed' => 7]);
        $this->assertDatabaseHas('fire_incidents', ['incident_number' => 'INCOMPLETE', 'record_status' => 'For Assessment', 'houses_destroyed' => null]);
        $this->assertDatabaseHas('fire_incidents', ['incident_number' => 'ONGOING', 'record_status' => 'Open']);
        $this->assertDatabaseHas('fire_incidents', ['incident_number' => 'DELETED', 'record_status' => 'Finalized', 'record_classification' => 'Superseded']);
        $this->assertNotNull(DB::table('fire_incidents')->where('incident_number', 'DELETED')->value('deleted_at'));
        $this->assertDatabaseCount('fire_incidents', 4);
    }

    private function staff(string $slug = 'fire-responder'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $slug)->sole()->id, 'is_active' => true]);
    }

    private function input(): array
    {
        return ['barangay_id' => Barangay::first()->id, 'incident_type' => 'Residential Fire', 'location' => 'Hulo street',
            'latitude' => 14.58, 'longitude' => 121.03, 'severity' => 'Minor', 'status' => 'Responding', 'alarm_level' => '1st',
            'occurred_at' => '2026-10-10T00:10', 'reported_at' => '2026-10-10T00:15', 'responded_at' => '2026-10-10T00:20'];
    }

    private function createIncident(): FireIncident
    {
        $this->post(route('fire-incidents.store'), $this->input())->assertSessionHasNoErrors();
        return FireIncident::sole();
    }

    private function assessment(): array
    {
        return ['individuals_affected' => 42, 'houses_destroyed' => 7, 'cause' => 'Electrical fault', 'remarks' => 'Impact totals verified by staff.'];
    }
}
