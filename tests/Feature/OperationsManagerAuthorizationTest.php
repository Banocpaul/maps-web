<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationsManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_open_operational_records(): void
    {
        $user = $this->userWithPermissions('operations-manager', [
            'records.view',
        ]);

        $this->actingAs($user)
            ->get(route('operational-records.index', [
                'dataset' => 'fire-incidents',
            ]))
            ->assertOk()
            ->assertSee('Operational Database Records');
    }

    public function test_user_without_records_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions('system-viewer', []);

        $this->actingAs($user)
            ->get(route('operational-records.index', [
                'dataset' => 'fire-incidents',
            ]))
            ->assertForbidden();
    }

    public function test_operations_manager_can_build_flood_pivot_report(): void
    {
        Schema::create('flood_analytics_dataset', function (Blueprint $table): void {
            $table->id();
            $table->string('barangay');
            $table->unsignedSmallInteger('year');
            $table->string('risk_level');
            $table->softDeletes();
        });

        DB::table('flood_analytics_dataset')->insert([
            ['barangay' => 'Addition Hills', 'year' => 2025, 'risk_level' => 'High'],
            ['barangay' => 'Addition Hills', 'year' => 2025, 'risk_level' => 'High'],
            ['barangay' => 'Hulo', 'year' => 2025, 'risk_level' => 'Low'],
        ]);

        $user = $this->userWithPermissions(
            'operations-manager',
            ['records.view']
        );

        $this->actingAs($user)
            ->get(route('operational-records.report-builder'))
            ->assertOk()
            ->assertSee('Flood Report Builder')
            ->assertSee('Addition Hills')
            ->assertSee('Generated heatmap');
    }

    public function test_operations_manager_can_open_create_fire_incident_form(): void
    {
        $user = $this->userWithPermissions(
            'operations-manager',
            ['fire.create']
        );

        $this->actingAs($user)
            ->get(route('fire-incidents.create'))
            ->assertOk();
    }

    public function test_fire_view_permission_does_not_allow_deletion(): void
    {
        $user = $this->userWithPermissions(
            'system-viewer',
            ['fire.view']
        );

        $incident = $this->fireIncident();

        $this->actingAs($user)
            ->delete(route('fire-incidents.destroy', $incident))
            ->assertForbidden();

        $this->assertDatabaseHas('fire_incidents', [
            'id' => $incident->id,
        ]);
    }

    public function test_operations_manager_can_soft_delete_fire_incident(): void
    {
        $user = $this->userWithPermissions(
            'operations-manager',
            ['fire.delete']
        );

        $incident = $this->fireIncident();

        $this->actingAs($user)
            ->delete(route('fire-incidents.destroy', $incident))
            ->assertRedirect(route('fire-incidents.index'));

        $this->assertSoftDeleted('fire_incidents', [
            'id' => $incident->id,
        ]);
    }

    private function userWithPermissions(
        string $roleSlug,
        array $permissionSlugs
    ): User {
        $role = Role::create([
            'name' => str($roleSlug)->headline()->toString(),
            'slug' => $roleSlug,
            'is_active' => true,
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => str($permissionSlug)
                        ->headline()
                        ->toString(),
                    'module' => str($permissionSlug)
                        ->before('.')
                        ->toString(),
                    'is_active' => true,
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function fireIncident(): FireIncident
    {
        $barangay = Barangay::create([
            'name' => 'Test Barangay',
            'district' => 1,
            'is_active' => true,
        ]);

        return FireIncident::create([
            'barangay_id' => $barangay->id,
            'incident_number' => 'TEST-001',
            'incident_type' => 'Structural Fire',
            'location' => 'Test Location',
            'severity' => 'Minor',
            'status' => 'Responding',
            'reported_at' => now(),
        ]);
    }
}
