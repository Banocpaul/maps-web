<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRoleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_dashboard_shows_four_fire_and_four_flood_analytics(): void
    {
        $response = $this->renderDashboard('operations-manager');

        $response
            ->assertSee('Fire Incident Intelligence')
            ->assertSee('Flood Risk Intelligence')
            ->assertSee('fireMonthlyChart', false)
            ->assertSee('fireSeverityChart', false)
            ->assertSee('fireBarangayChart', false)
            ->assertSee('fireTimeChart', false)
            ->assertSee('floodMonthlyChart', false)
            ->assertSee('floodRiskChart', false)
            ->assertSee('floodBarangayChart', false)
            ->assertSee('floodDepthRainChart', false);
    }

    public function test_fire_responder_dashboard_shows_only_fire_analytics(): void
    {
        $response = $this->renderDashboard('fire-responder');

        $response
            ->assertSee('Fire Incident Intelligence')
            ->assertSee('fireTimeChart', false)
            ->assertDontSee('Flood Risk Intelligence')
            ->assertDontSee('floodMonthlyChart', false);
    }

    public function test_flood_analyst_dashboard_shows_only_flood_analytics(): void
    {
        $response = $this->renderDashboard('flood-analyst');

        $response
            ->assertSee('Flood Risk Intelligence')
            ->assertSee('floodDepthRainChart', false)
            ->assertDontSee('Fire Incident Intelligence')
            ->assertDontSee('fireMonthlyChart', false);
    }

    private function renderDashboard(string $slug): \Illuminate\Testing\TestView
    {
        $role = Role::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $this->view('dashboard.index', [
            'user' => $user,
            'assignedRole' => $role,
            'roleSlug' => $slug,
            'adminDashboard' => [],
            'fireDashboard' => $this->fireDashboard(),
            'fireOperations' => ['recent_active' => collect()],
            'floodDashboard' => $this->floodDashboard(),
            'operationsSummary' => [],
            'liveWeather' => null,
            'liveWeatherError' => null,
            'barangays' => collect(),
            'availableYears' => [],
            'selectedYear' => null,
            'selectedBarangayId' => null,
            'selectedBarangay' => null,
        ]);
    }

    private function fireDashboard(): array
    {
        return [
            'kpis' => [],
            'monthly_trend' => ['labels' => ['Jan'], 'incidents' => [2]],
            'severity_distribution' => ['labels' => ['Minor'], 'values' => [2]],
            'top_barangays' => ['labels' => ['Hulo'], 'incidents' => [2]],
            'time_distribution' => ['labels' => ['Night'], 'values' => [2]],
        ];
    }

    private function floodDashboard(): array
    {
        return [
            'kpis' => [],
            'monthly_trend' => [
                'labels' => ['Jan'],
                'records' => [3],
                'average_depth_mm' => [120],
                'average_rainfall_24h_mm' => [45],
            ],
            'risk_distribution' => ['labels' => ['High'], 'values' => [3]],
            'top_barangays' => ['labels' => ['Hulo'], 'records' => [3]],
            'recent_records' => collect(),
        ];
    }
}
