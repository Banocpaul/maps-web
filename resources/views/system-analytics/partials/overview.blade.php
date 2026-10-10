@php
    $recentActivity = collect($adminDashboard['recent_activity'] ?? []);
    $roles = collect($adminDashboard['roles'] ?? []);
    $onlineUsers = collect($adminDashboard['online_users'] ?? []);

    $loginTrend = $adminDashboard['login_trend'] ?? [];
    $actionMix = $adminDashboard['action_mix'] ?? [];
    $moduleActivity = $adminDashboard['module_activity'] ?? [];
    $roleDistribution = $adminDashboard['role_distribution'] ?? [];
    $mostActiveUsers = $adminDashboard['most_active_users'] ?? [];
    $dailyActivity = $adminDashboard['daily_activity'] ?? [];
@endphp

<section class="overflow-hidden rounded-2xl border border-indigo-200 bg-indigo-50/60 shadow-sm">
    <div class="border-b border-indigo-200 bg-gradient-to-r from-indigo-50 via-blue-50 to-sky-50 px-5 py-5">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">
            Administrator only
        </p>
        <h2 class="mt-1 text-xl font-semibold text-indigo-950">
            System Analytics
        </h2>
        <p class="mt-1 text-sm text-indigo-700">
            Authentication, user adoption, system activity, and operational usage intelligence.
        </p>
    </div>

    <div class="bg-white/80 p-4 sm:p-5">
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-emerald-700">Online Now</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">
                    {{ number_format($adminDashboard['online_count'] ?? 0) }}
                </p>
                <p class="mt-2 text-xs text-emerald-700">
                    Active within the last {{ $adminDashboard['online_cutoff_minutes'] ?? 5 }} minutes
                </p>
            </article>

            <article class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-blue-700">Logins This Month</p>
                <p class="mt-2 text-3xl font-bold text-blue-800">
                    {{ number_format($adminDashboard['logins_this_month'] ?? 0) }}
                </p>
                <p class="mt-2 text-xs text-blue-700">Successful authentication events</p>
            </article>

            <article class="rounded-2xl border border-violet-200 bg-violet-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-violet-700">Actions This Month</p>
                <p class="mt-2 text-3xl font-bold text-violet-800">
                    {{ number_format($adminDashboard['actions_this_month'] ?? 0) }}
                </p>
                <p class="mt-2 text-xs text-violet-700">Non-authentication audited actions</p>
            </article>

            <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <p class="text-sm font-medium text-red-700">Failed Logins This Month</p>
                <p class="mt-2 text-3xl font-bold text-red-700">
                    {{ number_format($adminDashboard['failed_logins_this_month'] ?? 0) }}
                </p>
                <p class="mt-2 text-xs text-red-600">Authentication attempts requiring review</p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">Login Trend — Last 6 Months</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Successful and failed authentication events by month
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminLoginTrendChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">Most Performed Actions</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Share of audited actions during the current month
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminActionMixChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">Activity by Module</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Modules receiving the most audited activity this month
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminModuleActivityChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">Users by Role</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Current distribution of system accounts
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminRoleDistributionChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">Most Active Users</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Users with the most non-authentication actions this month
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminMostActiveUsersChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-950">System Activity — Last 14 Days</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Daily volume of recorded audit events
                </p>
                <div class="mt-4 h-72">
                    <canvas id="adminDailyActivityChart"></canvas>
                </div>
            </article>
        </section>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
    <article class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
        <div class="border-b border-emerald-200 bg-emerald-50 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-emerald-950">Users Online Now</h2>
                    <p class="mt-1 text-sm text-emerald-700">
                        Approximate presence based on activity within the last
                        {{ $adminDashboard['online_cutoff_minutes'] ?? 5 }} minutes
                    </p>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800">
                    {{ number_format($onlineUsers->count()) }}
                </span>
            </div>
        </div>

        <div class="max-h-[420px] divide-y divide-slate-100 overflow-y-auto">
            @forelse ($onlineUsers as $onlineUser)
                @php
                    $onlineName = $onlineUser->full_name !== ''
                        ? $onlineUser->full_name
                        : $onlineUser->name;
                @endphp

                <div class="flex items-center gap-3 px-5 py-4">
                    <div class="relative flex h-10 w-10 flex-none items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-700">
                        {{ str($onlineName)->substr(0, 1)->upper() }}
                        <span class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-500"></span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">
                            {{ $onlineName }}
                        </p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $onlineUser->role?->name ?? 'No role' }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-xs font-semibold text-emerald-700">Online</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ $onlineUser->last_seen_at?->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">
                    No users have been active in the last
                    {{ $adminDashboard['online_cutoff_minutes'] ?? 5 }} minutes.
                </p>
            @endforelse
        </div>
    </article>

    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-950">Recent System Activity</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Latest auditable actions and security events
                </p>
            </div>

            @if (Route::has('activity-logs.index'))
                <a
                    href="{{ route('activity-logs.index') }}"
                    class="text-sm font-semibold text-sky-700 hover:text-sky-900"
                >
                    View all
                </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Action</th>
                        <th class="px-5 py-3">Module</th>
                        <th class="px-5 py-3">Time</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentActivity as $activity)
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900">
                                {{ $activity->user_name ?? 'Guest user' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                    {{ str($activity->action)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ str($activity->module)->replace('-', ' ')->title() }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500">
                                {{ $activity->created_at?->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-slate-500">
                                No activity has been recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[.65fr_1.35fr]">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-slate-950">Administrative Actions</h2>

        <div class="mt-4 grid gap-3">
            @if (Route::has('users.create'))
                <a
                    href="{{ route('users.create') }}"
                    class="rounded-xl bg-sky-700 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-sky-800"
                >
                    Add User
                </a>
            @endif

            @if (Route::has('users.index'))
                <a
                    href="{{ route('users.index') }}"
                    class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Manage Users
                </a>
            @endif

            @if (Route::has('activity-logs.index'))
                <a
                    href="{{ route('activity-logs.index') }}"
                    class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Review Activity Logs
                </a>
            @endif
        </div>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-slate-950">Account Health</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Current active and inactive account totals
                </p>
            </div>

            <p class="text-sm font-semibold text-slate-700">
                {{ number_format($adminDashboard['active_users'] ?? 0) }} active /
                {{ number_format($adminDashboard['inactive_users'] ?? 0) }} inactive
            </p>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($roles as $role)
                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-700">{{ $role->name }}</span>
                    <strong class="text-slate-950">{{ number_format($role->users_count) }}</strong>
                </div>
            @endforeach
        </div>
    </article>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;

    const loginTrend = @json($loginTrend);
    const actionMix = @json($actionMix);
    const moduleActivity = @json($moduleActivity);
    const roleDistribution = @json($roleDistribution);
    const mostActiveUsers = @json($mostActiveUsers);
    const dailyActivity = @json($dailyActivity);

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };

    const loginCanvas = document.getElementById('adminLoginTrendChart');
    if (loginCanvas) {
        new Chart(loginCanvas, {
            type: 'line',
            data: {
                labels: loginTrend.labels ?? [],
                datasets: [
                    {
                        label: 'Successful Logins',
                        data: loginTrend.successful ?? [],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.10)',
                        tension: .3,
                        fill: true
                    },
                    {
                        label: 'Failed Logins',
                        data: loginTrend.failed ?? [],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220,38,38,.08)',
                        tension: .3
                    }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    const actionCanvas = document.getElementById('adminActionMixChart');
    if (actionCanvas) {
        new Chart(actionCanvas, {
            type: 'doughnut',
            data: {
                labels: actionMix.labels ?? [],
                datasets: [{
                    data: actionMix.values ?? [],
                    backgroundColor: [
                        '#2563eb',
                        '#7c3aed',
                        '#0891b2',
                        '#16a34a',
                        '#f59e0b',
                        '#dc2626',
                        '#64748b'
                    ]
                }]
            },
            options: commonOptions
        });
    }

    const moduleCanvas = document.getElementById('adminModuleActivityChart');
    if (moduleCanvas) {
        new Chart(moduleCanvas, {
            type: 'bar',
            data: {
                labels: moduleActivity.labels ?? [],
                datasets: [{
                    label: 'Audit Events',
                    data: moduleActivity.values ?? [],
                    backgroundColor: '#4f46e5'
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    const roleCanvas = document.getElementById('adminRoleDistributionChart');
    if (roleCanvas) {
        new Chart(roleCanvas, {
            type: 'doughnut',
            data: {
                labels: roleDistribution.labels ?? [],
                datasets: [{
                    data: roleDistribution.values ?? [],
                    backgroundColor: [
                        '#0f766e',
                        '#2563eb',
                        '#7c3aed',
                        '#ea580c',
                        '#475569'
                    ]
                }]
            },
            options: commonOptions
        });
    }

    const activeUsersCanvas = document.getElementById('adminMostActiveUsersChart');
    if (activeUsersCanvas) {
        new Chart(activeUsersCanvas, {
            type: 'bar',
            data: {
                labels: mostActiveUsers.labels ?? [],
                datasets: [{
                    label: 'Actions',
                    data: mostActiveUsers.values ?? [],
                    backgroundColor: '#0ea5e9'
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    const dailyCanvas = document.getElementById('adminDailyActivityChart');
    if (dailyCanvas) {
        new Chart(dailyCanvas, {
            type: 'line',
            data: {
                labels: dailyActivity.labels ?? [],
                datasets: [{
                    label: 'Audit Events',
                    data: dailyActivity.values ?? [],
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124,58,237,.10)',
                    fill: true,
                    tension: .3
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
});
</script>
@endpush
