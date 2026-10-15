<?php
namespace Tests\Feature;
use App\Models\{Barangay, FireIncident, FloodIncidentRecord, FloodTrainingRecord, Role, User};
use App\Services\{FireAnalyticsService, FloodAnalyticsService, IncidentArchiveService};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class IncidentArchivesTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp(); $this->withoutVite(); Http::preventStrayRequests();
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 08:00:00', 'UTC'));
    }
    private function staff(string $role = 'operations-manager'): User {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'is_active' => true]);
    }
    private function fire(string $date = '2023-10-14 15:59:59', string $status = 'Resolved'): FireIncident {
        $barangay = Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true]);
        return FireIncident::create(['incident_number' => 'FI-'.uniqid(), 'barangay_id' => $barangay->id,
            'incident_type' => 'Residential fire', 'location' => 'Test archive street', 'severity' => 'Minor',
            'status' => $status, 'occurred_at' => $date, 'reported_at' => $date,
            'fire_out_at' => $status === 'Resolved' ? $date : null]);
    }
    public function test_closed_fire_can_be_archived_restored_and_remains_in_analytics(): void {
        $fire = $this->fire(); $this->actingAs($this->staff());
        $before = app(FireAnalyticsService::class)->getDashboardStats();
        $this->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Closed incident older than three years.'])->assertRedirect();
        $this->assertNotNull($fire->fresh()->archived_at);
        $this->assertSame($before, app(FireAnalyticsService::class)->getDashboardStats());
        $this->get(route('fire-incidents.index'))->assertOk()->assertDontSee($fire->incident_number);
        $this->get(route('operational-records.index', ['dataset' => 'fire-incidents']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        $this->get(route('operational-records.index', ['dataset' => 'fire-incidents', 'archive_state' => 'archived']))->assertOk()->assertSee($fire->incident_number);
        $this->get(route('incident-archives.show', ['type' => 'fire', 'id' => $fire->id]))->assertOk()->assertSee($fire->incident_number)->assertSee('Print / Save as PDF');
        $this->post(route('incident-archives.restore', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Needed for staff review.'])->assertRedirect();
        $this->assertNull($fire->fresh()->archived_at);
        $this->assertSame('Resolved', $fire->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'archive_incident', 'subject_id' => $fire->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'restore_incident', 'subject_id' => $fire->id]);
    }
    public function test_recent_exact_cutoff_and_active_incidents_cannot_be_archived(): void {
        $this->actingAs($this->staff());
        foreach ([['2023-10-14 16:00:00','Resolved'], ['2026-01-01 00:00:00','Resolved'], ['2020-01-01 00:00:00','Responding']] as [$date,$status]) {
            $record = $this->fire($date, $status);
            $this->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $record->id]), ['reason' => 'Archive request.'])->assertStatus(409);
            $this->assertNull($record->fresh()->archived_at);
        }

    }
    public function test_subsided_flood_uses_manila_calendar_cutoff_and_keeps_training_fields(): void {
        $record = FloodIncidentRecord::create(['event_id' => 'ARCHIVE-TEST', 'barangay' => 'Hulo',
            'flood_start_datetime' => '2023-10-14 23:59:59', 'observation_datetime' => '2023-10-14 23:59:59',
            'status' => 'Subsided', 'flood_code' => 'B', 'duration_hours' => 1, 'year' => 2023, 'month' => 10, 'day_of_week' => 'Saturday', 'event_date' => '2023-10-14']);
        $user = $this->staff();
        app(IncidentArchiveService::class)->change('flood', $record->id, $user, 'Older closed flood.');
        $this->assertSame('B', $record->fresh()->flood_code);
        $this->assertSame(2023, (int) $record->fresh()->year);
        $record->forceFill(['archived_at' => null, 'flood_start_datetime' => '2023-10-15 00:00:00'])->save();
        $this->assertFalse(app(IncidentArchiveService::class)->query('flood', 'eligible')->whereKey($record->id)->exists());
    }
    public function test_archive_requires_staff_permission_reason_and_correct_record_state(): void {
        $fire = $this->fire();
        $this->actingAs($this->staff('public-resident'))->get(route('incident-archives.index'))->assertForbidden();
        $this->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Unauthorized request.'])->assertForbidden();
        $this->actingAs($this->staff())->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $fire->id]), [])->assertSessionHasErrors('reason');
        $this->post(route('incident-archives.restore', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Not archived yet.'])->assertStatus(409);
    }
    public function test_eligible_and_archived_lists_search_and_print_are_available(): void {
        $fire = $this->fire(); $this->actingAs($this->staff());
        $this->get(route('incident-archives.index', ['state' => 'eligible', 'search' => $fire->incident_number]))->assertOk()->assertSee($fire->incident_number);
        $this->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Old closed incident.'])->assertRedirect();
        $this->post(route('incident-archives.archive', ['type' => 'fire', 'id' => $fire->id]), ['reason' => 'Duplicate request.'])->assertStatus(409);
        $this->get(route('incident-archives.index', ['search' => $fire->incident_number]))->assertOk()->assertSee($fire->incident_number)->assertSee('Restore incident');
        $this->get(route('operational-records.print', ['dataset' => 'fire-incidents', 'archive_state' => 'archived']))->assertOk()->assertSee($fire->incident_number);
    }
    public function test_bulk_archive_is_atomic_and_field_observations_keep_training_approval(): void {
        $old = $this->fire(); $recent = $this->fire('2026-01-01 00:00:00');
        $user = $this->staff(); $this->actingAs($user);
        $this->post(route('incident-archives.bulk'), ['type' => 'fire', 'action' => 'archive',
            'ids' => [$old->id, $recent->id], 'reason' => 'Bulk archive review.'])->assertStatus(409);
        $this->assertNull($old->fresh()->archived_at);
        $this->post(route('incident-archives.bulk'), ['type' => 'fire', 'action' => 'archive',
            'ids' => [$old->id], 'reason' => 'Bulk archive review.'])->assertRedirect();
        $this->assertNotNull($old->fresh()->archived_at);
        $observation = FloodTrainingRecord::create(['observed_at' => '2020-01-01 00:00:00', 'barangay' => 'Hulo',
            'month' => 1, 'risk_level' => 'Medium', 'flood_level_code' => 'B', 'flood_status' => 'Subsided', 'include_in_training' => true,
            'review_status' => 'Approved']);
        app(IncidentArchiveService::class)->change('observations', $observation->id, $user, 'Old field observation.');
        $this->assertTrue($observation->fresh()->include_in_training);
        $this->assertSame('Approved', $observation->fresh()->review_status);
        $this->get(route('incident-archives.index', ['type' => 'observations']))->assertOk()->assertSee('Hulo');
    }

}
