@extends('layouts.app')
@section('title', 'Prediction Run #'.$execution->id.' | M.A.P.S')
@section('page-title', 'Prediction Run #'.$execution->id)
@section('page-description', 'Saved results and staff review')

@section('content')
@php
    $result = $execution->result_snapshot ?? [];
    $predictions = collect($result['predictions'] ?? []);
    $counts = $predictions->countBy(fn ($item) => $item['flood_code'] ?? 'Unknown');
    $window = $result['selected_forecast'] ?? data_get($execution->input_snapshot, 'weather_context.forecast_windows.'.$execution->forecast_hours, []);
    $colors = ['A' => '#39FF14', 'B' => '#FFF200', 'C' => '#FF9500', 'D' => '#FF3B1F'];
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-2xl font-bold text-slate-950">Prediction Run #{{ $execution->id }}</h1><p class="mt-1 text-sm text-slate-600">{{ $execution->kind }} · {{ $execution->forecast_hours }} hours · {{ $execution->status }}</p></div>
        <a href="{{ route('prediction.history.index') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-slate-50">All Runs</a>
    </div>

    <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
        <div><p class="text-xs font-semibold uppercase text-slate-500">Run by</p><p class="mt-1 font-semibold">{{ $execution->requested_by_name }}</p></div>
        <div><p class="text-xs font-semibold uppercase text-slate-500">Run time (Manila)</p><p class="mt-1 text-sm">{{ $execution->requested_at->timezone('Asia/Manila')->format('M j, Y g:i:s A') }}</p></div>
        <div><p class="text-xs font-semibold uppercase text-slate-500">Completed (Manila)</p><p class="mt-1 text-sm">{{ $execution->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'In progress' }}</p></div>
        <div><p class="text-xs font-semibold uppercase text-slate-500">Barangays</p><p class="mt-1 font-semibold">{{ $predictions->count() }}</p></div>
        @if ($window)
            <div class="sm:col-span-2"><p class="text-xs font-semibold uppercase text-slate-500">Forecast validity</p><p class="mt-1 text-sm">{{ $window['start_display'] ?? $window['start'] ?? 'Unavailable' }} — {{ $window['end_display'] ?? $window['end'] ?? 'Unavailable' }}</p></div>
            @if (is_numeric($window['rainfall_mm'] ?? null))
                <div><p class="text-xs font-semibold uppercase text-slate-500">Forecast rainfall</p><p class="mt-1 text-sm">{{ number_format((float) $window['rainfall_mm'], 2) }} mm</p></div>
            @endif
        @endif
    </section>

    @if ($execution->kind === 'Simulation')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Hypothetical rainfall simulation. This is not a live weather forecast.</div>
    @endif
    @if ($execution->status === 'Failed')
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Run failed: {{ $execution->error_message }}</div>
    @elseif ($execution->status === 'Running')
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">This run has no completed results yet.</div>
    @endif

    @if ($execution->status === 'Completed')
        <div class="grid gap-4 sm:grid-cols-4">
            @foreach (['A', 'B', 'C', 'D'] as $code)
                <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm font-semibold text-slate-600"><span class="mr-2 inline-block h-3 w-3 rounded-full" style="background-color: {{ $colors[$code] }}"></span>Level {{ $code }}</p><p class="mt-1 text-3xl font-bold">{{ $counts[$code] ?? 0 }}</p></div>
            @endforeach
        </div>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5"><h2 class="font-semibold text-slate-950">Saved Barangay Results</h2><p class="mt-1 text-sm text-slate-500">Confidence is the model probability of the displayed flood code.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Rank</th><th class="px-5 py-3">Barangay</th><th class="px-5 py-3">Flood Severity</th><th class="px-5 py-3">Confidence</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($predictions as $index => $item)
                        @php
                            $code = $item['flood_code'] ?? 'Unknown';
                            $description = match ($code) {'A' => 'Minor (0.5 ft)', 'B' => 'Moderate (1.5 ft)', 'C' => 'Severe (2.0 ft)', 'D' => 'Critical (2.5 ft+)', default => 'Unavailable'};
                        @endphp
                        <tr><td class="px-5 py-4">{{ $item['rank'] ?? $index + 1 }}</td><td class="px-5 py-4 font-semibold">{{ $item['barangay'] ?? 'Unknown' }}</td><td class="px-5 py-4"><span class="mr-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold text-slate-950" style="background-color: {{ $colors[$code] ?? '#e2e8f0' }}">{{ $code }}</span>{{ $description }}</td><td class="px-5 py-4">@include('prediction.partials.confidence', ['item' => $item])</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-slate-950">Staff Remarks</h2>
        <div class="mt-4 space-y-4">
            @forelse ($execution->remarks as $remark)
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4"><p class="text-sm font-semibold">{{ $remark->author_name }} <span class="font-normal text-slate-500">· {{ $remark->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span></p><p class="mt-1 text-xs font-semibold text-sky-700">{{ $remark->barangay ?? 'Whole run' }}</p><p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $remark->body }}</p></article>
            @empty
                <p class="text-sm text-slate-500">No remarks yet.</p>
            @endforelse
        </div>
        @if (auth()->user()->hasPermission('prediction.review'))
            <form method="POST" action="{{ route('prediction.history.remarks', $execution) }}" class="mt-6 space-y-3">
                @csrf
                <div><label for="barangay" class="mb-2 block text-sm font-medium">Remark applies to</label><select name="barangay" id="barangay" class="w-full rounded-xl border-slate-300 text-sm sm:max-w-sm"><option value="">Whole run</option>@foreach ($predictions->pluck('barangay')->filter()->unique()->sort() as $barangay)<option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>@endforeach</select></div>
                <div><label for="body" class="mb-2 block text-sm font-medium">Remark</label><textarea name="body" id="body" rows="4" maxlength="5000" required class="w-full rounded-xl border-slate-300 text-sm" placeholder="Add observations or follow-up actions.">{{ old('body') }}</textarea></div>
                <button class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Add Remark</button>
            </form>
        @endif
    </section>

    <details class="rounded-2xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer text-sm font-semibold">Complete Saved Results</summary><pre class="mt-4 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-xs">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
    <details class="rounded-2xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer text-sm font-semibold">Weather and Run Inputs</summary><pre class="mt-4 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-xs">{{ json_encode($execution->input_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
</div>
@endsection
