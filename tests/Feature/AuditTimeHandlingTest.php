<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditTimeHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fire_times_round_trip_as_manila_time_without_changing_the_stored_instant(): void
    {
        $this->withoutVite();
        Http::fake(); // Never send an actual incident notification during a test.
        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
        $payload = [
            'barangay_id' => $barangay->id, 'incident_type' => 'Residential Fire',
            'location' => 'Test street', 'latitude' => 14.5794, 'longitude' => 121.0359,
            'severity' => 'Minor', 'status' => 'Reported',
            'reported_at' => '2026-10-07T01:12', 'responded_at' => '2026-10-07T01:20',
            'resolved_at' => null,
        ];
        $this->actingAs($user)->post(route('fire-incidents.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $incident = FireIncident::sole();
        $this->assertSame('2026-10-06 17:12:00', $incident->reported_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-06 17:20:00', $incident->responded_at->format('Y-m-d H:i:s'));
        $this->get(route('fire-incidents.edit', $incident))->assertOk()->assertSee('value="2026-10-07T01:12"', false);
        $this->get(route('fire-incidents.show', $incident))->assertOk()->assertSee('October 7, 2026 1:12 AM');
        $this->get(route('gis.data'))->assertOk()->assertJsonPath('incidents.0.reported_at', '2026-10-07T01:12:00+08:00');
        $this->put(route('fire-incidents.update', $incident), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-10-06 17:12:00', $incident->fresh()->reported_at->format('Y-m-d H:i:s'));
    }

    public function test_logout_removes_the_user_from_recently_online_tracking(): void
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => 'administrator', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'last_seen_at' => now()]);
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_seen_at);
    }
}
