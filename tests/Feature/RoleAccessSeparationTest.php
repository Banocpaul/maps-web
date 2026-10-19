<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Permission, PublicAdvisory, Role, User};
use App\Support\RoleAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoleAccessSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staff(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => str($slug)->headline(), 'is_active' => true]);
        if ($slug === 'operations-officer') {
            $role->permissions()->sync(Permission::whereIn('slug', RoleAccess::permissions($slug))->pluck('id'));
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_admin_cannot_open_operational_pages_even_with_legacy_grants(): void
    {
        $admin = $this->staff('administrator');
        $admin->role->permissions()->sync(Permission::pluck('id'));
        $this->actingAs($admin);
        foreach (['fire-incidents.index', 'fire-incidents.create', 'fire-hydrants.index',
            'prediction.index', 'prediction.history.index', 'flood-operation.index', 'flood-history.index',
            'flood-dataset.index', 'gis.index', 'gis.data', 'sms.index', 'public-submissions.index',
            'advisories.index', 'incident-analytics.index', 'incident-comparison.index',
            'incident-archives.index', 'incident-archives.analytics', 'operational-records.index',
            'operational-records.export', 'operational-records.report-builder', 'operational-records.archives'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        foreach (['fire-incidents.store', 'flood-dataset.store', 'operational-records.flood.store',
            'prediction.citywide', 'flood-operation.simulate', 'public-submissions.settings',
            'incident-analytics.sla.update', 'advisories.store'] as $name) {
            $this->post(route($name), [])->assertForbidden();
        }
        $this->get(route('dashboard'))->assertOk()->assertDontSee('SMS Center')
            ->assertDontSee('Incident Archives')->assertSee('User Management')->assertSee('System Analytics');
        $this->get(route('system-analytics.index'))->assertOk()->assertDontSee('Annual incident comparison');
        $this->get(route('users.index'))->assertOk();
        $this->get(route('admin.backups.index'))->assertOk();
        $this->assertFalse($admin->hasPermission('unknown.permission'));
        Http::assertNothingSent();
    }

    public function test_manager_and_legacy_officer_can_oversee_operations_but_not_system_administration(): void
    {
        foreach (['operations-manager', 'operations-officer'] as $slug) {
            $user = $this->staff($slug);
            $this->actingAs($user);
            foreach (['dashboard', 'fire-incidents.index', 'fire-incidents.create', 'fire-hydrants.index',
                'flood-operation.index', 'prediction.history.index', 'gis.index', 'gis.data',
                'sms.index', 'public-submissions.index', 'advisories.index', 'advisories.create',
                'incident-comparison.index', 'incident-archives.index', 'incident-archives.analytics',
                'operational-records.index'] as $name) {
                $this->get(route($name))->assertOk();
            }
            foreach (['users.index', 'activity-logs.index', 'system-analytics.index', 'admin.backups.index'] as $name) {
                $this->get(route($name))->assertForbidden();
            }
            foreach (['fire.delete', 'flood.delete', 'public-submissions.approve', 'sms.send',
                'sms.automation.manage', 'records.export', 'prediction.run', 'advisories.edit'] as $permission) {
                $this->assertTrue($user->hasPermission($permission), $slug.': '.$permission);
            }
        }
    }

    public function test_hazard_roles_cannot_cross_departments_even_if_wrong_permissions_were_granted(): void
    {
        foreach (['fire-responder', 'flood-analyst'] as $slug) {
            $user = $this->staff($slug);
            $user->role->permissions()->sync(Permission::pluck('id'));
            $this->actingAs($user);
            $denied = $slug === 'fire-responder'
                ? ['flood-operation.index', 'prediction.index', 'flood-history.index']
                : ['fire-incidents.index', 'fire-incidents.create', 'fire-hydrants.index'];
            foreach ([...$denied, 'users.index', 'sms.index', 'incident-archives.index', 'admin.backups.index'] as $name) {
                $this->get(route($name))->assertForbidden();
            }
            if ($slug === 'fire-responder') {
                $this->get(route('gis.data', ['hazard' => 'flood']))->assertForbidden();
            }
            // Flood analyst's shared map always returns only its own hazard.
            if ($slug === 'flood-analyst') {
                $this->getJson(route('gis.data', ['hazard' => 'fire']))->assertJsonMissingPath('incidents')->assertJsonStructure(['floods']);
            }
        }
    }

    public function test_system_viewer_has_a_working_dashboard_and_no_mutation_controls(): void
    {
        $viewer = $this->staff('system-viewer');
        $viewer->role->permissions()->sync(Permission::pluck('id'));
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertSee('read-only')
            ->assertSee('Fire Incidents')->assertSee('Prediction History')->assertDontSee('SMS Center');
        $this->get(route('flood-operation.index'))->assertOk()->assertDontSee('id="rainfall-scenario-button"', false)
            ->assertDontSee('id="simulation-form"', false);
        foreach (['fire-incidents.store', 'flood-dataset.store', 'prediction.citywide', 'flood-operation.simulate',
            'incident-analytics.sla.update', 'advisories.store', 'prediction.weather.refresh'] as $name) {
            $this->post(route($name), [])->assertForbidden();
        }
        foreach (['public-submissions.index', 'sms.index', 'users.index', 'admin.backups.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_residents_cannot_use_internal_roles_even_with_accidental_grants(): void
    {
        $user = $this->staff('public-resident');
        $user->role->permissions()->sync(Permission::pluck('id'));
        $this->actingAs($user);
        foreach (['dashboard', 'fire-incidents.index', 'prediction.history.index', 'gis.data', 'public-submissions.index', 'users.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_admin_logs_exports_and_analytics_exclude_operational_details(): void
    {
        ActivityLog::create(['module' => 'authentication', 'action' => 'login', 'description' => 'Visible account event']);
        ActivityLog::create(['module' => 'fire-incidents', 'action' => 'record_created', 'description' => 'PRIVATE FIRE DETAIL']);
        ActivityLog::create(['module' => 'sms', 'action' => 'send_sms', 'description' => 'PRIVATE ALERT DETAIL']);
        $this->actingAs($this->staff('administrator'));
        foreach (['activity-logs.index', 'system-analytics.index'] as $name) {
            $this->get(route($name))->assertOk()->assertDontSee('PRIVATE FIRE DETAIL')->assertDontSee('PRIVATE ALERT DETAIL');
        }
        $this->get(route('activity-logs.index', ['module' => 'fire-incidents']))->assertOk()->assertViewHas('logs', fn ($logs) => $logs->isEmpty());
        $body = $this->get(route('activity-logs.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Visible account event', $body);
        $this->assertStringNotContainsString('PRIVATE FIRE DETAIL', $body);
        $this->assertStringNotContainsString('PRIVATE ALERT DETAIL', $body);
        $data = $this->get(route('system-analytics.index'))->assertOk()->viewData('adminDashboard');
        $this->assertNotContains('Fire Incidents', $data['module_activity']['labels']);
        $this->assertNotContains('Sms', $data['module_activity']['labels']);
    }

    public function test_manager_can_manage_staff_advisories_and_specialists_stay_in_scope(): void
    {
        $owner = $this->staff('fire-responder');
        $advisory = PublicAdvisory::create(['user_id' => $owner->id, 'advisory_date' => today(),
            'type' => 'fire', 'subject' => 'Fire notice', 'message' => 'Staff notice']);
        $manager = $this->staff('operations-manager');
        $this->actingAs($manager)->get(route('advisories.edit', $advisory))->assertOk();
        $this->put(route('advisories.update', $advisory), ['advisory_date' => today()->toDateString(),
            'type' => 'fire', 'subject' => 'Reviewed notice', 'message' => 'Reviewed by operations'])
            ->assertRedirect(route('advisories.index'));
        $this->assertSame('Reviewed notice', $advisory->fresh()->subject);
        $this->actingAs($this->staff('flood-analyst'))->get(route('advisories.edit', $advisory))->assertForbidden();
        $this->actingAs($owner)->post(route('advisories.store'), ['advisory_date' => today()->toDateString(),
            'type' => 'flood', 'subject' => 'Wrong hazard', 'message' => 'Wrong department'])->assertSessionHasErrors('type');
        $this->actingAs($manager)->delete(route('advisories.destroy', $advisory))->assertRedirect(route('advisories.index'));
        $this->assertDatabaseMissing('public_advisories', ['id' => $advisory->id]);
    }

    public function test_migration_repairs_existing_grants_without_reactivating_disabled_accounts(): void
    {
        $admin = Role::where('slug', 'administrator')->firstOrFail();
        $admin->permissions()->sync(Permission::pluck('id'));
        $manager = Role::where('slug', 'operations-manager')->firstOrFail();
        $manager->permissions()->detach();
        $manager->update(['is_active' => false]);
        Permission::where('slug', 'fire.edit')->update(['is_active' => false]);
        $migration = require database_path('migrations/2026_10_19_120000_separate_administration_and_operations.php');
        $migration->up();
        $migration->up();
        $this->assertEqualsCanonicalizing(RoleAccess::permissions('administrator'), $admin->permissions()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(RoleAccess::permissions('operations-manager'), $manager->permissions()->pluck('slug')->all());
        $this->assertFalse($manager->fresh()->is_active);
        $this->assertFalse(Permission::where('slug', 'fire.edit')->first()->is_active);
    }
}
