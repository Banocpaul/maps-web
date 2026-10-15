<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $name, string $slug): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function administrator(): User
    {
        $role = $this->role('Administrator', 'administrator');

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
            'approved_at' => now(),
        ]);
    }

    private function updatePayload(User $user, Role $role): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $role->id,
            'is_active' => 1,
        ];
    }

    public function test_administrator_can_open_and_update_existing_user(): void
    {
        $admin = $this->administrator();
        $staffRole = $this->role('Flood Analyst', 'flood-analyst');
        $user = User::factory()->create([
            'role_id' => $staffRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('Edit User')
            ->assertSee($user->email);

        $payload = $this->updatePayload($user, $staffRole);
        $payload['name'] = 'Updated Account Name';
        $payload['email'] = 'updated-account@example.com';

        $this->put(route('users.update', $user), $payload)
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertSame('Updated Account Name', $user->name);
        $this->assertSame('updated-account@example.com', $user->email);
        $this->assertSame($staffRole->id, $user->role_id);
        $this->assertNull($user->role_changed_at);
    }

    public function test_role_change_records_timestamp(): void
    {
        $admin = $this->administrator();
        $firstRole = $this->role('Fire Responder', 'fire-responder');
        $secondRole = $this->role('Operations Manager', 'operations-manager');

        $user = User::factory()->create([
            'role_id' => $firstRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(
                route('users.update', $user),
                $this->updatePayload($user, $secondRole)
            )
            ->assertRedirect(route('users.index'));

        $user->refresh();

        $this->assertSame($secondRole->id, $user->role_id);
        $this->assertNotNull($user->role_changed_at);
    }

    public function test_role_cannot_change_again_within_three_months(): void
    {
        $admin = $this->administrator();
        $firstRole = $this->role('Fire Responder', 'fire-responder');
        $secondRole = $this->role('Operations Manager', 'operations-manager');

        $user = User::factory()->create([
            'role_id' => $firstRole->id,
            'is_active' => true,
            'role_changed_at' => now()->subMonth(),
        ]);

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(
                route('users.update', $user),
                $this->updatePayload($user, $secondRole)
            )
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHasErrors('role_id');

        $this->assertSame(
            $firstRole->id,
            $user->fresh()->role_id
        );
    }

    public function test_role_can_change_after_three_months(): void
    {
        $admin = $this->administrator();
        $firstRole = $this->role('Fire Responder', 'fire-responder');
        $secondRole = $this->role('Operations Manager', 'operations-manager');

        $user = User::factory()->create([
            'role_id' => $firstRole->id,
            'is_active' => true,
            'role_changed_at' => now()->subMonths(4),
        ]);

        $this->actingAs($admin)
            ->put(
                route('users.update', $user),
                $this->updatePayload($user, $secondRole)
            )
            ->assertRedirect(route('users.index'));

        $this->assertSame(
            $secondRole->id,
            $user->fresh()->role_id
        );
    }

    public function test_administrator_account_role_is_protected(): void
    {
        $admin = $this->administrator();
        $staffRole = $this->role('Flood Analyst', 'flood-analyst');

        $this->actingAs($admin)
            ->from(route('users.edit', $admin))
            ->put(
                route('users.update', $admin),
                $this->updatePayload($admin, $staffRole)
            )
            ->assertRedirect(route('users.edit', $admin))
            ->assertSessionHasErrors('role_id');

        $admin->refresh();

        $this->assertTrue($admin->isAdministrator());
        $this->assertNull($admin->role_changed_at);
    }
    public function test_public_account_can_be_deactivated_reactivated_and_deleted_without_deleting_reports(): void
    {
        $admin = $this->administrator();
        $role = Role::firstOrCreate(['slug' => 'public-resident'], ['name' => 'Public Resident', 'is_active' => true]);
        $resident = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $report = \App\Models\PublicIncidentReport::create([
            'reference' => 'KEEP-REPORT', 'submission_token' => (string) \Illuminate\Support\Str::uuid(),
            'incident_type' => 'fire', 'latitude' => 14.58, 'longitude' => 121.03,
            'status' => 'Pending', 'submitted_by' => $resident->id,
        ]);
        $this->actingAs($admin)->patch(route('users.status', $resident), ['is_active' => false])->assertSessionHas('success');
        $this->assertFalse($resident->fresh()->is_active);
        $this->assertSame(1, $resident->fresh()->password_version);
        $this->patch(route('users.status', $resident), ['is_active' => true])->assertSessionHas('success');
        $this->assertTrue($resident->fresh()->is_active);
        $this->delete(route('users.destroy', $resident))->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $resident->id]);
        $this->assertDatabaseHas('public_incident_reports', ['id' => $report->id, 'submitted_by' => null]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'delete_user_account', 'subject_id' => $resident->id]);
    }

    public function test_suspended_resident_cannot_be_activated_without_review(): void
    {
        $admin = $this->administrator();
        $role = Role::firstOrCreate(['slug' => 'public-resident'], ['name' => 'Public Resident', 'is_active' => true]);
        $resident = User::factory()->create(['role_id' => $role->id, 'is_active' => false]);
        $resident->forceFill(['suspended_at' => now()])->save();
        $this->actingAs($admin)->patch(route('users.status', $resident), ['is_active' => true])->assertSessionHas('error');
        $this->assertFalse($resident->fresh()->is_active);
    }
    public function test_sms_directory_exposes_public_account_controls_to_administrator(): void
    {
        $admin = $this->administrator();
        $role = Role::firstOrCreate(['slug' => 'public-resident'], ['name' => 'Public Resident', 'is_active' => true]);
        $resident = User::factory()->create(['role_id' => $role->id, 'contact_number' => '+639171234567', 'is_active' => true, 'receive_flood_alerts' => false, 'receive_fire_alerts' => true]);
        app(\App\Services\ResidentSubscriptionService::class)->sync($resident);
        $this->actingAs($admin)->get(route('sms.index'))->assertOk()
            ->assertSee('Deactivate account')->assertSee('Delete account')
            ->assertSee(route('users.destroy', $resident), false);
    }
}
