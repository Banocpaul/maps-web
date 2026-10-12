<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mockery;
use Tests\TestCase;

class AdministratorPredictionPermissionTest extends TestCase
{
    public function test_administrator_cannot_access_any_prediction_permission(): void
    {
        $user = $this->userWithRole('administrator');

        foreach (['prediction.view', 'prediction.run', 'prediction.review', 'prediction.data.manage'] as $permission) {
            $this->assertFalse($user->hasPermission($permission), $permission);
        }
    }

    public function test_administrator_keeps_system_management_permissions(): void
    {
        $user = $this->userWithRole('administrator');

        foreach (['dashboard.view', 'users.manage', 'activity-logs.view'] as $permission) {
            $this->assertTrue($user->hasPermission($permission), $permission);
        }
    }

    public function test_operational_role_still_uses_its_assigned_permissions(): void
    {
        $role = Mockery::mock(Role::class)->makePartial();
        $role->slug = 'flood-analyst';
        $role->is_active = true;

        $permissions = Mockery::mock(BelongsToMany::class);
        $permissions->shouldReceive('where')->with('slug', 'prediction.run')->once()->andReturnSelf();
        $permissions->shouldReceive('where')->with('is_active', true)->once()->andReturnSelf();
        $permissions->shouldReceive('exists')->once()->andReturnTrue();
        $role->shouldReceive('permissions')->once()->andReturn($permissions);

        $this->assertTrue($this->userWithRole('flood-analyst', $role)->hasPermission('prediction.run'));
    }

    private function userWithRole(string $slug, ?Role $role = null): User
    {
        $role ??= new Role;
        $role->slug = $slug;
        $role->is_active = true;

        $relationship = Mockery::mock(BelongsTo::class);
        $relationship->shouldReceive('first')->andReturn($role);

        $user = Mockery::mock(User::class)->makePartial();
        $user->is_active = true;
        $user->shouldReceive('role')->andReturn($relationship);

        return $user;
    }
}
