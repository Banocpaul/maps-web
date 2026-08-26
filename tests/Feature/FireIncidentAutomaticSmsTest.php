<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireHydrant;
use App\Models\FireIncident;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmsRecipient;
use App\Models\User;
use App\Services\FireIncidentAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FireIncidentAutomaticSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_fire_incident_sends_to_matching_barangay_fire_recipients(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['ok' => true], 200)]);

        $additionHills = $this->barangay('Addition Hills');
        $hulo = $this->barangay('Hulo');
        $matchingRecipient = $this->recipient($additionHills, true, true, '09171234567');
        $this->recipient($hulo, true, true, '09171234568');
        $this->recipient($additionHills, false, true, '09171234569');
        $this->recipient($additionHills, true, false, '09171234570');

        FireHydrant::create([
            'barangay_id' => $additionHills->id,
            'hydrant_code' => 'AH-HYD-NEAR',
            'location' => 'Martinez Street',
            'latitude' => 14.5853,
            'longitude' => 121.0349,
            'status' => 'Active',
        ]);
        FireHydrant::create([
            'barangay_id' => $hulo->id,
            'hydrant_code' => 'HULO-HYD-FAR',
            'location' => 'Hulo Main Road',
            'latitude' => 14.5700,
            'longitude' => 121.0250,
            'status' => 'Active',
        ]);

        $user = $this->userWithPermission('fire.create');

        $response = $this->actingAs($user)->post(route('fire-incidents.store'), [
            'barangay_id' => $additionHills->id,
            'incident_type' => 'Residential Fire',
            'location' => 'Martinez Street near San Rafael Street',
            'street' => 'Martinez Street',
            'corner' => 'San Rafael Street',
            'latitude' => 14.5852,
            'longitude' => 121.0348,
            'severity' => 'Major',
            'status' => 'Reported',
            'reported_at' => '2026-08-26 10:15:00',
        ]);

        $incident = FireIncident::query()->sole();
        $response->assertRedirect(route('fire-incidents.show', $incident));
        $response->assertSessionHas('success', fn (string $message): bool =>
            str_contains($message, '1 sent, 0 failed')
        );

        $this->assertDatabaseHas('sms_logs', [
            'fire_incident_id' => $incident->id,
            'sms_recipient_id' => $matchingRecipient->id,
            'source' => 'automatic',
            'alert_key' => 'fire_incident_created',
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('sms_logs', 1);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $message = data_get($request->data(), 'textMessage.text', '');

            return str_contains($message, 'Brgy: Addition Hills')
                && str_contains($message, 'Severity: Major')
                && str_contains($message, 'Street: Martinez Street / Corner: San Rafael Street')
                && str_contains($message, 'GPS: 14.5852000, 121.0348000')
                && str_contains($message, 'AH-HYD-NEAR');
        });
    }

    public function test_gateway_failure_does_not_roll_back_fire_incident(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['error' => 'offline'], 503)]);

        $barangay = $this->barangay('Addition Hills');
        $this->recipient($barangay, true, true, '09171234567');
        $user = $this->userWithPermission('fire.create');

        $this->actingAs($user)->post(route('fire-incidents.store'), [
            'barangay_id' => $barangay->id,
            'incident_type' => 'Residential Fire',
            'location' => 'Martinez Street',
            'latitude' => 14.5852,
            'longitude' => 121.0348,
            'severity' => 'Moderate',
            'status' => 'Reported',
            'reported_at' => '2026-08-26 10:15:00',
        ])->assertSessionHas('success', fn (string $message): bool =>
            str_contains($message, '0 sent, 1 failed')
        );

        $this->assertDatabaseCount('fire_incidents', 1);
        $this->assertDatabaseHas('sms_logs', [
            'source' => 'automatic',
            'status' => 'failed',
        ]);
    }

    public function test_duplicate_created_alert_is_not_sent_twice(): void
    {
        config(['services.sms_gateway.url' => 'https://sms.test/send']);
        Http::fake(['https://sms.test/send' => Http::response(['ok' => true], 200)]);

        $barangay = $this->barangay('Addition Hills');
        $this->recipient($barangay, true, true, '09171234567');
        $incident = FireIncident::create([
            'barangay_id' => $barangay->id,
            'incident_number' => 'FI-2026-9999',
            'incident_type' => 'Residential Fire',
            'location' => 'Martinez Street',
            'street' => 'Martinez Street',
            'latitude' => 14.5852,
            'longitude' => 121.0348,
            'severity' => 'Major',
            'status' => 'Reported',
            'reported_at' => now(),
        ]);

        $service = app(FireIncidentAlertService::class);
        $first = $service->sendCreatedAlert($incident, null);
        $second = $service->sendCreatedAlert($incident, null);

        $this->assertSame(1, $first['sent']);
        $this->assertSame(1, $second['skipped']);
        $this->assertDatabaseCount('sms_logs', 1);
        Http::assertSentCount(1);
    }

    private function barangay(string $name): Barangay
    {
        return Barangay::create([
            'name' => $name,
            'district' => 1,
            'is_active' => true,
        ]);
    }

    private function recipient(
        Barangay $barangay,
        bool $receiveFireAlerts,
        bool $active,
        string $phoneNumber
    ): SmsRecipient {
        return SmsRecipient::create([
            'full_name' => $barangay->name . ' Fire Recipient',
            'phone_number' => $phoneNumber,
            'office_or_barangay' => $barangay->name,
            'barangay_id' => $barangay->id,
            'receive_flood_alerts' => false,
            'receive_fire_alerts' => $receiveFireAlerts,
            'receive_general_alerts' => false,
            'is_active' => $active,
        ]);
    }

    private function userWithPermission(string $permissionSlug): User
    {
        $role = Role::create([
            'name' => 'Operations Manager',
            'slug' => 'operations-manager',
            'is_active' => true,
        ]);
        $permission = Permission::create([
            'name' => 'Create Fire Incidents',
            'slug' => $permissionSlug,
            'module' => 'fire',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
