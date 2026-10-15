<?php
namespace Tests\Feature;

use App\Models\{ActivityLog, Barangay, FireIncident, PublicIncidentReport, ReportArchive, Role, SmsLog, User};
use App\Services\{CommunityReportAlertService, FireIncidentAlertService, PublicReportVerification, ResidentSubscriptionService};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class PostDefenseRevisionsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['https://sms.test/send' => Http::response(['ok' => true])]);
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 08:00:00', 'UTC'));
    }
    private function user(string $role = 'operations-manager'): User {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'is_active' => true, 'receive_flood_alerts' => false, 'receive_fire_alerts' => false]);
    }
    private function barangay(): Barangay {
        return Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true]);
    }
    private function report(User $resident, string $street = 'Mabini Street'): PublicIncidentReport {
        return PublicIncidentReport::create(['reference' => 'PR-'.Str::ulid(), 'submission_token' => (string) Str::uuid(),
            'incident_type' => 'fire', 'status' => 'Pending', 'submitted_by' => $resident->id,
            'latitude' => 14.5794, 'longitude' => 121.0359, 'street' => $street]);
    }
    private function incident(): FireIncident {
        return FireIncident::create(['incident_number' => 'FI-TEST-'.Str::random(5), 'barangay_id' => $this->barangay()->id,
            'incident_type' => 'Residential fire', 'location' => 'Test Street', 'street' => 'Test Street',
            'latitude' => 14.5794, 'longitude' => 121.0359, 'severity' => 'Major', 'status' => 'Reported',
            'reported_at' => now()->subHour(), 'occurred_at' => now()->subHours(2), 'coordinate_accuracy' => 'Verified']);
    }
    public function test_only_three_confirmed_fraud_reports_suspend_and_invalidate_a_resident(): void {
        $resident = $this->user('public-resident');
        $operator = $this->user();
        for ($i=1; $i<=3; $i++) {
            $report = $this->report($resident);
            $this->actingAs($operator)->post(route('public-submissions.reject', $report), [
                'rejection_reason' => 'Field verification confirmed deliberate false reporting.', 'confirmed_fraud' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($i === 3, (bool) $resident->fresh()->suspended_at);
            $this->post(route('public-submissions.reject', $report), ['rejection_reason' => 'Repeated request.', 'confirmed_fraud' => 1])->assertStatus(409);
        }
        $this->assertFalse($resident->fresh()->is_active);
        $this->actingAs($resident->fresh())->get(route('public.reports'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs', ['subject_type' => User::class, 'subject_id' => $resident->id, 'action' => 'record_updated']);
    }
    public function test_unconfirmed_rejections_do_not_suspend(): void {
        $resident = $this->user('public-resident');
        $this->actingAs($this->user());
        for ($i=0; $i<3; $i++) $this->post(route('public-submissions.reject', $this->report($resident)), ['rejection_reason' => 'Could not confirm the incident.'])->assertRedirect();
        $this->assertNull($resident->fresh()->suspended_at);
        $this->assertTrue($resident->fresh()->is_active);
    }
    public function test_different_streets_and_missing_streets_do_not_combine(): void {
        $verification = app(PublicReportVerification::class);
        $settings = (object) ['threshold' => 2, 'radius_m' => 100, 'window_minutes' => 120];
        foreach (['Alpha Street', 'Beta Street', '', ''] as $street) {
            $report = $this->report($this->user('public-resident'), $street);
            DB::transaction(fn () => $verification->verify($report, $settings));
        }
        $this->assertSame(0, PublicIncidentReport::where('community_verified', true)->count());
    }
    public function test_community_sms_uses_pin_barangay_and_is_sent_once_per_subscriber(): void {
        $subscriber = $this->user('public-resident');
        $names = app(\App\Services\IncidentReportArea::class)->barangayNames(14.5794, 121.0359);
        $this->assertCount(1, $names);
        $barangay = Barangay::firstOrCreate(['name' => $names[0]], ['district' => 1, 'is_active' => true]);
        $subscriber->update(['barangay_id' => $barangay->id, 'contact_number' => '+639171234567', 'receive_fire_alerts' => true]);
        app(ResidentSubscriptionService::class)->sync($subscriber);
        $report = $this->report($subscriber);
        $report->forceFill(['community_verified' => true, 'report_group_id' => $report->id, 'status' => 'Validated'])->save();
        app(CommunityReportAlertService::class)->send($report->id);
        app(CommunityReportAlertService::class)->send($report->id);
        $this->assertSame(1, SmsLog::where('public_report_group_id', $report->id)->where('status', 'sent')->count());
        Http::assertSentCount(1);
    }
    public function test_fire_progress_updates_alarm_and_sends_one_sms_per_event(): void {
        $operator = $this->user();
        $resident = $this->user('public-resident');
        $resident->update(['barangay_id' => $this->barangay()->id, 'contact_number' => '+639171234567', 'receive_fire_alerts' => true]);
        app(ResidentSubscriptionService::class)->sync($resident);
        $incident = $this->incident();
        $this->actingAs($operator)->post(route('fire-incidents.progress', $incident), [
            'observed_at' => '2026-10-15T15:45', 'status' => 'Responding', 'alarm_level' => '2nd',
            'alarm_source' => 'BFP radio report', 'notes' => 'Response teams are on site.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $incident->refresh();
        $this->assertSame('2nd', $incident->alarm_level);
        $update = $incident->progressUpdates()->firstOrFail();
        $this->assertSame('2026-10-15 07:45:00', $update->observed_at->format('Y-m-d H:i:s'));
        $summary = app(FireIncidentAlertService::class)->sendProgressAlert($incident, $update, $operator->id);
        $this->assertSame(1, $summary['skipped']);
        Http::assertSentCount(1);
        $this->assertStringContainsString('BFP alarm: 2nd', SmsLog::where('fire_incident_id', $incident->id)->firstOrFail()->message);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('Response teams are on site.');
    }
    public function test_future_progress_and_unauthorized_progress_are_rejected(): void {
        $incident = $this->incident();
        $data = ['observed_at' => '2026-10-16T15:45', 'status' => 'Responding', 'notes' => 'Future report.'];
        $this->actingAs($this->user())->post(route('fire-incidents.progress', $incident), $data)->assertSessionHasErrors('observed_at');
        $this->actingAs($this->user('public-resident'))->post(route('fire-incidents.progress', $incident), $data)->assertForbidden();
        $this->assertSame(0, $incident->progressUpdates()->count());
    }
    public function test_fire_out_progress_enters_assessment_and_blocks_later_updates(): void {
        $incident = $this->incident();
        $this->actingAs($this->user());
        $data = ['observed_at' => '2026-10-15T15:45', 'status' => 'Resolved', 'notes' => 'BFP confirmed fire out.'];
        $this->post(route('fire-incidents.progress', $incident), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('For Assessment', $incident->fresh()->record_status);
        $this->post(route('fire-incidents.progress', $incident), $data)->assertStatus(409);
    }
    public function test_archived_report_is_immutable_and_public_cannot_export(): void {
        $incident = $this->incident();
        $this->actingAs($this->user());
        $this->post(route('operational-records.archives.store'), ['dataset' => 'fire-incidents'])->assertRedirect();
        $archive = ReportArchive::firstOrFail();
        $this->assertCount(1, $archive->snapshot['rows']);
        $incident->update(['location' => 'CHANGED LOCATION']);
        $this->get(route('operational-records.archives.show', $archive))->assertOk()->assertSee('Test Street')->assertDontSee('CHANGED LOCATION');
        $this->get(route('operational-records.print', ['dataset' => 'fire-incidents']))->assertOk()->assertSee('CHANGED LOCATION');
        $this->actingAs($this->user('public-resident'))->get(route('operational-records.archives.show', $archive))->assertForbidden();
        $this->get(route('operational-records.print'))->assertForbidden();
    }
    public function test_casualty_counts_and_bfp_reference_are_saved_at_validation(): void {
        $report = $this->report($this->user('public-resident'));
        $this->actingAs($this->user())->post(route('public-submissions.validate', $report), [
            'validation_notes' => 'Confirmed through response team.', 'alarm_level' => '3rd', 'alarm_source' => 'BFP radio',
            'injured_count' => 2, 'deceased_count' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $report->fresh()->injured_count);
        $this->assertSame(0, $report->fresh()->deceased_count);
        $this->assertSame('BFP radio', $report->fresh()->alarm_source);
    }
    public function test_insert_audit_does_not_replace_generated_business_ids(): void {
        for ($i=0; $i<4; $i++) app(\App\Services\Audit\AuditRecorder::class)->record(['action' => 'test', 'module' => 'test', 'description' => 'Offset audit sequence.']);
        $role = Role::create(['name' => 'ID regression', 'slug' => 'id-regression', 'is_active' => true]);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'slug' => 'id-regression']);
        $id = DB::table('roles')->insertGetId(['name' => 'Raw ID regression', 'slug' => 'raw-id-regression', 'is_active' => true]);
        $this->assertDatabaseHas('roles', ['id' => $id, 'slug' => 'raw-id-regression']);
    }
    public function test_residence_type_is_saved_at_registration_and_shown_for_staff_review(): void {
        $response = $this->post(route('public.register.store'), [
            'first_name' => 'Paul', 'last_name' => 'Banoc', 'email' => 'residence@example.test',
            'password' => 'Resident123!', 'password_confirmation' => 'Resident123!',
            'contact_number' => '09171234567', 'barangay_id' => $this->barangay()->id,
            'residence_type' => 'Tenant', 'receive_fire_alerts' => 0, 'receive_flood_alerts' => 0,
        ]);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $resident = User::where('email', 'residence@example.test')->firstOrFail();
        $this->assertSame('Tenant', $resident->residence_type);
        $this->actingAs($this->user('administrator'))->get(route('users.edit', $resident))->assertOk()->assertSee('Residence type');
    }
    public function test_only_admin_can_reinstate_and_the_fraud_history_is_retained(): void {
        $resident = $this->user('public-resident');
        $resident->forceFill(['suspended_at' => now(), 'is_active' => false, 'suspension_reason' => 'Confirmed fraud.'])->save();
        $this->actingAs($this->user())->post(route('users.reinstate', $resident), ['reason' => 'Reviewed the evidence.'])->assertForbidden();
        $this->actingAs($this->user('administrator'))->post(route('users.reinstate', $resident), ['reason' => 'Reviewed the evidence.'])->assertRedirect();
        $this->assertNull($resident->fresh()->suspended_at);
        $this->assertTrue($resident->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reinstate_resident', 'subject_id' => $resident->id]);
    }

}
