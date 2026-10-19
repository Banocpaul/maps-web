<?php
namespace Tests\Feature;
use App\Models\{Barangay, FireHydrant, Role, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class FireHydrantManagementTest extends TestCase {
    use RefreshDatabase;
    public function test_authorized_staff_can_open_create_save_and_edit_hydrant(): void {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $barangay = Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true]);
        foreach (['operations-manager', 'fire-responder'] as $slug) {
            $user = User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id'), 'is_active' => true]);
            $this->actingAs($user)->get(route('fire-hydrants.create'))->assertOk()->assertViewIs('fire.hydrants.create')->assertSee('Create Hydrant');
            $this->post(route('fire-hydrants.store'), ['barangay_id' => $barangay->id, 'hydrant_code' => 'TEST-'.$slug,
                'location' => 'Test street', 'status' => 'Active', 'latitude' => 14.58, 'longitude' => 121.03])
                ->assertRedirect(route('fire-hydrants.index'));
            $hydrant = FireHydrant::where('hydrant_code', 'TEST-'.$slug)->firstOrFail();
            $this->get(route('fire-hydrants.show', $hydrant))->assertOk();
            $this->get(route('fire-hydrants.edit', $hydrant))->assertOk();
        }
    }
    public function test_flood_analyst_cannot_create_hydrants(): void {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['role_id' => Role::where('slug', 'flood-analyst')->value('id'), 'is_active' => true]);
        $this->actingAs($user)->get(route('fire-hydrants.create'))->assertForbidden();
        $this->post(route('fire-hydrants.store'), [])->assertForbidden();
    }
}
