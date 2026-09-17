<?php

namespace Tests\Feature;

use App\Models\DatabaseBackup;
use App\Models\Role;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DatabaseBackupAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_backup_and_recovery_page(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Backup & Recovery', false)
            ->assertSee('Create Manual Backup');
    }

    public function test_operations_manager_cannot_open_backup_and_recovery_page(): void
    {
        $operationsManager = $this->userWithRole('operations-manager');

        $this->actingAs($operationsManager)
            ->get(route('admin.backups.index'))
            ->assertForbidden();
    }

    public function test_administrator_password_is_required_to_create_backup(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)
            ->post(route('admin.backups.store'), [
                'current_password' => 'incorrect-password',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_administrator_can_request_manual_backup(): void
    {
        $administrator = $this->userWithRole('administrator');
        $backup = DatabaseBackup::create([
            'uuid' => fake()->uuid(),
            'type' => 'manual',
            'status' => 'completed',
            'disk' => 'backups',
            'path' => 'database-backups/test.sql.gz.enc',
            'filename' => 'test.sql.gz.enc',
            'size_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'database_name' => 'maps_system',
            'created_by' => $administrator->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('create')
            ->once()
            ->with('manual', $administrator->id)
            ->andReturn($backup);
        $this->app->instance(DatabaseBackupService::class, $service);

        $this->actingAs($administrator)
            ->post(route('admin.backups.store'), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
