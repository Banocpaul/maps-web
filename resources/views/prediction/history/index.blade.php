@extends('layouts.app')
@section('title', 'Prediction History | M.A.P.S')
@section('page-title', 'Prediction History')
@section('page-description', 'Saved flood prediction runs and staff remarks')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-bold text-slate-950">Prediction History</h1><p class="mt-1 text-sm text-slate-600">Review saved runs and add staff remarks.</p></div>
        @if (auth()->user()->hasPermission('prediction.run'))
            <a href="{{ route('prediction.index') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-sky-800">Run Prediction</a>
        @endif
    </div>

    <form method="GET" action="{{ route('prediction.history.index') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
        <div class="sm:col-span-2"><label for="history-search" class="mb-2 block text-sm font-medium">Search</label><input id="history-search" name="search" type="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Run number or staff name" class="w-full rounded-xl border-slate-300 text-sm"></div>
        <div><label for="forecast_hours" class="mb-2 block text-sm font-medium">Forecast window</label><select id="forecast_hours" name="forecast_hours" class="w-full rounded-xl border-slate-300 text-sm"><option value="">All windows</option>@foreach ([24, 48, 72] as $hours)<option value="{{ $hours }}" @selected((string) ($filters['forecast_hours'] ?? '') === (string) $hours)>{{ $hours }} hours</option>@endforeach</select></div>
        <div><label for="kind" class="mb-2 block text-sm font-medium">Run type</label><select id="kind" name="kind" class="w-full rounded-xl border-slate-300 text-sm"><option value="">All types</option>@foreach (['Forecast', 'Simulation'] as $kind)<option @selected(($filters['kind'] ?? '') === $kind)>{{ $kind }}</option>@endforeach</select></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_remark" value="1" @checked($filters['needs_remark'] ?? false) class="rounded border-slate-300">Forecasts without remarks</label>
        <div><label for="status" class="mb-2 block text-sm font-medium">Status</label><select id="status" name="status" class="w-full rounded-xl border-slate-300 text-sm"><option value="">All statuses</option>@foreach (['Running', 'Completed', 'Failed'] as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Apply</button><a href="{{ route('prediction.history.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Run</th><th class="px-4 py-3">Run time (Manila)</th><th class="px-4 py-3">Staff</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Window</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Remarks</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($runs as $run)
                    <tr class="hover:bg-slate-50"><td class="px-4 py-4 font-semibold">#{{ $run->id }}</td><td class="whitespace-nowrap px-4 py-4">{{ $run->requested_at->timezone('Asia/Manila')->format('M j, Y g:i:s A') }}</td><td class="px-4 py-4">{{ $run->requested_by_name }}</td><td class="px-4 py-4">{{ $run->kind }}</td><td class="whitespace-nowrap px-4 py-4">{{ $run->forecast_hours }} hours</td><td class="px-4 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800' => $run->status === 'Completed', 'bg-rose-100 text-rose-800' => $run->status === 'Failed', 'bg-amber-100 text-amber-800' => $run->status === 'Running'])>{{ $run->status }}</span></td><td class="px-4 py-4">{{ $run->remarks_count }}</td><td class="px-4 py-4"><a href="{{ route('prediction.history.show', $run) }}" class="font-semibold text-sky-700 hover:text-sky-900">Review</a></td></tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No saved runs match these filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
    {{ $runs->links() }}
</div>
@endsection
