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
                'flood_level_code' => 'D',
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
            'location_name' => 'Hulo flood extent',
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
                'flood_level_code' => 'A',
            ])
            ->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['geometry_type', 'geometry_geojson', 'latitude', 'longitude'],
            ]);
    }

    public function test_flood_analyst_can_save_a_linear_flood_observation(): void
    {
        $role = Role::create(['name' => 'Flood Analyst', 'slug' => 'flood-analyst', 'is_active' => true]);
        $permission = Permission::create(['name' => 'Create Flood', 'slug' => 'flood.create', 'module' => 'flood', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), [
                'barangay' => 'Barangka Drive',
                'flood_level_code' => 'B',
                'geometry_type' => 'LineString',
                'geometry_geojson' => [
                    'type' => 'LineString',
                    'coordinates' => [[121.0359, 14.5794], [121.0368, 14.5801]],
                ],
                'latitude' => 14.57975,
                'longitude' => 121.03635,
                'extent_length_m' => 123.45,
            ])
            ->assertCreated()
            ->assertJsonPath('record.flood_level_code', 'B');

        $this->assertDatabaseHas('flood_training_records', [
            'barangay' => 'Barangka Drive',
            'flood_level_code' => 'B',
            'flood_status' => 'Active',
            'geometry_type' => 'LineString',
            'extent_length_m' => 123.45,
        ]);
    }

    public function test_point_geometry_is_rejected(): void
    {
        $role = Role::create(['name' => 'Flood Analyst', 'slug' => 'flood-analyst', 'is_active' => true]);
        $permission = Permission::create(['name' => 'Create Flood', 'slug' => 'flood.create', 'module' => 'flood', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), [
                'barangay' => 'Hulo',
                'flood_level_code' => 'A',
                'geometry_type' => 'Point',
                'geometry_geojson' => [
                    'type' => 'Point',
                    'coordinates' => [121.0359, 14.5794],
                ],
                'latitude' => 14.5794,
                'longitude' => 121.0359,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['geometry_type', 'geometry_geojson.type', 'extent_length_m']);
    }
}
