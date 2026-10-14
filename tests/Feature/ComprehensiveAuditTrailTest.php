<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComprehensiveAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_views_and_rejected_login_are_logged_without_credentials(): void
    {
        $this->get(route('login'))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'login', 'action' => 'view', 'response_status' => 200]);
        $this->post(route('login.attempt'), ['email' => 'missing@example.test', 'password' => 'DoNotStoreThis!'])
            ->assertSessionHasErrors('email');
        $log = ActivityLog::where('action', 'failed_login')->latest('id')->firstOrFail();
        $this->assertSame('Failed', $log->new_values['_audit']['outcome']);
        $this->assertStringNotContainsString('DoNotStoreThis!', json_encode($log->getAttributes()));
    }

    public function test_model_update_captures_actual_before_after_and_redacts_nested_secrets(): void
    {
        $role = Role::firstOrCreate(['slug' => 'public-resident'], ['name' => 'Public Resident', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'first_name' => 'Before']);
        $user->update(['first_name' => 'After', 'password' => Hash::make('DoNotStoreThis!')]);
        $log = ActivityLog::where('action', 'record_updated')->where('subject_type', User::class)
            ->where('subject_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('Before', $log->old_values['first_name']);
        $this->assertSame('After', $log->new_values['first_name']);
        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $safe = app(AuditRecorder::class)->redact(['settings' => ['api_key' => 'private', 'name' => 'Keep']]);
        $this->assertSame('[REDACTED]', $safe['settings']['api_key']);
        $this->assertSame('Keep', $safe['settings']['name']);
    }

    public function test_query_builder_updates_are_recorded_without_sql_bindings(): void
    {
        DB::table('incident_report_settings')->where('id', 1)->update(['threshold' => 12]);
        $log = ActivityLog::where('action', 'database_update')->where('subject_type', 'incident_report_settings')
            ->latest('id')->firstOrFail();
        $this->assertSame(['table' => 'incident_report_settings', 'operation' => 'update'], $log->new_values);
    }

    public function test_audit_entries_do_not_recursively_audit_themselves(): void
    {
        $before = ActivityLog::count();
        app(AuditRecorder::class)->record(['action' => 'test_event', 'module' => 'test', 'description' => 'Single entry']);
        $this->assertSame($before + 1, ActivityLog::count());
    }

    public function test_rolled_back_changes_do_not_survive_in_the_audit(): void
    {
        $before = ActivityLog::count();
        DB::beginTransaction();
        Role::create(['slug' => 'rolled-back-role', 'name' => 'Rollback', 'is_active' => true]);
        DB::rollBack();
        $this->assertSame($before, ActivityLog::count());
    }

    public function test_forbidden_audit_access_is_logged_once_as_a_failed_action(): void
    {
        $role = Role::firstOrCreate(['slug' => 'public-resident'], ['name' => 'Public Resident', 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'must_change_password' => false]);
        $before = ActivityLog::where('route_name', 'activity-logs.index')->where('response_status', 403)->count();
        $this->actingAs($user)->get(route('activity-logs.index'))->assertForbidden();
        $this->assertSame($before + 1, ActivityLog::where('route_name', 'activity-logs.index')
            ->where('response_status', 403)->count());
    }
}
