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
}
