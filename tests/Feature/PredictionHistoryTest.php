<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Permission;
use App\Models\PredictionExecution;
use App\Models\Role;
use App\Models\User;
use App\Services\FloodPredictionService;
use App\Services\LiveWeatherService;
use App\Services\PredictionStorageService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PredictionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
    }

    public function test_each_forecast_window_saves_a_distinct_complete_run_without_changing_ml_inputs(): void
    {
        $user = $this->staff();
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(6, 0));
        $weather = $this->weather();
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldReceive('getCurrentWeather')->times(3)->andReturn($weather));
        $this->mock(PredictionStorageService::class, fn ($mock) => $mock->shouldReceive('saveCitywide')->times(3)->andReturn(collect()));
        $this->mock(FloodPredictionService::class, function ($mock) use ($weather): void {
            foreach ([24, 48, 72] as $hours) {
                $mock->shouldReceive('predictCitywide')->once()->withArgs(function ($input) use ($hours, $weather): bool {
                    return $input['forecast_hours'] === $hours
                        && $input['weather_context'] === $weather
                        && $input['barangays'][0]['barangay'] === 'Hulo'
                        && ! isset($input['simulation']);
                })->andReturn($this->predictionResult($hours));
            }
        });

        foreach ([24, 48, 72] as $hours) {
            $this->actingAs($user)->post(route('prediction.citywide'), ['forecast_hours' => $hours])
                ->assertRedirect(route('prediction.history.show', PredictionExecution::latest('id')->first()));
            $run = PredictionExecution::latest('id')->first();
            $this->assertSame($this->predictionResult($hours), $run->result_snapshot);
            $this->assertSame($weather, $run->input_snapshot['weather_context']);
            $this->assertSame($hours, $run->forecast_hours);
            $this->assertSame('Completed', $run->status);
            $this->assertSame('Forecast', $run->kind);
            $this->assertSame($user->id, $run->requested_by_user_id);
            // Snapshot date differs from actual execution time; history uses the latter.
            $this->assertSame('2026-10-10 06:00:00', $run->requested_at->format('Y-m-d H:i:s'));
            $this->assertNotNull($run->completed_at);
        }
        $this->assertDatabaseCount('prediction_executions', 3);
    }

    public function test_saved_results_survive_a_legacy_storage_error(): void
    {
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldReceive('getCurrentWeather')->andReturn($this->weather()));
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldReceive('predictCitywide')->andReturn($this->predictionResult(48)));
        $this->mock(PredictionStorageService::class, fn ($mock) => $mock->shouldReceive('saveCitywide')->andThrow(new RuntimeException('Legacy schema mismatch')));

        $this->actingAs($this->staff())->post(route('prediction.citywide'), ['forecast_hours' => 48])->assertRedirect();
        $run = PredictionExecution::first();
        $this->assertSame('Completed', $run->status);
        $this->assertSame($this->predictionResult(48), $run->result_snapshot);
    }

    public function test_failed_api_attempt_is_saved_without_inventing_results(): void
    {
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldReceive('getCurrentWeather')->andReturn($this->weather()));
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldReceive('predictCitywide')->andThrow(new RuntimeException('ML service unavailable')));
        $this->mock(PredictionStorageService::class, fn ($mock) => $mock->shouldNotReceive('saveCitywide'));
        $this->actingAs($this->staff())->post(route('prediction.citywide'), ['forecast_hours' => 72])
            ->assertRedirect(route('prediction.index'));
        $run = PredictionExecution::first();
        $this->assertSame('Failed', $run->status);
        $this->assertSame(72, $run->forecast_hours);
        $this->assertSame('ML service unavailable', $run->error_message);
        $this->assertNull($run->result_snapshot);
        $this->assertNotNull($run->input_snapshot);
        $this->get(route('prediction.history.show', $run))->assertOk()->assertSee('Run failed:');
    }

    public function test_weather_failure_is_recorded_before_inference(): void
    {
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldReceive('getCurrentWeather')->andThrow(new RuntimeException('Weather unavailable')));
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictCitywide'));
        $this->actingAs($this->staff())->post(route('prediction.citywide'), ['forecast_hours' => 24])->assertRedirect();
        $this->assertDatabaseHas('prediction_executions', ['status' => 'Failed', 'forecast_hours' => 24]);
    }

    public function test_empty_or_malformed_ml_results_are_failed_attempts(): void
    {
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldReceive('getCurrentWeather')->andReturn($this->weather()));
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldReceive('predictCitywide')->andReturn(['predictions' => []], ['predictions' => ['bad row']]));
        $this->actingAs($this->staff());
        foreach ([24, 48] as $hours) {
            $this->post(route('prediction.citywide'), ['forecast_hours' => $hours])->assertRedirect(route('prediction.index'));
        }
        $this->assertSame(2, PredictionExecution::where('status', 'Failed')->count());
        $this->assertSame(0, PredictionExecution::whereNotNull('result_snapshot')->count());
    }

    public function test_invalid_horizon_does_not_start_an_execution(): void
    {
        $this->actingAs($this->staff())->post(route('prediction.citywide'), ['forecast_hours' => 12])->assertSessionHasErrors('forecast_hours');
        $this->assertDatabaseCount('prediction_executions', 0);
    }

    public function test_simulation_is_saved_separately_with_the_original_rainfall_inputs(): void
    {
        $rainfall = ['rainfall_24h_mm' => 20.0, 'rainfall_3d_mm' => 50.0, 'rainfall_7d_mm' => 100.0];
        $result = $this->predictionResult(24);
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldReceive('predictCitywide')->once()
            ->withArgs(fn ($input) => $input['simulation'] === $rainfall && $input['forecast_hours'] === 24)
            ->andReturn($result));
        $this->actingAs($this->staff())->postJson(route('flood-operation.simulate'), $rainfall)->assertOk()
            ->assertJsonPath('predictions', $result['predictions'])
            ->assertJsonPath('history_url', route('prediction.history.show', PredictionExecution::first()));
        $run = PredictionExecution::first();
        $this->assertSame('Simulation', $run->kind);
        $this->assertEquals($rainfall, $run->input_snapshot['simulation']);
        $this->assertSame($result, $run->result_snapshot);
        $this->get(route('prediction.history.show', $run))->assertOk()->assertSee('Hypothetical rainfall simulation.');
    }

    public function test_saved_run_review_never_calls_ml_or_weather_and_does_not_change_results(): void
    {
        $run = $this->savedRun();
        $original = $run->result_snapshot;
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldNotReceive('predictCitywide'));
        $this->mock(LiveWeatherService::class, fn ($mock) => $mock->shouldNotReceive('getCurrentWeather'));
        $this->actingAs($this->staff())->get(route('prediction.history.show', $run))->assertOk()
            ->assertSee('Hulo')->assertSee('Level B')->assertSee('Moderate (1.5 ft)')
            ->assertSee('2:00:00 PM');
        $this->assertSame($original, $run->fresh()->result_snapshot);
        $this->get(route('prediction.history.show', 99999))->assertNotFound();
    }

    public function test_failed_simulation_is_saved_but_invalid_rainfall_does_not_run(): void
    {
        $this->mock(FloodPredictionService::class, fn ($mock) => $mock->shouldReceive('predictCitywide')->once()
            ->andThrow(new RuntimeException('Simulation API unavailable')));
        $this->actingAs($this->staff());
        $this->postJson(route('flood-operation.simulate'), [
            'rainfall_24h_mm' => 20, 'rainfall_3d_mm' => 10, 'rainfall_7d_mm' => 30,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('prediction_executions', 0);
        $this->postJson(route('flood-operation.simulate'), [
            'rainfall_24h_mm' => 20, 'rainfall_3d_mm' => 50, 'rainfall_7d_mm' => 100,
        ])->assertStatus(502);
        $this->assertDatabaseHas('prediction_executions', ['kind' => 'Simulation', 'status' => 'Failed']);
        $this->assertNull(PredictionExecution::first()->result_snapshot);
    }

    public function test_remarks_are_attributed_append_only_escaped_and_do_not_overwrite_results(): void
    {
        $run = $this->savedRun();
        $snapshot = $run->result_snapshot;
        $user = $this->staff();
        $this->actingAs($user)->post(route('prediction.history.remarks', $run), [
            'body' => '<script>alert(1)</script> Follow up with Hulo.', 'barangay' => 'Hulo',
            'user_id' => 999, 'author_name' => 'Forged', 'result_snapshot' => [], 'status' => 'Failed',
        ])->assertRedirect(route('prediction.history.show', $run));
        $this->post(route('prediction.history.remarks', $run), ['body' => 'Second observation.'])->assertRedirect();
        $run->refresh();
        $this->assertSame($snapshot, $run->result_snapshot);
        $this->assertSame('Completed', $run->status);
        $this->assertSame(2, $run->remarks()->count());
        $remark = $run->remarks()->first();
        $this->assertSame($user->id, $remark->user_id);
        $this->assertSame($user->full_name, $remark->author_name);
        $this->get(route('prediction.history.show', $run))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->put(route('prediction.history.remarks', $run), ['body' => 'Overwrite'])->assertStatus(405);
        $this->assertSame($snapshot, $run->fresh()->result_snapshot);
    }

    public function test_remark_requires_text_and_a_barangay_from_the_saved_run(): void
    {
        $run = $this->savedRun();
        $this->actingAs($this->staff());
        foreach ([['body' => '   '], ['body' => str_repeat('x', 5001)], ['body' => 'Note', 'barangay' => 'Unknown Barangay']] as $data) {
            $this->post(route('prediction.history.remarks', $run), $data)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('prediction_remarks', 0);
    }

    public function test_history_and_remarks_require_their_permissions(): void
    {
        $run = $this->savedRun();
        $this->get(route('prediction.history.index'))->assertRedirect(route('login'));
        $this->post(route('prediction.history.remarks', $run), ['body' => 'Guest'])->assertRedirect(route('login'));
        foreach (['fire-responder', 'public-resident'] as $role) {
            $this->actingAs($this->staff($role, []))->get(route('prediction.history.index'))->assertForbidden();
            $this->post(route('prediction.history.remarks', $run), ['body' => 'Denied'])->assertForbidden();
        }
        $this->actingAs($this->staff('system-viewer', ['prediction.view']))
            ->get(route('prediction.history.show', $run))->assertOk()->assertDontSee('Add Remark');
        $this->post(route('prediction.history.remarks', $run), ['body' => 'Read only'])->assertForbidden();
        $this->assertDatabaseCount('prediction_remarks', 0);
    }

    public function test_history_filters_runs_by_window_type_and_status(): void
    {
        $match = $this->savedRun(['forecast_hours' => 48]);
        $this->savedRun(['forecast_hours' => 72]);
        $this->savedRun(['forecast_hours' => 48, 'kind' => 'Simulation']);
        $this->savedRun(['forecast_hours' => 48, 'status' => 'Failed', 'result_snapshot' => null]);
        $this->actingAs($this->staff())->get(route('prediction.history.index', [
            'forecast_hours' => 48, 'kind' => 'Forecast', 'status' => 'Completed',
        ]))->assertOk()->assertViewHas('runs', fn ($runs) => $runs->total() === 1 && $runs->first()->id === $match->id);
    }

    public function test_staff_deletion_preserves_authorship_and_history(): void
    {
        $user = $this->staff();
        $run = $this->savedRun(['requested_by_user_id' => $user->id, 'requested_by_name' => $user->full_name]);
        $this->actingAs($user)->post(route('prediction.history.remarks', $run), ['body' => 'Keep this observation.'])->assertRedirect();
        $name = $user->full_name;
        $user->delete();
        $this->assertNull($run->fresh()->requested_by_user_id);
        $this->assertSame($name, $run->fresh()->requested_by_name);
        $this->assertNull($run->remarks()->first()->user_id);
        $this->assertSame($name, $run->remarks()->first()->author_name);
        $this->assertSame($this->predictionResult(24), $run->fresh()->result_snapshot);
    }

    public function test_seeded_operations_and_flood_staff_have_review_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach (['operations-manager', 'flood-analyst'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $this->assertTrue($role->permissions()->where('slug', 'prediction.review')->exists());
        }
    }

    private function staff(string $role = 'flood-analyst', array $permissions = ['prediction.view', 'prediction.run', 'prediction.review']): User
    {
        $role = Role::firstOrCreate(['slug' => $role], ['name' => $role, 'is_active' => true]);
        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'prediction', 'is_active' => true]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function savedRun(array $overrides = []): PredictionExecution
    {
        return PredictionExecution::create(array_replace([
            'requested_by_name' => 'Original Staff', 'kind' => 'Forecast', 'forecast_hours' => 24,
            'status' => 'Completed', 'requested_at' => '2026-10-10 06:00:00', 'completed_at' => '2026-10-10 06:00:30',
            'input_snapshot' => ['weather_context' => $this->weather()], 'result_snapshot' => $this->predictionResult(24),
        ], $overrides));
    }

    private function weather(): array
    {
        return ['date' => '2026-10-09', 'time' => '00:00:00', 'forecast_rainfall_24h_mm' => 25.4, 'forecast_windows' => ['48' => ['rainfall_mm' => 65.3]]];
    }

    private function predictionResult(int $hours): array
    {
        return [
            'forecast_hours' => $hours, 'generated_at' => '2026-10-10T14:00:10+08:00',
            'selected_forecast' => ['start' => '2026-10-10T00:00:00+08:00', 'end' => '2026-10-13T00:00:00+08:00'],
            'predictions' => [['rank' => 1, 'barangay' => 'Hulo', 'flood_code' => 'B', 'confidence' => 0.873, 'probabilities' => ['A' => 0.1, 'B' => 0.873, 'C' => 0.027, 'D' => 0]]],
            'model_version' => 'preserve-original-version',
        ];
    }
}
