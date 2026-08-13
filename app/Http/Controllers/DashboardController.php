<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FloodAnalyticsService;
use App\Services\LiveWeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $user = $request->user();
        $assignedRole = $user->role()
            ->with('permissions')
            ->first();

        abort_if($assignedRole === null, 403, 'No active system role is assigned to this account.');

        $roleSlug = $assignedRole->slug;

        abort_unless(in_array($roleSlug, [
            'administrator',
            'fire-responder',
            'flood-analyst',
            'operations-officer',
        ], true), 403, 'This role does not have a personalized dashboard.');

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
        ]);

        $selectedYear = isset($validated['year'])
            ? (int) $validated['year']
            : null;

        $selectedBarangayId = isset($validated['barangay_id'])
            ? (int) $validated['barangay_id']
            : null;

        $selectedBarangay = $selectedBarangayId
            ? Barangay::query()->find($selectedBarangayId)
            : null;

        $barangays = collect();
        $availableYears = [];
        $fireDashboard = [];
        $floodDashboard = [];
        $liveWeather = null;
        $liveWeatherError = null;
        $adminDashboard = [];
        $fireOperations = [];
        $operationsSummary = [];

        $needsFire = in_array($roleSlug, [
            'fire-responder',
            'operations-officer',
        ], true);

        $needsFlood = in_array($roleSlug, [
            'flood-analyst',
            'operations-officer',
        ], true);

        if ($needsFire) {
            $fireDashboard = $this->fireAnalyticsService->getDashboardData(
                $selectedYear,
                $selectedBarangayId
            );

            $fireOperations = $this->fireOperations();
        }

        if ($needsFlood) {
            $floodDashboard = $this->floodAnalyticsService->getDashboardData(
                $selectedYear,
                $selectedBarangay?->name
            );

            try {
                $liveWeather = $this->liveWeatherService->getCurrentWeather();
            } catch (Throwable $exception) {
                report($exception);
                $liveWeatherError = 'Live weather data is temporarily unavailable.';
            }
        }

        if ($needsFire || $needsFlood) {
            $barangays = Barangay::query()
                ->where('is_active', true)
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
        }

        if ($roleSlug === 'administrator') {
            $adminDashboard = $this->administratorDashboard();
        }

        if ($roleSlug === 'operations-officer') {
            $operationsSummary = $this->operationsSummary();
        }

        return view('dashboard.index', compact(
            'user',
            'assignedRole',
            'roleSlug',
            'adminDashboard',
            'fireDashboard',
            'fireOperations',
            'floodDashboard',
            'operationsSummary',
            'liveWeather',
            'liveWeatherError',
            'barangays',
            'availableYears',
            'selectedYear',
            'selectedBarangayId',
            'selectedBarangay'
        ));
    }

    private function administratorDashboard(): array
    {
        $hasActivityLogs = Schema::hasTable('activity_logs');
        $hasSmsLogs = Schema::hasTable('sms_logs');

        return [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
            'inactive_users' => User::query()->where('is_active', false)->count(),
            'roles' => Role::query()
                ->withCount('users')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'failed_logins_today' => $hasActivityLogs
                ? ActivityLog::query()
                    ->where('action', 'failed_login')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
            'recent_activity' => $hasActivityLogs
                ? ActivityLog::query()->latest()->limit(8)->get()
                : collect(),
            'sms_sent_today' => $hasSmsLogs
                ? SmsLog::query()
                    ->where('status', 'sent')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
            'sms_failed_today' => $hasSmsLogs
                ? SmsLog::query()
                    ->where('status', 'failed')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
        ];
    }

    private function fireOperations(): array
    {
        return [
            'active' => FireIncident::query()->active()->count(),
            'reported' => FireIncident::query()->where('status', 'Reported')->count(),
            'responding' => FireIncident::query()->where('status', 'Responding')->count(),
            'controlled' => FireIncident::query()->where('status', 'Controlled')->count(),
            'resolved' => FireIncident::query()->where('status', 'Resolved')->count(),
            'recent_active' => FireIncident::query()
                ->with('barangay')
                ->active()
                ->latest('reported_at')
                ->limit(8)
                ->get(),
        ];
    }

    private function operationsSummary(): array
    {
        $hasSmsLogs = Schema::hasTable('sms_logs');

        return [
            'sms_sent_today' => $hasSmsLogs
                ? SmsLog::query()
                    ->where('status', 'sent')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
            'sms_failed_today' => $hasSmsLogs
                ? SmsLog::query()
                    ->where('status', 'failed')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
        ];
    }
}
