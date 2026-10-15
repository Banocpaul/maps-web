<?php

namespace Tests\Feature;

use App\Models\FloodIncidentRecord;
use App\Models\FloodTrainingRecord;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloodHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(): User
    {
        $role = Role::firstOrCreate(['slug' => 'flood-analyst'], ['name' => 'Flood analyst', 'is_active' => true]);
        foreach (['prediction.view', 'flood.edit'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'flood', 'is_active' => true]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function observation(array $overrides = []): FloodTrainingRecord
    {
        return FloodTrainingRecord::create(array_replace([
            'barangay' => 'Plainview', 'location_name' => 'History test street', 'observed_at' => '2026-10-12 16:30:00',
            'month' => 10, 'flood_level_code' => 'B', 'flood_status' => 'Active', 'risk_level' => 'Medium',
        ], $overrides));
    }

    public function test_history_has_all_imported_rows_with_server_pagination_and_source_labels(): void
    {
        $this->actingAs($this->staff())->getJson(route('flood-history.index', ['tab' => 'historical']))
            ->assertOk()->assertJsonPath('counts.historical', 6874)->assertJsonPath('records.total', 6874)
            ->assertJsonCount(25, 'records.data')->assertJsonPath('records.per_page', 25)
            ->assertJsonPath('records.data.0.source', 'Historical dataset')->assertJsonPath('records.data.0.origin', 'record');
        $this->get(route('flood-operation.index'))->assertOk()->assertSee('Active Flood Incidents')
            ->assertSee('Flood History &amp; Records', false)->assertSee('Historical Dataset');
    }

    public function test_marking_subsided_moves_existing_record_from_active_list_to_history(): void
    {
        $user = $this->staff(); $record = $this->observation();
        $this->actingAs($user)->getJson(route('flood-dataset.index', ['flood_status' => 'Active', 'scope' => 'active']))
            ->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('statistics.total', 1);
        $this->getJson(route('flood-history.index'))->assertOk()->assertJsonPath('records.total', 0);
        $this->postJson(route('flood-lifecycle.bulk'), [
            'ids' => [$record->id], 'action' => 'subside', 'change_observed_at' => '2026-10-13T08:00:00+08:00',
            'change_note' => 'Field team confirmed water has subsided.',
            'expected' => [$record->id => ['code' => 'B', 'status' => 'Active']],
        ])->assertOk();
        $this->getJson(route('flood-dataset.index', ['flood_status' => 'Active', 'scope' => 'active']))
            ->assertOk()->assertJsonPath('records.total', 0)->assertJsonPath('statistics.total', 0);
        $this->getJson(route('flood-history.index'))->assertOk()->assertJsonPath('records.total', 1)
            ->assertJsonPath('records.data.0.id', $record->id)->assertJsonPath('records.data.0.reference', 'OBS-'.$record->id)
            ->assertJsonPath('records.data.0.status', 'Subsided')->assertJsonPath('counts.historical', 6874);
        $this->getJson(route('flood-history.show', ['origin' => 'observation', 'id' => $record->id]))->assertOk()
            ->assertJsonPath('history.0.action', 'Subsided')->assertJsonPath('history.0.note', 'Field team confirmed water has subsided.');
        $this->assertDatabaseCount('flood_training_records', 1);
    }

    public function test_all_records_normalize_timezones_and_apply_barangay_level_year_and_date_filters(): void
    {
        $record = $this->observation();
        $this->observation(['barangay' => 'Hulo', 'flood_level_code' => 'A']);
        $this->actingAs($this->staff())->getJson(route('flood-history.index', [
            'tab' => 'all', 'search' => 'Plainview', 'level' => 'B', 'year' => 2026,
            'date_from' => '2026-10-13', 'date_to' => '2026-10-13',
        ]))->assertOk()->assertJsonPath('records.total', 1)->assertJsonPath('records.data.0.id', $record->id)
            ->assertJsonPath('records.data.0.start_at', '2026-10-13T00:30:00+08:00');
        $this->getJson(route('flood-history.index', ['tab' => 'all', 'date_to' => '2026-10-13']))->assertOk();
        $historic = FloodIncidentRecord::whereNotNull('source_file_hash')->firstOrFail();
        $this->getJson(route('flood-history.show', ['origin' => 'record', 'id' => $historic->id]))->assertOk()
            ->assertJsonPath('record.source', 'Historical dataset')
            ->assertJsonPath('record.start_at', \Carbon\Carbon::parse($historic->flood_start_datetime, 'Asia/Manila')->toIso8601String());
    }

    public function test_history_excludes_deleted_records_and_rejects_invalid_filters(): void
    {
        $record = $this->observation(['flood_status' => 'Subsided']); $record->delete();
        $this->actingAs($this->staff())->getJson(route('flood-history.index'))->assertOk()->assertJsonPath('records.total', 0);
        $this->getJson(route('flood-history.show', ['origin' => 'observation', 'id' => $record->id]))->assertNotFound();
        $this->getJson(route('flood-history.index', ['tab' => 'invalid']))->assertStatus(422);
        $this->getJson(route('flood-history.index', ['date_from' => '2026-10-14', 'date_to' => '2026-10-13']))->assertStatus(422);
    }

    public function test_history_is_not_public_and_requires_prediction_view_permission(): void
    {
        $this->get(route('flood-history.index'))->assertRedirect(route('login'));
        $this->getJson(route('flood-history.index'))->assertUnauthorized();
        $user = $this->staff();
        $user->role->permissions()->detach(Permission::where('slug', 'prediction.view')->value('id'));
        $this->actingAs($user)->getJson(route('flood-history.index'))->assertForbidden();
        $this->getJson(route('flood-history.show', ['origin' => 'record', 'id' => 1]))->assertForbidden();
    }
}
