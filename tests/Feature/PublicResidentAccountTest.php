<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\Permission;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\SmsRecipient;
use App\Models\User;
use App\Services\FireIncidentAlertService;
use App\Services\FloodIncidentAlertService;
use App\Services\ResidentSubscriptionService;
use App\Services\LiveWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicResidentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake([]);
    }

    private function barangay(string $name = 'Hulo'): Barangay
    {
        return Barangay::firstOrCreate(['name' => $name], ['district' => 1, 'is_active' => true]);
    }

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Paul', 'last_name' => 'Bañoc', 'email' => 'paul@example.test',
            'password' => 'Resident123!', 'password_confirmation' => 'Resident123!',
            'contact_number' => '09171234567', 'barangay_id' => $this->barangay()->id,
            'receive_flood_alerts' => '1', 'receive_fire_alerts' => '1',
        ], $overrides);
    }

    private function resident(string $phone = '+639171234567', string $barangay = 'Hulo', array $overrides = []): User
    {
        $user = User::factory()->create(array_replace([
            'role_id' => Role::where('slug', 'public-resident')->value('id'),
            'barangay_id' => $this->barangay($barangay)->id, 'contact_number' => $phone,
            'receive_flood_alerts' => true, 'receive_fire_alerts' => true, 'is_active' => true,
        ], $overrides));
        app(ResidentSubscriptionService::class)->sync($user);

        return $user;
    }

    private function submission(): array
    {
        return ['incident_type' => 'fire', 'latitude' => 14.5794, 'longitude' => 121.0359,
            'submission_token' => (string) Str::uuid()];
    }

    public function test_guests_can_view_public_information_but_must_log_in_to_report(): void
    {
        $this->barangay();
        $this->mock(LiveWeatherService::class, function ($mock): void {
            $mock->shouldReceive('getCurrentWeather')->andReturn([]);
            $mock->shouldReceive('getSevenDayForecast')->andReturn(['days' => []]);
        });
        $this->get(route('public.portal'))->assertOk()->assertSee('Public Login')->assertSee('Create Account / Get Alerts');
        $this->get(route('public.flood-map'))->assertOk();
        $this->get(route('public.advisories'))->assertOk();
        $this->get(route('public.weather'))->assertOk();
        $this->get(route('public.incident-reports.create'))->assertRedirect(route('public.login'));
        $this->post(route('public.incident-reports.store'), $this->submission())->assertRedirect(route('public.login'));
        $this->assertDatabaseCount('public_incident_reports', 0);
        $this->get(route('public.login'))->assertOk();
        $this->get(route('public.register'))->assertOk()->assertSee('Hulo');
    }

    public function test_registration_sets_only_resident_role_and_synchronizes_opt_in_recipient(): void
    {
        $admin = Role::create(['name' => 'Admin', 'slug' => 'administrator', 'is_active' => true]);
        $this->post(route('public.register.store'), $this->registration([
            'email' => ' Paul@Example.test ', 'role_id' => $admin->id, 'is_active' => false,
            'approved_at' => now(), 'user_id' => 123,
        ]))->assertRedirect(route('public.account'));
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->isPublicResident());
        $this->assertTrue($user->is_active);
        $this->assertNull($user->approved_at);
        $this->assertTrue(Hash::check('Resident123!', $user->password));
        $this->assertSame('paul@example.test', $user->email);
        $this->assertSame('+639171234567', $user->contact_number);
        $this->assertDatabaseHas('sms_recipients', [
            'user_id' => $user->id, 'barangay_id' => $user->barangay_id,
            'receive_flood_alerts' => true, 'receive_fire_alerts' => true, 'receive_general_alerts' => false,
        ]);
        Http::assertNothingSent();
        $this->get(route('public.account'))->assertOk()->assertSee('My account & alerts', false);
    }

    public function test_registration_without_alert_consent_does_not_subscribe(): void
    {
        $payload = $this->registration();
        unset($payload['receive_flood_alerts'], $payload['receive_fire_alerts']);
        $this->post(route('public.register.store'), $payload)->assertRedirect();
        $recipient = SmsRecipient::sole();
        $this->assertFalse($recipient->is_active);
        $this->assertFalse($recipient->receive_flood_alerts);
        $this->assertFalse($recipient->receive_fire_alerts);
    }

    public function test_registration_rejects_duplicate_phone_invalid_barangay_and_bad_password(): void
    {
        $existing = $this->resident();
        $this->post(route('public.register.store'), $this->registration())->assertSessionHasErrors('contact_number');
        $this->post(route('public.register.store'), $this->registration(['contact_number' => '123']))->assertSessionHasErrors('contact_number');
        $inactive = $this->barangay('Inactive');
        $inactive->update(['is_active' => false]);
        $this->post(route('public.register.store'), $this->registration([
            'contact_number' => '09171234568', 'barangay_id' => $inactive->id,
            'password_confirmation' => 'different',
        ]))->assertSessionHasErrors('barangay_id');
        $this->post(route('public.register.store'), $this->registration([
            'contact_number' => '09171234568', 'password_confirmation' => 'different',
        ]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->id, SmsRecipient::sole()->user_id);
    }

    public function test_registration_cannot_claim_an_existing_staff_sms_number(): void
    {
        SmsRecipient::create(['full_name' => 'Existing contact', 'phone_number' => '+639171234567']);
        $this->post(route('public.register.store'), $this->registration())->assertSessionHasErrors('contact_number');
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(SmsRecipient::sole()->user_id);
    }

    public function test_resident_login_returns_to_report_form_and_keeps_staff_routes_forbidden(): void
    {
        $resident = $this->resident(overrides: ['email' => 'paul@example.test', 'password' => 'Resident123!']);
        $this->get(route('public.incident-reports.create'))->assertRedirect(route('public.login'));
        $this->post(route('public.login.attempt'), ['email' => $resident->email, 'password' => 'Resident123!'])
            ->assertRedirect(route('public.incident-reports.create'));
        foreach (['dashboard', 'users.index', 'sms.index', 'public-submissions.index', 'prediction.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->post(route('logout'))->assertRedirect(route('public.portal'));
        $this->assertGuest();
    }

    public function test_resident_cannot_gain_staff_access_even_if_permissions_are_assigned_to_role(): void
    {
        $resident = $this->resident();
        $permission = Permission::create(['name' => 'Dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard', 'is_active' => true]);
        $resident->role()->first()->permissions()->attach($permission);
        $this->actingAs($resident)->get(route('dashboard'))->assertForbidden();
    }

    public function test_inactive_account_or_role_cannot_log_in_report_or_receive_alerts(): void
    {
        $resident = $this->resident(overrides: ['password' => 'Resident123!']);
        $resident->update(['is_active' => false]);
        $this->post(route('public.login.attempt'), ['email' => $resident->email, 'password' => 'Resident123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($resident)->post(route('public.incident-reports.store'), $this->submission())->assertForbidden();
        $this->assertSame(0, SmsRecipient::eligibleForAlerts()->count());
        $resident->update(['is_active' => true]);
        $resident->role()->first()->update(['is_active' => false]);
        $this->get(route('public.account'))->assertForbidden();
        $this->assertSame(0, SmsRecipient::eligibleForAlerts()->count());
    }

    public function test_preferences_move_the_same_recipient_and_allow_complete_opt_out(): void
    {
        $resident = $this->resident();
        $recipientId = $resident->smsRecipient->id;
        $payload = $this->registration(['barangay_id' => $this->barangay('Plainview')->id,
            'contact_number' => '09171234568', 'receive_fire_alerts' => '0', 'role_id' => 999]);
        $this->actingAs($resident)->put(route('public.account.update'), $payload)->assertRedirect();
        $this->assertSame($recipientId, SmsRecipient::sole()->id);
        $this->assertDatabaseHas('sms_recipients', ['id' => $recipientId, 'barangay_id' => $payload['barangay_id'],
            'phone_number' => '+639171234568', 'receive_fire_alerts' => false, 'receive_flood_alerts' => true]);
        $this->assertTrue($resident->fresh()->isPublicResident());
        $payload['receive_flood_alerts'] = '0';
        $this->put(route('public.account.update'), $payload)->assertRedirect();
        $this->assertFalse(SmsRecipient::sole()->is_active);
    }

    public function test_reports_belong_to_the_signed_in_resident_and_history_is_private(): void
    {
        $resident = $this->resident();
        $other = $this->resident('+639171234568');
        $payload = $this->submission();
        $this->actingAs($resident)->post(route('public.incident-reports.store'), $payload + [
            'submitted_by' => $other->id, 'reporter_barangay_id' => 999, 'status' => 'Published',
        ])->assertRedirect();
        $report = PublicIncidentReport::sole();
        $this->assertSame($resident->id, $report->submitted_by);
        $this->assertSame($resident->barangay_id, $report->reporter_barangay_id);
        $this->assertSame('Pending', $report->status);
        $this->assertDatabaseHas('public_incident_report_events', ['actor_id' => $resident->id]);
        $this->post(route('public.incident-reports.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('public_incident_reports', 1);
        $report->update(['status' => 'Rejected', 'rejection_reason' => 'Duplicate confirmed incident.']);
        $this->get(route('public.reports'))->assertOk()->assertSee($report->reference)->assertSee('Duplicate confirmed incident.');
        $this->actingAs($other)->get(route('public.reports'))->assertOk()->assertDontSee($report->reference);
        $this->post(route('public.incident-reports.store'), $payload)->assertStatus(409);
        $this->get(route('public-submissions.show', $report))->assertForbidden();
        $this->post(route('public-submissions.validate', $report), ['validation_notes' => 'Trying to validate.'])->assertForbidden();
    }

    public function test_password_changes_require_current_password(): void
    {
        $resident = $this->resident(overrides: ['password' => 'Resident123!']);
        $payload = ['current_password' => 'Wrong', 'password' => 'NewResident123!', 'password_confirmation' => 'NewResident123!'];
        $this->actingAs($resident)->put(route('public.account.password'), $payload)->assertSessionHasErrors('current_password');
        $payload['current_password'] = 'Resident123!';
        $this->put(route('public.account.password'), $payload)->assertRedirect();
        $this->assertTrue(Hash::check('NewResident123!', $resident->fresh()->password));
    }

    public function test_staff_recipient_actions_cannot_override_resident_preferences_or_broadcast_to_them(): void
    {
        $resident = $this->resident();
        $recipient = $resident->smsRecipient;
        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'is_active' => true]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($admin)->put(route('sms.recipients.update', $recipient), [])->assertForbidden();
        $this->patch(route('sms.recipients.status', $recipient))->assertForbidden();
        $this->delete(route('sms.recipients.destroy', $recipient))->assertForbidden();
        $this->post(route('sms.send'), ['recipient_ids' => [$recipient->id], 'message' => 'Untyped broadcast.'])
            ->assertSessionHasErrors('recipient_ids');
        $this->assertDatabaseHas('sms_recipients', ['id' => $recipient->id, 'user_id' => $resident->id,
            'receive_flood_alerts' => true, 'receive_fire_alerts' => true]);
        Http::assertNothingSent();
    }

    public function test_sms_lists_show_internal_and_public_groups_before_search_and_preserve_selection_rules(): void
    {
        $resident = $this->resident();
        $public = $resident->smsRecipient;
        $officer = SmsRecipient::create(['full_name' => 'Bañoc "Officer"', 'phone_number' => '+639171234599',
            'position' => 'Fire Responder', 'barangay_id' => $this->barangay('New Zañiga')->id, 'is_active' => true]);
        $inactive = SmsRecipient::create(['full_name' => 'Inactive Officer', 'phone_number' => '+639171234598', 'is_active' => false]);
        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'is_active' => true]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $response = $this->actingAs($admin)->withSession(['_old_input' => [
            'recipient_ids' => [$officer->id, $inactive->id, [$public->id]],
        ]])->get(route('sms.index'))->assertOk()
            ->assertSee('Internal Officers')->assertSee('Public Residents')
            ->assertSee('manual-recipient-search')->assertSee('directory-recipient-search');
        $groups = collect($response->viewData('recipientGroups'))->keyBy('key');
        $this->assertEqualsCanonicalizing([$officer->id, $inactive->id], $groups['internal']['recipients']->pluck('id')->all());
        $this->assertSame([$public->id], $groups['public']['recipients']->pluck('id')->all());

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//*[@data-recipient-search-scope]')->length);
        foreach (['internal' => [$officer->id, $inactive->id], 'public' => [$public->id]] as $key => $expectedIds) {
            $panels = $xpath->query('//*[@data-recipient-group-key="'.$key.'"]');
            $this->assertSame(2, $panels->length);
            foreach ($panels as $panel) {
                $rows = $xpath->query('.//*[@data-recipient-row]', $panel);
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = (int) $row->getAttribute('data-recipient-id');
                    $this->assertFalse($row->hasAttribute('hidden'));
                }
                $this->assertEqualsCanonicalizing($expectedIds, $ids);
            }
        }
        foreach ([$officer->id => false, $inactive->id => true, $public->id => true] as $id => $disabled) {
            $checkbox = $xpath->query('//input[@name="recipient_ids[]" and @value="'.$id.'"]')->item(0);
            $this->assertNotNull($checkbox);
            $this->assertSame($disabled, $checkbox->hasAttribute('disabled'));
            $this->assertSame(! $disabled, $checkbox->hasAttribute('checked'));
        }
        Http::assertNothingSent();
    }

    public function test_deleting_an_account_preserves_report_history_and_removes_its_subscription(): void
    {
        $resident = $this->resident();
        $this->actingAs($resident)->post(route('public.incident-reports.store'), $this->submission())->assertRedirect();
        $report = PublicIncidentReport::sole();
        $resident->delete();
        $this->assertNull($report->fresh()->submitted_by);
        $this->assertDatabaseCount('public_incident_reports', 1);
        $this->assertDatabaseCount('public_incident_report_events', 1);
        $this->assertDatabaseCount('sms_recipients', 0);
    }

    public function test_flood_alerts_target_only_matching_active_subscribers_and_send_once(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['ok' => true])]);
        $resident = $this->resident();
        $this->resident('+639171234568', 'Plainview');
        $this->resident('+639171234569', overrides: ['receive_flood_alerts' => false]);
        $this->resident('+639171234570', overrides: ['is_active' => false]);
        $record = FloodTrainingRecord::create([
            'barangay' => 'Hulo', 'observed_at' => now(), 'month' => 10,
            'flood_status' => 'Active', 'flood_level_code' => 'B', 'risk_level' => 'Medium',
        ]);
        $service = app(FloodIncidentAlertService::class);
        $this->assertSame(1, $service->sendCreatedAlert($record, null)['sent']);
        $this->assertSame(1, $service->sendCreatedAlert($record, null)['skipped']);
        $this->assertDatabaseHas('sms_logs', ['sms_recipient_id' => $resident->smsRecipient->id,
            'flood_training_record_id' => $record->id, 'status' => 'sent']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(data_get($request->data(), 'textMessage.text'), 'Brgy: Hulo'));
        $record->update(['flood_status' => 'Subsided']);
        $this->assertSame(0, $service->sendCreatedAlert($record, null)['eligible']);
    }

    public function test_fire_alerts_include_matching_resident_subscribers(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['ok' => true])]);
        $resident = $this->resident();
        $this->resident('+639171234568', 'Plainview');
        $this->resident('+639171234569', overrides: ['receive_fire_alerts' => false]);
        $this->resident('+639171234570', overrides: ['is_active' => false]);
        $incident = FireIncident::create([
            'incident_number' => 'FI-RESIDENT-TEST', 'barangay_id' => $resident->barangay_id,
            'incident_type' => 'Residential fire', 'location' => 'Test street',
            'severity' => 'Minor', 'status' => 'Reported', 'reported_at' => now(),
            'latitude' => 14.5794, 'longitude' => 121.0359,
        ]);
        $result = app(FireIncidentAlertService::class)->sendCreatedAlert($incident, null);
        $this->assertSame(1, $result['sent']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_logs', ['sms_recipient_id' => $resident->smsRecipient->id,
            'fire_incident_id' => $incident->id, 'status' => 'sent']);
    }

    public function test_flood_gateway_failure_is_logged_without_losing_the_record(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['error' => 'offline'], 503)]);
        $this->resident();
        $record = FloodTrainingRecord::create(['barangay' => 'Hulo', 'observed_at' => now(),
            'month' => 10, 'flood_status' => 'Active', 'flood_level_code' => 'A', 'risk_level' => 'Low']);
        $this->assertSame(1, app(FloodIncidentAlertService::class)->sendCreatedAlert($record, null)['failed']);
        $this->assertDatabaseHas('sms_logs', ['flood_training_record_id' => $record->id, 'status' => 'failed']);
        $this->assertDatabaseHas('flood_training_records', ['id' => $record->id]);
    }
}
