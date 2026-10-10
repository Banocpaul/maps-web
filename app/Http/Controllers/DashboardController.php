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
use App\Services\ProcessDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $assignedRole = $this->assignedRole($request);
        return view('dashboard.index', array_merge([
            'user' => $request->user(), 'assignedRole' => $assignedRole, 'roleSlug' => $assignedRole->slug,
        ], app(ProcessDashboardService::class)->build($request->user(), $assignedRole)));
    }

    public function incidentAnalytics(Request $request): View
    {
        return $this->renderAnalytics($request);
    }

    public function systemAnalytics(Request $request): View
    {
        return $this->renderAnalytics($request, true);
    }

    private function assignedRole(Request $request): Role
    {
        $assignedRole = $request->user()->role()
            ->with('permissions')
            ->first();

        abort_if(
            $assignedRole === null || ! $assignedRole->is_active,
            403,
            'No active system role is assigned to this account.'
        );

        abort_unless(in_array($assignedRole->slug, [
            'administrator',
            'fire-responder',
            'flood-analyst',
            'operations-manager',
        ], true), 403, 'This role does not have a personalized dashboard.');

        return $assignedRole;
    }

    private function renderAnalytics(Request $request, bool $systemAnalytics = false): View
    {
        $user = $request->user();
        $assignedRole = $this->assignedRole($request);
        $roleSlug = $assignedRole->slug;
        abort_unless($systemAnalytics ? $roleSlug === 'administrator' : in_array($roleSlug, [
            'fire-responder', 'flood-analyst', 'operations-manager',
        ], true), 403);

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'analytics' => ['nullable', 'in:fire,flood'],
        ]);

        $selectedAnalytics = match ($roleSlug) {
            'fire-responder' => 'fire', 'flood-analyst' => 'flood',
            default => $validated['analytics'] ?? 'fire',
        };

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

        $needsFire = in_array($roleSlug, [
            'fire-responder',
            'operations-manager',
        ], true);

        $needsFlood = in_array($roleSlug, [
            'flood-analyst',
            'operations-manager',
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
                $liveWeatherError =
                    'Live weather data is temporarily unavailable.';
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

        return view($systemAnalytics ? 'system-analytics.index' : 'incident-analytics.index', compact(
            'user',
            'assignedRole',
            'roleSlug',
            'adminDashboard',
            'fireDashboard',
            'fireOperations',
            'floodDashboard',
            'liveWeather',
            'liveWeatherError',
            'barangays',
            'availableYears',
            'selectedYear',
            'selectedBarangayId',
            'selectedBarangay',
            'selectedAnalytics'
        ));
    }

    private function administratorDashboard(): array
    {
        $hasActivityLogs = Schema::hasTable('activity_logs');
        $hasSmsLogs = Schema::hasTable('sms_logs');
        $hasLastSeen = Schema::hasColumn('users', 'last_seen_at');

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $onlineCutoff = $now->copy()->subMinutes(5);

        $roles = Role::query()
            ->withCount('users')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $onlineUsers = $hasLastSeen
            ? User::query()
                ->with('role:id,name,slug')
                ->where('is_active', true)
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', $onlineCutoff)
                ->orderByDesc('last_seen_at')
                ->get([
                    'id',
                    'role_id',
                    'name',
                    'first_name',
                    'last_name',
                    'last_login_at',
                    'last_seen_at',
                ])
            : collect();

        $loginsThisMonth = $hasActivityLogs
            ? ActivityLog::query()
                ->where('action', 'login')
                ->where('created_at', '>=', $monthStart)
                ->count()
            : 0;

        $failedLoginsThisMonth = $hasActivityLogs
            ? ActivityLog::query()
                ->where('action', 'failed_login')
                ->where('created_at', '>=', $monthStart)
                ->count()
            : 0;

        $actionsThisMonth = $hasActivityLogs
            ? ActivityLog::query()
                ->where('created_at', '>=', $monthStart)
                ->whereNotIn('action', [
                    'login',
                    'logout',
                    'failed_login',
                ])
                ->count()
            : 0;

        return [
            'total_users' => User::query()->count(),
            'active_users' => User::query()
                ->where('is_active', true)
                ->count(),
            'inactive_users' => User::query()
                ->where('is_active', false)
                ->count(),
            'online_users' => $onlineUsers,
            'online_count' => $onlineUsers->count(),
            'online_cutoff_minutes' => 5,
            'roles' => $roles,
            'logins_this_month' => $loginsThisMonth,
            'failed_logins_this_month' => $failedLoginsThisMonth,
            'actions_this_month' => $actionsThisMonth,
            'failed_logins_today' => $hasActivityLogs
                ? ActivityLog::query()
                    ->where('action', 'failed_login')
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
            'recent_activity' => $hasActivityLogs
                ? ActivityLog::query()
                    ->latest()
                    ->limit(8)
                    ->get()
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
            'login_trend' => $hasActivityLogs
                ? $this->adminLoginTrend($now)
                : $this->emptyLoginTrend($now),
            'action_mix' => $hasActivityLogs
                ? $this->adminActionMix($monthStart)
                : ['labels' => [], 'values' => []],
            'module_activity' => $hasActivityLogs
                ? $this->adminModuleActivity($monthStart)
                : ['labels' => [], 'values' => []],
            'role_distribution' => [
                'labels' => $roles->pluck('name')->values()->all(),
                'values' => $roles
                    ->pluck('users_count')
                    ->map(fn ($count): int => (int) $count)
                    ->values()
                    ->all(),
            ],
            'most_active_users' => $hasActivityLogs
                ? $this->adminMostActiveUsers($monthStart)
                : ['labels' => [], 'values' => []],
            'daily_activity' => $hasActivityLogs
                ? $this->adminDailyActivity($now)
                : $this->emptyDailyActivity($now),
        ];
    }

    private function adminLoginTrend(Carbon $now): array
    {
        $start = $now->copy()
            ->subMonths(5)
            ->startOfMonth();

        $rows = ActivityLog::query()
            ->selectRaw(
                (DB::connection()->getDriverName() === 'sqlite'
                    ? "strftime('%Y-%m', created_at)"
                    : "DATE_FORMAT(created_at, '%Y-%m')") . ' as month_key, ' .
                'action, COUNT(*) as total'
            )
            ->whereIn('action', ['login', 'failed_login'])
            ->where('created_at', '>=', $start)
            ->groupBy('month_key', 'action')
            ->get()
            ->groupBy('month_key');

        $labels = [];
        $successful = [];
        $failed = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->copy()
                ->subMonths($offset)
                ->startOfMonth();

            $key = $month->format('Y-m');
            $labels[] = $month->format('M Y');
            $monthRows = $rows->get($key, collect());

            $successful[] = (int) (
                $monthRows->firstWhere('action', 'login')?->total ?? 0
            );

            $failed[] = (int) (
                $monthRows->firstWhere('action', 'failed_login')?->total ?? 0
            );
        }

        return compact('labels', 'successful', 'failed');
    }

    private function emptyLoginTrend(Carbon $now): array
    {
        $labels = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $labels[] = $now->copy()
                ->subMonths($offset)
                ->format('M Y');
        }

        return [
            'labels' => $labels,
            'successful' => array_fill(0, 6, 0),
            'failed' => array_fill(0, 6, 0),
        ];
    }

    private function adminActionMix(Carbon $monthStart): array
    {
        $rows = ActivityLog::query()
            ->selectRaw('action, COUNT(*) as total')
            ->where('created_at', '>=', $monthStart)
            ->groupBy('action')
            ->orderByDesc('total')
            ->get();

        $top = $rows->take(6);
        $other = (int) $rows->skip(6)->sum('total');

        $labels = $top
            ->pluck('action')
            ->map(
                fn ($action): string =>
                    str((string) $action)
                        ->replace('_', ' ')
                        ->title()
                        ->toString()
            )
            ->values()
            ->all();

        $values = $top
            ->pluck('total')
            ->map(fn ($total): int => (int) $total)
            ->values()
            ->all();

        if ($other > 0) {
            $labels[] = 'Other';
            $values[] = $other;
        }

        return compact('labels', 'values');
    }

    private function adminModuleActivity(Carbon $monthStart): array
    {
        $rows = ActivityLog::query()
            ->selectRaw('module, COUNT(*) as total')
            ->where('created_at', '>=', $monthStart)
            ->groupBy('module')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows
                ->pluck('module')
                ->map(
                    fn ($module): string =>
                        str((string) $module)
                            ->replace('-', ' ')
                            ->title()
                            ->toString()
                )
                ->values()
                ->all(),
            'values' => $rows
                ->pluck('total')
                ->map(fn ($total): int => (int) $total)
                ->values()
                ->all(),
        ];
    }

    private function adminMostActiveUsers(Carbon $monthStart): array
    {
        $rows = ActivityLog::query()
            ->selectRaw(
                'user_id, MAX(user_name) as user_name, COUNT(*) as total'
            )
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $monthStart)
            ->whereNotIn('action', [
                'login',
                'logout',
                'failed_login',
            ])
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'labels' => $rows
                ->map(
                    fn ($row): string =>
                        $row->user_name ?: 'User #' . $row->user_id
                )
                ->values()
                ->all(),
            'values' => $rows
                ->pluck('total')
                ->map(fn ($total): int => (int) $total)
                ->values()
                ->all(),
        ];
    }

    private function adminDailyActivity(Carbon $now): array
    {
        $start = $now->copy()
            ->subDays(13)
            ->startOfDay();

        $rows = ActivityLog::query()
            ->selectRaw('DATE(created_at) as day_key, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day_key')
            ->pluck('total', 'day_key');

        $labels = [];
        $values = [];

        for ($offset = 13; $offset >= 0; $offset--) {
            $day = $now->copy()
                ->subDays($offset)
                ->startOfDay();

            $key = $day->format('Y-m-d');
            $labels[] = $day->format('M j');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return compact('labels', 'values');
    }

    private function emptyDailyActivity(Carbon $now): array
    {
        $labels = [];

        for ($offset = 13; $offset >= 0; $offset--) {
            $labels[] = $now->copy()
                ->subDays($offset)
                ->format('M j');
        }

        return [
            'labels' => $labels,
            'values' => array_fill(0, 14, 0),
        ];
    }

    private function fireOperations(): array
    {
        return [
            'active' => FireIncident::query()
                ->active()
                ->count(),
            'reported' => FireIncident::query()
                ->where('status', 'Reported')
                ->count(),
            'responding' => FireIncident::query()
                ->where('status', 'Responding')
                ->count(),
            'controlled' => FireIncident::query()
                ->where('status', 'Controlled')
                ->count(),
            'resolved' => FireIncident::query()
                ->where('status', 'Resolved')
                ->count(),
            'recent_active' => FireIncident::query()
                ->with('barangay')
                ->active()
                ->latest('reported_at')
                ->limit(8)
                ->get(),
        ];
    }

}
