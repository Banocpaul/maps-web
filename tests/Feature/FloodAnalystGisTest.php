<?php

namespace Tests\Feature;

use App\Models\FloodTrainingRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloodAnalystGisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_analyst_opens_an_internal_flood_map_from_the_shared_gis_route(): void
    {
        $this->actingAs($this->operator('flood-analyst'))
            ->get(route('gis.index'))->assertOk()->assertViewIs('gis.flood')
            ->assertSee('Flood GIS Mapping')->assertSee('flood-gis-map', false)
            ->assertSee('sidebar-overlay', false)
            ->assertSee(route('gis.data'), false)
            ->assertDontSee('Total Hydrants')->assertDontSee('Nearest active hydrant')
            ->assertDontSee('Public Active Flood')->assertDontSee(route('public.flood-map'), false);
    }

    public function test_analyst_data_contains_only_valid_active_floods_with_manila_times(): void
    {
        $valid = $this->flood();
        $this->flood(['flood_status' => 'Subsided']);
        $deleted = $this->flood();
        $deleted->delete();
        $this->flood(['geometry_geojson' => null]);
        $this->flood(['geometry_geojson' => ['type' => 'LineString', 'coordinates' => [[181, 14.58], [121, 14.59]]]]);
        $this->flood(['geometry_geojson' => ['type' => 'LineString', 'coordinates' => [['bad', 14.58], [121, 14.59]]]]);

        $response = $this->actingAs($this->operator('flood-analyst'))->get(route('gis.data'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'floods')->assertJsonPath('floods.0.id', $valid->id)
            ->assertJsonPath('floods.0.observed_at', '2026-10-07T01:12:00+08:00')
            ->assertJsonPath('statistics.active_floods', 4)
            ->assertJsonPath('statistics.unmapped_floods', 3)
            ->assertJsonPath('statistics.mapped_floods', 1)
            ->assertJsonPath('statistics.extent_length_m', 123.4);
        $this->assertArrayNotHasKey('hydrants', $response->json());
        $this->assertArrayNotHasKey('incidents', $response->json());
        $this->assertSame('2026-10-06 17:12:00', $valid->fresh()->observed_at->format('Y-m-d H:i:s'));
    }

    public function test_analyst_cannot_fetch_the_fire_response_assistant(): void
    {
        $this->actingAs($this->operator('flood-analyst'))
            ->getJson(route('gis.nearest-hydrants', ['latitude' => 14.58, 'longitude' => 121.03]))
            ->assertForbidden();
    }

    public function test_fire_and_operations_users_keep_their_existing_gis_page_and_data(): void
    {
        foreach (['fire-responder', 'operations-manager'] as $role) {
            $this->actingAs($this->operator($role))->get(route('gis.index'))
                ->assertOk()->assertViewIs('gis.index')->assertSee('Total Hydrants');
            $response = $this->get(route('gis.data'))->assertOk();
            $this->assertArrayHasKey('hydrants', $response->json());
            $this->assertArrayHasKey('incidents', $response->json());
            $this->assertArrayNotHasKey('floods', $response->json());
        }
    }

    public function test_gis_still_requires_login_and_permission(): void
    {
        $this->get(route('gis.index'))->assertRedirect(route('login'));
        $this->get(route('gis.data'))->assertRedirect(route('login'));
        $this->actingAs($this->operator('flood-analyst', false));
        $this->get(route('gis.index'))->assertForbidden();
        $this->getJson(route('gis.data'))->assertForbidden();
    }

    private function operator(string $slug, bool $grantPermission = true): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
        if ($grantPermission) {
            $permission = Permission::firstOrCreate(['slug' => 'gis.view'], [
                'name' => 'View GIS', 'module' => 'gis', 'is_active' => true,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function flood(array $attributes = []): FloodTrainingRecord
    {
        return FloodTrainingRecord::create(array_replace([
            'barangay' => 'Hulo', 'location_name' => 'Test street',
            'observed_at' => '2026-10-06 17:12:00', 'month' => 10,
            'risk_level' => 'Medium', 'flood_level_code' => 'B', 'flood_status' => 'Active',
            'geometry_type' => 'LineString', 'extent_length_m' => 123.4,
            'geometry_geojson' => ['type' => 'LineString', 'coordinates' => [[121.03, 14.58], [121.031, 14.581]]],
        ], $attributes));
    }
}
