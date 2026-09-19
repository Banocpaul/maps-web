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

    public function test_verified_backup_shows_restore_action(): void
    {
        $administrator = $this->userWithRole('administrator');
        $backup = $this->completedBackup($administrator, true);

        $this->actingAs($administrator)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('restore-'.$backup->uuid, false)
            ->assertSee('RESTORE-MAPS');
    }

    public function test_administrator_can_restore_verified_backup_with_safety_backup(): void
    {
        $administrator = $this->userWithRole('administrator');
        $backup = $this->completedBackup($administrator, true);
        $safetyBackup = $this->completedBackup($administrator, true, 'pre_restore');

        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('create')
            ->once()
            ->with('pre_restore', $administrator->id)
            ->andReturn($safetyBackup);
        $service->shouldReceive('restore')
            ->once()
            ->with(Mockery::on(fn (DatabaseBackup $value) => $value->is($backup)), $administrator->id);
        $service->shouldReceive('preserveRecordAfterRestore')
            ->once()
            ->with(Mockery::on(fn (DatabaseBackup $value) => $value->is($safetyBackup)), $administrator->id, false)
            ->andReturn($safetyBackup);
        $this->app->instance(DatabaseBackupService::class, $service);

        $this->actingAs($administrator)
            ->post(route('admin.backups.restore', $backup), [
                'current_password' => 'password',
                'confirmation' => 'RESTORE-MAPS',
            ])
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success');
    }

    public function test_unverified_backup_cannot_be_restored(): void
    {
        $administrator = $this->userWithRole('administrator');
        $backup = $this->completedBackup($administrator, false);

        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldNotReceive('create');
        $service->shouldNotReceive('restore');
        $this->app->instance(DatabaseBackupService::class, $service);

        $this->actingAs($administrator)
            ->post(route('admin.backups.restore', $backup), [
                'current_password' => 'password',
                'confirmation' => 'RESTORE-MAPS',
            ])
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('error');
    }

    public function test_restore_requires_exact_confirmation(): void
    {
        $administrator = $this->userWithRole('administrator');
        $backup = $this->completedBackup($administrator, true);

        $this->actingAs($administrator)
            ->post(route('admin.backups.restore', $backup), [
                'current_password' => 'password',
                'confirmation' => 'RESTORE',
            ])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_operations_manager_cannot_restore_backup(): void
    {
        $administrator = $this->userWithRole('administrator');
        $operationsManager = $this->userWithRole('operations-manager');
        $backup = $this->completedBackup($administrator, true);

        $this->actingAs($operationsManager)
            ->post(route('admin.backups.restore', $backup), [
                'current_password' => 'password',
                'confirmation' => 'RESTORE-MAPS',
            ])
            ->assertForbidden();
    }

    private function completedBackup(
        User $creator,
        bool $verified,
        string $type = 'manual'
    ): DatabaseBackup {
        return DatabaseBackup::create([
            'uuid' => fake()->uuid(),
            'type' => $type,
            'status' => 'completed',
            'disk' => 'backups',
            'path' => 'database-backups/'.fake()->uuid().'.sql.gz.enc',
            'filename' => fake()->uuid().'.sql.gz.enc',
            'size_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'database_name' => 'maps_system',
            'created_by' => $creator->id,
            'started_at' => now(),
            'completed_at' => now(),
            'verified_at' => $verified ? now() : null,
            'verified_by' => $verified ? $creator->id : null,
        ]);
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
