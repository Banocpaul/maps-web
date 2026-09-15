<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloodFieldObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_save_a_linear_level_d_flood_observation(): void
    {
        $role = Role::create(['name' => 'Operations Manager', 'slug' => 'operations-manager', 'is_active' => true]);
        $permission = Permission::create(['name' => 'Create Flood', 'slug' => 'flood.create', 'module' => 'flood', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $geometry = [
            'type' => 'LineString',
            'coordinates' => [[121.0359, 14.5794], [121.0370, 14.5800]],
        ];

        $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), [
                'barangay' => 'Hulo',
                'location_name' => 'San Francisco Street',
                'flood_level_code' => 'D',
                'flood_status' => 'Active',
                'geometry_type' => 'LineString',
                'geometry_geojson' => $geometry,
                'latitude' => 14.5797,
                'longitude' => 121.03645,
                'extent_length_m' => 137.25,
            ])
            ->assertCreated()
            ->assertJsonPath('record.flood_level_code', 'D')
            ->assertJsonPath('record.risk_level', 'High');

        $this->assertDatabaseHas('flood_training_records', [
            'barangay' => 'Hulo',
            'flood_level_code' => 'D',
            'flood_status' => 'Active',
            'risk_level' => 'High',
            'flood_depth_mm' => 1219.20,
            'geometry_type' => 'LineString',
            'include_in_training' => false,
        ]);
    }

    public function test_geometry_is_required_for_a_flood_observation(): void
    {
        $role = Role::create(['name' => 'Operations Manager', 'slug' => 'operations-manager', 'is_active' => true]);
        $permission = Permission::create(['name' => 'Create Flood', 'slug' => 'flood.create', 'module' => 'flood', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), [
                'barangay' => 'Hulo',
                'location_name' => 'San Francisco Street',
                'flood_level_code' => 'A',
                'flood_status' => 'Active',
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['geometry_type', 'geometry_geojson', 'latitude', 'longitude'],
            ]);
    }
}
