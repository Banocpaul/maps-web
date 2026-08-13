@php
    $recentActivity = $adminDashboard['recent_activity'] ?? collect();
    $roles = $adminDashboard['roles'] ?? collect();
@endphp

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Active Users</p>
        <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($adminDashboard['active_users'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">{{ number_format($adminDashboard['inactive_users'] ?? 0) }} inactive accounts</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Failed Logins Today</p>
        <p class="mt-2 text-3xl font-bold text-red-600">{{ number_format($adminDashboard['failed_logins_today'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">Review unusual authentication activity</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">SMS Sent Today</p>
        <p class="mt-2 text-3xl font-bold text-emerald-600">{{ number_format($adminDashboard['sms_sent_today'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">Successful gateway deliveries</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">SMS Failed Today</p>
        <p class="mt-2 text-3xl font-bold text-amber-600">{{ number_format($adminDashboard['sms_failed_today'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">Messages requiring review</p>
    </article>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-950">Recent System Activity</h2>
                <p class="mt-1 text-sm text-slate-500">Latest auditable actions and security events</p>
            </div>
            @if (Route::has('activity-logs.index'))
                <a href="{{ route('activity-logs.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">View all</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">User</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Module</th><th class="px-5 py-3">Time</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentActivity as $activity)
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900">{{ $activity->user_name ?? 'Guest user' }}</td>
                            <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($activity->action)->replace('_', ' ')->title() }}</span></td>
                            <td class="px-5 py-3 text-slate-600">{{ str($activity->module)->replace('-', ' ')->title() }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $activity->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No activity has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <div class="space-y-6">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Administrative Actions</h2>
            <div class="mt-4 grid gap-3">
                @if (Route::has('users.create'))
                    <a href="{{ route('users.create') }}" class="rounded-xl bg-sky-700 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-sky-800">Add User</a>
                @endif
                @if (Route::has('users.index'))
                    <a href="{{ route('users.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Manage Users</a>
                @endif
                @if (Route::has('activity-logs.index'))
                    <a href="{{ route('activity-logs.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Review Activity Logs</a>
                @endif
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold text-slate-950">Users by Role</h2>
            <div class="mt-4 space-y-3">
                @foreach ($roles as $role)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                        <span class="text-sm text-slate-700">{{ $role->name }}</span>
                        <strong class="text-slate-950">{{ number_format($role->users_count) }}</strong>
                    </div>
                @endforeach
            </div>
        </article>
    </div>
</section>
