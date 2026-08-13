@extends('layouts.app')

@section('title', 'Activity Logs | M.A.P.S.')
@section('page-title', 'Activity Logs')
@section('page-description', 'Administrator-only security and system audit trail')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">
                Administration
            </p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
                User Activity Logs
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Review authenticated actions, security events, affected modules,
                devices, IP addresses, and request details.
            </p>
        </div>
        <span class="inline-flex w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">
            Administrator access only
        </span>
    </div>

    <section class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">All recorded activities</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalLogs) }}</p>
        </article>
        <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
            <p class="text-sm text-sky-700">Activities today</p>
            <p class="mt-2 text-3xl font-bold text-sky-950">{{ number_format($todayLogs) }}</p>
        </article>
        <article class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
            <p class="text-sm text-rose-700">Failed login attempts</p>
            <p class="mt-2 text-3xl font-bold text-rose-950">{{ number_format($failedLogins) }}</p>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route('activity-logs.index') }}" class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label class="text-xs font-semibold uppercase text-slate-500" for="search">Search</label>
                <input id="search" name="search" value="{{ request('search') }}" placeholder="User, action, route or IP" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-slate-500" for="user_id">User</label>
                <select id="user_id" name="user_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->full_name ?: $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-slate-500" for="module">Module</label>
                <select id="module" name="module" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucwords(str_replace('-', ' ', $module)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-slate-500" for="action">Action</label>
                <select id="action" name="action" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ ucwords(str_replace('_', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Filter</button>
                <a href="{{ route('activity-logs.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Reset</a>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-slate-500" for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="text-xs font-semibold uppercase text-slate-500" for="date_to">Until</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Date and time</th>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Action</th>
                        <th class="px-5 py-3">Module</th>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-5 py-3">IP / Device</th>
                        <th class="px-5 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($logs as $log)
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                {{ $log->created_at->timezone('Asia/Manila')->format('M d, Y') }}
                                <div class="text-xs text-slate-400">{{ $log->created_at->timezone('Asia/Manila')->format('h:i:s A') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900">{{ $log->user_name ?? 'Guest user' }}</div>
                                <div class="text-xs text-slate-500">{{ $log->role_name ?? 'No role' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ ucwords(str_replace('_', ' ', $log->action)) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ ucwords(str_replace('-', ' ', $log->module)) }}</td>
                            <td class="max-w-sm px-5 py-4 text-slate-700">
                                {{ $log->description }}
                                @if ($log->route_name)
                                    <div class="mt-1 font-mono text-[11px] text-slate-400">{{ $log->http_method }} {{ $log->route_name }}</div>
                                @endif
                            </td>
                            <td class="max-w-xs px-5 py-4 text-xs text-slate-600">
                                <div class="font-mono">{{ $log->ip_address ?? 'Unknown' }}</div>
                                <div class="mt-1 line-clamp-2 text-slate-400" title="{{ $log->user_agent }}">{{ $log->user_agent ?? 'Unknown device' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($log->new_values || $log->old_values)
                                    <details>
                                        <summary class="cursor-pointer text-xs font-semibold text-sky-700">View data</summary>
                                        <pre class="mt-2 max-h-64 w-72 overflow-auto rounded-lg bg-slate-950 p-3 text-[11px] text-slate-100">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="text-xs text-slate-400">No request data</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No activity logs matched the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $logs->links() }}</div>
        @endif
    </section>
</div>
@endsection
