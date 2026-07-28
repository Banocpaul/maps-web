<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\FireAnalyticsService;
use App\Services\FloodAnalyticsService;
use App\Services\LiveWeatherService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly FireAnalyticsService $fireAnalyticsService,
        private readonly FloodAnalyticsService $floodAnalyticsService,
        private readonly LiveWeatherService $liveWeatherService
    ) {
    }

    public function index(Request $request): View
    {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Assigned role
        |--------------------------------------------------------------------------
        |
        | The users table currently contains a legacy "role" string column,
        | which conflicts with the User::role() relationship. Querying the
        | relationship directly avoids that conflict.
        |
        */

        $assignedRole = $user->role()
            ->with('permissions')
            ->first();

        $validated = $request->validate([
            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
            ],
        ]);

        $selectedYear = isset($validated['year'])
            ? (int) $validated['year']
            : null;

        $selectedBarangayId = isset($validated['barangay_id'])
            ? (int) $validated['barangay_id']
            : null;

        /*
        |--------------------------------------------------------------------------
        | Load selected barangay
        |--------------------------------------------------------------------------
        */

        $selectedBarangay = $selectedBarangayId
            ? Barangay::query()->find($selectedBarangayId)
            : null;

        $selectedBarangayName = $selectedBarangay?->name;

        /*
        |--------------------------------------------------------------------------
        | Fire analytics
        |--------------------------------------------------------------------------
        */

        $fireDashboard = $this->fireAnalyticsService->getDashboardData(
            $selectedYear,
            $selectedBarangayId
        );

        /*
        |--------------------------------------------------------------------------
        | Flood analytics
        |--------------------------------------------------------------------------
        */

        $floodDashboard = $this->floodAnalyticsService->getDashboardData(
            $selectedYear,
            $selectedBarangayName
        );

        /*
        |--------------------------------------------------------------------------
        | Live weather
        |--------------------------------------------------------------------------
        */

        $liveWeather = null;
        $liveWeatherError = null;

        try {
            $liveWeather = $this->liveWeatherService->getCurrentWeather();
        } catch (Throwable $exception) {
            report($exception);

            $liveWeatherError =
                'Live weather data is temporarily unavailable.';
        }

        /*
        |--------------------------------------------------------------------------
        | Dashboard filter options
        |--------------------------------------------------------------------------
        */

        $barangays = Barangay::query()
            ->orderBy('name')
            ->get();

        $availableYears = collect([
            ...($fireDashboard['available_years'] ?? []),
            ...($floodDashboard['available_years'] ?? []),
        ])
            ->filter()
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return view('dashboard.index', [
            'user' => $user,
            'assignedRole' => $assignedRole,

            'fireDashboard' => $fireDashboard,
            'floodDashboard' => $floodDashboard,

            'liveWeather' => $liveWeather,
            'liveWeatherError' => $liveWeatherError,

            'barangays' => $barangays,
            'availableYears' => $availableYears,

            'selectedYear' => $selectedYear,
            'selectedBarangayId' => $selectedBarangayId,
            'selectedBarangay' => $selectedBarangay,
            'selectedBarangayName' => $selectedBarangayName,
        ]);
    }
}