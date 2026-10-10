<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Barangay;
use App\Models\Permission;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FloodAnalyticsService;
use App\Services\LiveWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DashboardRoleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_analytics_page_shows_four_fire_and_four_flood_analytics(): void
    {
        $response = $this->renderDashboard('operations-manager', 'incident-analytics.index');

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
            ->assertSee('floodRainfallChart', false)
            ->assertDontSee('Average Flood Depth')
            ->assertDontSee('Monthly Depth and Rainfall')
            ->assertDontSee('Average Flood Depth (mm)')
            ->assertSee('7.5 mm');
    }

    public function test_operations_dashboard_has_overview_and_analytics_link_without_charts(): void
    {
        $this->renderDashboard('operations-manager')
            ->assertSee('Active Fire Incidents')
            ->assertSee('SMS Sent Today')
            ->assertSee('Command Shortcuts')
            ->assertSee(route('incident-analytics.index'), false)
            ->assertDontSee('Business Intelligence')
            ->assertDontSee('fireMonthlyChart', false)
            ->assertDontSee('floodMonthlyChart', false)
            ->assertDontSee('name="barangay_id"', false);
    }

    public function test_analytics_filters_stay_on_new_page_and_preserve_selected_hazard(): void
    {
        $user = $this->createUser('operations-manager');
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);

        $this->mock(FireAnalyticsService::class, function ($mock) use ($barangay): void {
            $mock->shouldReceive('getDashboardData')->once()->with(2025, $barangay->id)
                ->andReturn($this->fireDashboard());
        });
        $this->mock(FloodAnalyticsService::class, function ($mock): void {
            $mock->shouldReceive('getDashboardData')->once()->with(2025, 'Hulo')
                ->andReturn($this->floodDashboard());
        });
        $this->mock(LiveWeatherService::class, function ($mock): void {
            $mock->shouldReceive('getCurrentWeather')->once()->andReturn(['rainfall_24h_mm' => 7.5]);
        });

        $this->actingAs($user)->get(route('incident-analytics.index', [
            'year' => 2025, 'barangay_id' => $barangay->id, 'analytics' => 'flood',
        ]))->assertOk()
            ->assertViewIs('incident-analytics.index')
            ->assertViewHas('selectedAnalytics', 'flood')
            ->assertViewHas('selectedYear', 2025)
            ->assertViewHas('selectedBarangayId', $barangay->id)
            ->assertSee('action="'.route('incident-analytics.index').'"', false)
            ->assertSee(route('incident-analytics.index', ['analytics' => 'flood']), false)
            ->assertSee('value="flood" data-analytics-filter-input', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_incident_analytics_requires_login_and_dashboard_permission(): void
    {
        $this->get(route('incident-analytics.index'))->assertRedirect(route('login'));
        $this->actingAs($this->createUser('operations-manager', false))
            ->get(route('incident-analytics.index'))->assertForbidden();
    }

    public function test_incident_analytics_does_not_expand_other_roles_access(): void
    {
        foreach (['administrator', 'fire-responder', 'flood-analyst', 'public-resident'] as $slug) {
            $this->actingAs($this->createUser($slug))->get(route('incident-analytics.index'))
                ->assertForbidden();
        }
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
            ->assertSee('floodRainfallChart', false)
            ->assertSee(route('gis.index'), false)
            ->assertDontSee(route('public.flood-map'), false)
            ->assertDontSee('Average Flood Depth (mm)')
            ->assertDontSee('Duration')
            ->assertSee('7.5 mm')
            ->assertDontSee('Fire Incident Intelligence')
            ->assertDontSee('fireMonthlyChart', false);
    }

    private function createUser(string $slug, bool $canViewDashboard = true): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], [
            'name' => str($slug)->headline()->toString(),
            'is_active' => true,
        ]);

        if ($canViewDashboard) {
            $permission = Permission::firstOrCreate(['slug' => 'dashboard.view'], [
                'name' => 'View Dashboard', 'module' => 'dashboard', 'is_active' => true,
            ]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

    }

    private function renderDashboard(string $slug, string $view = 'dashboard.index'): \Illuminate\Testing\TestView
    {
        $user = $this->createUser($slug);
        $role = $user->role()->first();
        $this->actingAs($user);

        return $this->view($view, [
            'user' => $user,
            'assignedRole' => $role,
            'roleSlug' => $slug,
            'adminDashboard' => [],
            'fireDashboard' => $this->fireDashboard(),
            'fireOperations' => ['recent_active' => collect()],
            'floodDashboard' => $this->floodDashboard(),
            'operationsSummary' => [],
            'liveWeather' => ['rainfall_24h_mm' => 7.5],
            'liveWeatherError' => null,
            'barangays' => collect(),
            'availableYears' => [],
            'selectedYear' => null,
            'selectedBarangayId' => null,
            'selectedBarangay' => null,
            'errors' => new ViewErrorBag(),
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
