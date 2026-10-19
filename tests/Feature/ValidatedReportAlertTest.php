<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\CommunityReportAlertService;
use App\Services\ResidentSubscriptionService;
use App\Services\ValidatedReportAlertService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidatedReportAlertTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneSequence = 1234500;
    private int $gatewayStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        Http::preventStrayRequests();
        Http::fake(['https://sms.test/send' => fn () => Http::response(['ok' => $this->gatewayStatus === 200], $this->gatewayStatus)]);
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
    }

    private function barangay(string $name = 'Vergara'): Barangay
    {
        return Barangay::firstOrCreate(['name' => $name], ['district' => 1, 'is_active' => true]);
    }

    private function resident(?Barangay $barangay = null, array $overrides = []): User
    {
        $resident = User::factory()->create(array_replace([
            'role_id' => Role::where('slug', 'public-resident')->value('id'),
            'barangay_id' => ($barangay ?? $this->barangay())->id,
            'contact_number' => '+63917'.$this->phoneSequence++,
            'receive_fire_alerts' => false, 'receive_flood_alerts' => false, 'is_active' => true,
        ], $overrides));
        app(ResidentSubscriptionService::class)->sync($resident);

        return $resident;
    }

    private function operator(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'operations-manager')->value('id'), 'is_active' => true]);
    }

    private function report(string $type = 'fire', string $status = 'Pending', array $overrides = []): PublicIncidentReport
    {
        return PublicIncidentReport::create(array_replace([
            'reference' => 'PR-'.Str::ulid(), 'submission_token' => (string) Str::uuid(),
            'incident_type' => $type, 'status' => $status, 'street' => 'Ilino Cruz Street',
            'latitude' => 14.6, 'longitude' => 121.0,
            'submitted_by' => $this->resident($this->barangay('Hulo'))->id,
            'reporter_barangay_id' => $this->barangay('Hulo')->id,
            'incident_barangay_id' => $this->barangay()->id,
        ], $overrides));
    }

    public function test_staff_validation_sends_each_hazard_to_its_incident_barangay_subscribers(): void
    {
        $this->resident($this->barangay('Hulo'), ['receive_fire_alerts' => true, 'receive_flood_alerts' => true]);
        $this->resident(); // Opted out of both hazards.
        $this->resident(null, ['receive_fire_alerts' => true, 'receive_flood_alerts' => true, 'is_active' => false]);
        $operator = $this->operator();

        foreach (['fire', 'flood'] as $type) {
            $subscriber = $this->resident(null, ['receive_'.$type.'_alerts' => true]);
            $report = $this->report($type);
            $this->actingAs($operator)->post(route('public-submissions.validate', $report), [
                'validation_notes' => 'Verified by the responding team.', 'alarm_level' => '2nd',
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertSame('Validated', $report->fresh()->status);
            $log = SmsLog::where('public_report_group_id', $report->id)->sole();
            $this->assertSame($subscriber->smsRecipient->id, $log->sms_recipient_id);
            $this->assertSame('sent', $log->status);
            $this->assertSame($operator->id, $log->sent_by);
            $this->assertSame('staff', $log->condition_data['validation_method']);
            $this->assertStringContainsString('Brgy. Vergara', $log->message);
            if ($type === 'fire') $this->assertStringContainsString('BFP alarm: 2nd', $log->message);
        }
        Http::assertSentCount(2);
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->assertDatabaseCount('flood_training_records', 0);
    }

    public function test_community_validation_sends_once_even_when_the_pin_is_outside_old_polygons(): void
    {
        DB::table('incident_report_settings')->where('id', 1)->update(['threshold' => 2]);
        $subscriber = $this->resident(null, ['receive_fire_alerts' => true]);
        foreach (range(1, 2) as $i) {
            $this->actingAs($this->resident($this->barangay('Hulo')))->post(route('public.incident-reports.store'), [
                'incident_type' => 'fire', 'incident_barangay_id' => $this->barangay()->id,
                'street' => 'Ilino Cruz Street', 'latitude' => 14.6, 'longitude' => 121.0,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect()->assertSessionHasNoErrors();
            if ($i === 1) Http::assertNothingSent();
        }
        $this->assertSame(2, PublicIncidentReport::where('status', 'Validated')->count());
        $log = SmsLog::sole();
        $this->assertSame($subscriber->smsRecipient->id, $log->sms_recipient_id);
        $this->assertSame('community', $log->condition_data['validation_method']);
        app(CommunityReportAlertService::class)->send(PublicIncidentReport::oldest('id')->firstOrFail()->id);
        Http::assertSentCount(1);
    }

    public function test_same_street_in_different_incident_barangays_does_not_combine(): void
    {
        DB::table('incident_report_settings')->where('id', 1)->update(['threshold' => 2]);
        foreach (['Vergara', 'Hulo'] as $name) {
            $this->actingAs($this->resident())->post(route('public.incident-reports.store'), [
                'incident_type' => 'fire', 'incident_barangay_id' => $this->barangay($name)->id,
                'street' => 'Ilino Cruz Street', 'latitude' => 14.6, 'longitude' => 121.0,
                'submission_token' => (string) Str::uuid(),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(2, PublicIncidentReport::where('status', 'Pending')->count());
        Http::assertNothingSent();
    }

    public function test_staff_and_community_callbacks_do_not_duplicate_a_group_alert(): void
    {
        $this->resident(null, ['receive_fire_alerts' => true]);
        $report = $this->report('fire', 'Validated');
        $report->forceFill(['report_group_id' => $report->id])->save();
        app(ValidatedReportAlertService::class)->send($report->id);
        $report->forceFill(['community_verified' => true])->save();
        app(CommunityReportAlertService::class)->send($report->id);
        $member = $this->report('fire', 'Validated');
        $member->forceFill(['report_group_id' => $report->id])->save();
        $summary = app(ValidatedReportAlertService::class)->send($member->id);
        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseCount('sms_logs', 1);
        Http::assertSentCount(1);
    }

    public function test_preexisting_community_alert_is_not_sent_again_after_the_upgrade(): void
    {
        $resident = $this->resident(null, ['receive_fire_alerts' => true]);
        $report = $this->report('fire', 'Validated');
        SmsLog::create([
            'public_report_group_id' => $report->id, 'sms_recipient_id' => $resident->smsRecipient->id,
            'recipient_name' => $resident->name, 'phone_number' => $resident->contact_number,
            'alert_key' => 'community_verified', 'message' => 'Previously sent community alert',
            'source' => 'automatic', 'status' => 'sent',
        ]);
        $summary = app(ValidatedReportAlertService::class)->send($report->id);
        $this->assertSame(1, $summary['skipped']);
        Http::assertNothingSent();
    }

    public function test_gateway_failure_is_logged_without_undoing_validation(): void
    {
        $this->gatewayStatus = 503;
        $this->resident(null, ['receive_fire_alerts' => true]);
        $report = $this->report();
        $this->actingAs($this->operator())->post(route('public-submissions.validate', $report), [
            'validation_notes' => 'Verified by the responding team.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Validated', $report->fresh()->status);
        $this->assertSame('failed', SmsLog::sole()->status);
        $this->assertNotEmpty(SmsLog::sole()->failure_reason);
    }

    public function test_pending_rejected_and_published_reports_do_not_send_a_validation_alert(): void
    {
        $this->resident(null, ['receive_fire_alerts' => true]);
        foreach (['Pending', 'Rejected', 'Published'] as $status) {
            app(ValidatedReportAlertService::class)->send($this->report('fire', $status)->id);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_legacy_report_requires_an_incident_barangay_before_staff_validation(): void
    {
        $this->resident(null, ['receive_fire_alerts' => true]);
        $report = $this->report('fire', 'Pending', ['incident_barangay_id' => null]);
        $this->actingAs($this->operator())->post(route('public-submissions.validate', $report), [
            'validation_notes' => 'Verified by the responding team.',
        ])->assertSessionHasErrors('incident_barangay_id');
        $this->assertSame('Pending', $report->fresh()->status);
        Http::assertNothingSent();
        $this->post(route('public-submissions.validate', $report), [
            'validation_notes' => 'Verified by the responding team.', 'incident_barangay_id' => $this->barangay()->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->barangay()->id, $report->fresh()->incident_barangay_id);
        Http::assertSentCount(1);
    }

    public function test_validation_sms_waits_for_commit_and_is_cancelled_on_rollback(): void
    {
        $this->resident(null, ['receive_fire_alerts' => true]);
        $report = $this->report();
        DB::beginTransaction();
        try {
            $this->actingAs($this->operator())->post(route('public-submissions.validate', $report), [
                'validation_notes' => 'Verified by the responding team.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            Http::assertNothingSent();
            $this->assertSame('Validated', $report->fresh()->status);
        } finally {
            DB::rollBack();
        }
        $this->assertSame('Pending', $report->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_unmapped_legacy_pin_does_not_notify_the_reporters_home_barangay(): void
    {
        $this->resident($this->barangay('Hulo'), ['receive_fire_alerts' => true]);
        app(ValidatedReportAlertService::class)->send($this->report('fire', 'Validated', ['incident_barangay_id' => null])->id);
        Http::assertNothingSent();
    }
}
