<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FloodOperationalAnalyticsService;
use App\Services\IncidentSlaService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentAnalyticsSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(12, 0));
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_elapsed_sla_uses_completion_times_excludes_rejections_and_keeps_open_ages_separate(): void
    {
        DB::table('incident_sla_targets')->insert(['hazard' => 'fire', 'validation_minutes' => 15, 'publication_minutes' => 10, 'completion_minutes' => 120]);
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
        $incident = FireIncident::create(['barangay_id' => $barangay->id, 'incident_number' => 'SLA-1', 'incident_type' => 'Residential',
            'location' => 'Hulo', 'status' => 'Resolved', 'record_status' => 'Finalized', 'finalized_at' => '2026-10-11 11:00:00',
            'reported_at' => '2026-10-11 10:00:00', 'record_classification' => 'Reported']);
        $this->report('done', ['created_at' => '2026-10-11 10:00:00', 'validated_at' => '2026-10-11 10:10:00',
            'published_at' => '2026-10-11 10:15:00', 'status' => 'Published', 'fire_incident_id' => $incident->id]);
        $this->report('waiting', ['created_at' => '2026-10-11 08:00:00']);
        $this->report('rejected', ['created_at' => '2026-10-11 08:00:00', 'status' => 'Rejected']);
        $data = app(IncidentSlaService::class)->build('fire', 2026);
        $this->assertSame(3, $data['total']);
        $this->assertSame(1, $data['rejected']);
        $this->assertSame(60.0, $data['stages']['completion']['median']);
        $this->assertSame(100.0, $data['stages']['completion']['compliance']);
        $this->assertSame(1, $data['stages']['completion']['overdue']);
        $this->assertSame(1, $data['stages']['completion']['waiting']);
        $this->assertSame('waiting', $data['attention'][0]['reference']);
        $this->assertSame(1, app(IncidentSlaService::class)->build('fire', 2026, $barangay->id)['total']);
        $this->assertSame(0, app(IncidentSlaService::class)->build('fire', 2025)['total']);
    }

    public function test_unknown_completion_and_invalid_intervals_do_not_become_zero_or_overdue(): void
    {
        $incident = FireIncident::create(['incident_number' => 'LEGACY', 'incident_type' => 'Residential', 'location' => 'Hulo',
            'status' => 'Resolved', 'record_status' => 'Finalized', 'reported_at' => now(), 'record_classification' => 'Reported']);
        $this->report('legacy', ['created_at' => '2026-10-11 10:00:00', 'validated_at' => '2026-10-11 09:00:00', 'fire_incident_id' => $incident->id]);
        $data = app(IncidentSlaService::class)->build('fire');
        $this->assertSame(1, $data['unknown_completion']);
        $this->assertSame(1, $data['stages']['validation']['invalid']);
        $this->assertNull($data['stages']['completion']['median']);
        $this->assertNull($data['stages']['completion']['compliance']);
        $this->assertSame(0, $data['stages']['completion']['waiting']);
    }

    public function test_flood_completion_tracks_staff_action_and_resets_when_reopened(): void
    {
        DB::table('flood_incident_records')->delete();
        $record = FloodTrainingRecord::create(['barangay' => 'Hulo', 'month' => 10, 'risk_level' => 'High', 'observed_at' => '2026-10-11 08:00:00', 'flood_status' => 'Active', 'flood_level_code' => 'C']);
        $this->report('flood', ['incident_type' => 'flood', 'created_at' => '2026-10-11 09:00:00', 'validated_at' => '2026-10-11 09:10:00',
            'published_at' => '2026-10-11 09:15:00', 'status' => 'Published', 'flood_training_record_id' => $record->id]);
        $record->update(['flood_status' => 'Subsided', 'subsided_at' => '2026-10-11 10:00:00']);
        $this->assertEquals(now(), $record->fresh()->handling_completed_at);
        $this->assertSame(180.0, app(IncidentSlaService::class)->build('flood')['stages']['completion']['median']);
        $insights = app(FloodOperationalAnalyticsService::class)->build(2026, 'Hulo');
        $this->assertSame(100.0, $insights['priority'][0]['rate']);
        $this->assertSame(2.0, $insights['priority'][0]['median_hours']);
        $record->update(['remarks' => 'Corrected notes']);
        $this->assertEquals(now(), $record->fresh()->handling_completed_at);
        $record->update(['flood_status' => 'Active', 'subsided_at' => null]);
        $this->assertNull($record->fresh()->handling_completed_at);
        $this->assertSame(1, app(IncidentSlaService::class)->build('flood')['stages']['completion']['waiting']);
    }

    public function test_only_operations_manager_can_configure_targets_and_invalid_targets_are_rejected(): void
    {
        foreach (['fire-responder', 'flood-analyst', 'administrator', 'public-resident'] as $slug) {
            $this->actingAs($this->user($slug))->post(route('incident-analytics.sla.update'), ['hazard' => 'fire', 'validation_minutes' => 15])->assertForbidden();
        }
        $manager = $this->user('operations-manager');
        $this->actingAs($manager)->post(route('incident-analytics.sla.update'), ['hazard' => 'fire', 'validation_minutes' => 0])->assertSessionHasErrors('validation_minutes');
        $this->post(route('incident-analytics.sla.update'), ['hazard' => 'fire', 'validation_minutes' => 15])->assertRedirect();
        $this->assertDatabaseHas('incident_sla_targets', ['hazard' => 'fire', 'validation_minutes' => 15, 'updated_by' => $manager->id, 'completion_minutes' => null]);
    }

    public function test_cause_counts_include_unknown_and_do_not_include_deleted_or_example_records(): void
    {
        foreach ([['CAUSE-1', 'Electrical', 'Reported'], ['CAUSE-2', null, 'Dataset'], ['CAUSE-3', 'Electrical', 'Example']] as [$number, $cause, $classification]) {
            FireIncident::create(['incident_number' => $number, 'incident_type' => 'Residential', 'location' => 'Hulo', 'status' => 'Reported',
                'reported_at' => now(), 'occurred_at' => now(), 'cause' => $cause, 'record_classification' => $classification]);
        }
        $data = app(FireAnalyticsService::class)->getCauseDistribution(2026);
        $this->assertSame(2, array_sum($data['values']));
        $this->assertContains('Unknown', $data['labels']);
    }

    public function test_flood_dashboard_uses_incident_records_and_excludes_deleted_history(): void
    {
        $data = app(\App\Services\FloodAnalyticsService::class)->getDashboardData();
        $this->assertSame(6874, $data['kpis']['total_records']);
        $this->assertSame(6874, array_sum($data['monthly_trend']['records']));
        $this->assertSame(6874, array_sum($data['risk_distribution']['values']));
        $first = \App\Models\FloodIncidentRecord::first();
        $first->delete();
        $this->assertSame(6873, app(\App\Services\FloodAnalyticsService::class)->getDashboardStats()['total_records']);
        $insights = app(FloodOperationalAnalyticsService::class)->build();
        $this->assertSame(6873, $insights['source_counts']['incident_records']);
        $this->assertSame(6873, array_sum(array_column($insights['priority'], 'count')));
    }

    private function report(string $reference, array $data = []): PublicIncidentReport
    {
        $report = new PublicIncidentReport;
        $report->forceFill(array_replace(['reference' => $reference, 'submission_token' => hash('sha256', $reference), 'incident_type' => 'fire',
            'latitude' => 14.58, 'longitude' => 121.03, 'status' => 'Pending', 'created_at' => now()], $data))->save();

        return $report;
    }

    private function user(string $slug): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $slug)->sole()->id, 'is_active' => true]);
    }
}
