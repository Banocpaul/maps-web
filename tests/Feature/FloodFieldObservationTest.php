<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
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

    public function test_authorized_operator_can_mark_an_active_flood_as_subsided(): void
    {
        $role = Role::create(['name' => 'Operations Manager', 'slug' => 'operations-manager', 'is_active' => true]);
        $createPermission = Permission::create(['name' => 'Create Flood', 'slug' => 'flood.create', 'module' => 'flood', 'is_active' => true]);
        $editPermission = Permission::create(['name' => 'Edit Flood', 'slug' => 'flood.edit', 'module' => 'flood', 'is_active' => true]);
        $role->permissions()->attach([$createPermission->id, $editPermission->id]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $geometry = [
            'type' => 'LineString',
            'coordinates' => [[121.0359, 14.5794], [121.0370, 14.5800]],
        ];

        $createResponse = $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), [
                'barangay' => 'Hulo',
                'flood_level_code' => 'C',
                'geometry_type' => 'LineString',
                'geometry_geojson' => $geometry,
                'latitude' => 14.5797,
                'longitude' => 121.03645,
                'extent_length_m' => 137.25,
            ])
            ->assertCreated()
            ->assertJsonPath('record.flood_status', 'Active');

        $recordId = $createResponse->json('record.id');

        $this->putJson(route('flood-dataset.update', $recordId), [
            'barangay' => 'Hulo',
            'flood_level_code' => 'C',
            'flood_status' => 'Subsided',
            'geometry_type' => 'LineString',
            'geometry_geojson' => $geometry,
            'latitude' => 14.5797,
            'longitude' => 121.03645,
            'extent_length_m' => 137.25,
        ])
            ->assertOk()
            ->assertJsonPath('record.flood_status', 'Subsided');

        $this->assertDatabaseHas('flood_training_records', [
            'id' => $recordId,
            'flood_status' => 'Subsided',
        ]);
    }

    public function test_enriched_subsided_observation_can_be_approved_for_training(): void
    {
        $role = Role::create(['name' => 'Flood Analyst', 'slug' => 'flood-analyst', 'is_active' => true]);
        $permissions = collect([
            ['Create Flood', 'flood.create'],
            ['Edit Flood', 'flood.edit'],
            ['Manage Prediction Data', 'prediction.data.manage'],
        ])->map(fn (array $permission) => Permission::create([
            'name' => $permission[0],
            'slug' => $permission[1],
            'module' => 'flood',
            'is_active' => true,
        ]));
        $role->permissions()->attach($permissions->pluck('id'));
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        Barangay::create([
            'name' => 'Hulo',
            'district' => 1,
            'latitude' => 14.5794,
            'longitude' => 121.0359,
            'elevation_m' => 8,
            'nearest_waterway' => 'Pasig River',
            'distance_to_waterway_m' => 120,
            'drainage_index' => 0.55,
            'impervious_surface_ratio' => 0.82,
            'population_density_per_km2' => 28000,
            'historical_flood_count_5y' => 7,
            'is_active' => true,
        ]);

        $observedAt = now('Asia/Manila')->subHours(2);

        DailyWeatherSnapshot::create([
            'snapshot_date' => $observedAt->toDateString(),
            'source' => 'Open-Meteo',
            'weather_data' => [
                'rainfall_24h_mm' => 42.5,
                'rainfall_3d_mm' => 75.2,
                'rainfall_7d_mm' => 110.4,
                'avg_temp_mean_c' => 28.4,
                'avg_rh_pct' => 86.0,
                'avg_wind_speed' => 3.2,
            ],
            'fetched_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $geometry = [
            'type' => 'LineString',
            'coordinates' => [[121.0359, 14.5794], [121.0370, 14.5800]],
        ];

        $payload = [
            'observed_at' => $observedAt->toDateTimeString(),
            'barangay' => 'Hulo',
            'flood_level_code' => 'C',
            'geometry_type' => 'LineString',
            'geometry_geojson' => $geometry,
            'latitude' => 14.5797,
            'longitude' => 121.03645,
            'extent_length_m' => 137.25,
        ];

        $createResponse = $this->actingAs($user)
            ->postJson(route('flood-dataset.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('record.enrichment_status', 'Enriched')
            ->assertJsonPath('record.review_status', 'Pending');

        $recordId = $createResponse->json('record.id');

        $this->putJson(route('flood-dataset.update', $recordId), [
            ...$payload,
            'flood_status' => 'Subsided',
        ])
            ->assertOk()
            ->assertJsonPath('record.review_status', 'Ready for Review');

        $this->postJson(route('flood-dataset.review', $recordId), [
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('record.review_status', 'Approved')
            ->assertJsonPath('record.include_in_training', true);

        $this->assertDatabaseHas('flood_training_records', [
            'id' => $recordId,
            'enrichment_status' => 'Enriched',
            'review_status' => 'Approved',
            'include_in_training' => true,
            'rainfall_24h_mm' => 42.5,
            'historical_flood_count_5y' => 7,
        ]);
    }
}
