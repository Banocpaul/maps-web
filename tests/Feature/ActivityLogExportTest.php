<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_export_filtered_audit_trail_to_excel(): void
    {
        $administrator = $this->userWithRoleAndPermission('administrator');

        ActivityLog::create([
            'user_id' => $administrator->id,
            'user_name' => $administrator->full_name,
            'role_name' => 'Administrator',
            'action' => 'restore_backup',
            'module' => 'admin',
            'description' => 'Restored a verified database backup.',
            'route_name' => 'admin.backups.restore',
            'http_method' => 'POST',
            'ip_address' => '127.0.0.1',
            'response_status' => 302,
        ]);

        ActivityLog::create([
            'user_name' => 'Fire Responder',
            'role_name' => 'Fire Responder',
            'action' => 'create',
            'module' => 'fire-incidents',
            'description' => 'Created a fire incident.',
            'response_status' => 302,
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('activity-logs.export', ['action' => 'restore_backup']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->assertDownload();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Restored a verified database backup.', $content);
        $this->assertStringContainsString('restore_backup', $content);
        $this->assertStringNotContainsString('Created a fire incident.', $content);
    }

    public function test_operations_manager_cannot_export_audit_trail(): void
    {
        $operationsManager = $this->userWithRoleAndPermission('operations-manager');

        $this->actingAs($operationsManager)
            ->get(route('activity-logs.export'))
            ->assertForbidden();
    }

    private function userWithRoleAndPermission(string $slug): User
    {
        $role = Role::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ]);

        $permission = Permission::create([
            'name' => 'View Activity Logs',
            'slug' => 'activity-logs.view',
            'module' => 'activity-logs',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
