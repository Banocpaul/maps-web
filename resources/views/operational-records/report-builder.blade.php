@extends('layouts.app')

@section('title', 'Flood Report Builder | M.A.P.S.')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Interactive Analytics</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Flood Report Builder</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">Mix flood attributes into rows and columns, select a calculation, and generate a heatmap summary without changing the original records.</p>
        </div>
        <a href="{{ route('operational-records.index', ['dataset' => 'flood-records']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to records</a>
    </header>

    <form method="GET" action="{{ route('operational-records.report-builder') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid gap-5 xl:grid-cols-[1.2fr_1fr]">
            <section>
                <h2 class="font-semibold text-slate-950">Report layout</h2>
                <p class="mt-1 text-xs text-slate-500">Choose up to two row attributes and one column attribute.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Primary row</span>
                        <select name="row_primary" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach ($availableDimensions as $key => $label)
                                <option value="{{ $key }}" @selected($configuration['row_primary'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Secondary row</span>
                        <select name="row_secondary" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">None</option>
                            @foreach ($availableDimensions as $key => $label)
                                <option value="{{ $key }}" @selected($configuration['row_secondary'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Column</span>
                        <select name="column" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">None</option>
                            @foreach ($availableDimensions as $key => $label)
                                <option value="{{ $key }}" @selected($configuration['column'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Value</span>
                        <select name="measure" id="report-measure" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach ($availableMeasures as $key => $label)
                                <option value="{{ $key }}" @selected($configuration['measure'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Calculation</span>
                        <select name="aggregation" id="report-aggregation" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            @foreach (['count' => 'Count', 'avg' => 'Average', 'sum' => 'Sum', 'min' => 'Minimum', 'max' => 'Maximum'] as $key => $label)
                                <option value="{{ $key }}" @selected($configuration['aggregation'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
                <h2 class="font-semibold text-slate-950">Filters</h2>
                <p class="mt-1 text-xs text-slate-500">Limit which flood records are included.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Barangay</span>
                        <select name="barangay" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">All barangays</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay }}" @selected($configuration['barangay'] === $barangay)>{{ $barangay }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">Risk level</span>
                        <select name="risk_level" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">All risk levels</option>
                            @foreach (['Low', 'Medium', 'High'] as $risk)
                                <option value="{{ $risk }}" @selected($configuration['risk_level'] === $risk)>{{ $risk }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">From date</span>
                        <input type="date" name="date_from" value="{{ $configuration['date_from'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <label>
                        <span class="text-xs font-semibold text-slate-600">To date</span>
                        <input type="date" name="date_to" value="{{ $configuration['date_to'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                </div>
            </section>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
            <button class="rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Generate report</button>
            <a href="{{ route('operational-records.report-builder') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            <p class="text-xs text-slate-500">The report uses approved fields only and never alters database records.</p>
        </div>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-950">Generated heatmap</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $availableMeasures[$configuration['measure']] }} — {{ ucfirst($configuration['aggregation']) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ($rowDimensions as $dimension)
                            <th class="whitespace-nowrap border-b border-r border-slate-200 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $availableDimensions[$dimension] }}</th>
                        @endforeach
                        @foreach ($columnKeys as $columnKey)
                            <th class="whitespace-nowrap border-b border-slate-200 px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $columnKey }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pivotRows as $row)
                        <tr>
                            @foreach ($rowDimensions as $dimension)
                                <th class="whitespace-nowrap border-b border-r border-slate-200 bg-slate-50/70 px-4 py-3 text-left font-medium text-slate-700">{{ $row['dimensions'][$dimension] }}</th>
                            @endforeach
                            @foreach ($columnKeys as $columnKey)
                                @php
                                    $value = $row['values'][$columnKey] ?? null;
                                    $intensity = $value !== null && $maximumValue > 0
                                        ? 0.08 + (0.72 * ($value / $maximumValue))
                                        : 0;
                                @endphp
                                <td class="border-b border-slate-200 px-4 py-3 text-right font-semibold text-slate-800" @if ($value !== null) style="background-color: rgba(239, 68, 68, {{ number_format($intensity, 3, '.', '') }});" @endif>
                                    {{ $value === null ? '—' : number_format($value, $configuration['aggregation'] === 'count' ? 0 : 2) }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(count($rowDimensions) + count($columnKeys), 1) }}" class="px-5 py-12 text-center text-slate-500">No flood records match this report configuration.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4 text-xs text-slate-500">
            <span>{{ number_format(count($pivotRows)) }} grouped rows</span>
            <span class="inline-flex items-center gap-2"><span>Lower</span><span class="h-3 w-24 rounded bg-gradient-to-r from-red-50 to-red-500"></span><span>Higher</span></span>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const measure = document.getElementById('report-measure');
        const aggregation = document.getElementById('report-aggregation');

        const synchronizeAggregation = () => {
            const countingRecords = measure.value === 'records';
            aggregation.disabled = countingRecords;

            if (countingRecords) {
                aggregation.value = 'count';
            }
        };

        measure.addEventListener('change', synchronizeAggregation);
        synchronizeAggregation();
    });
</script>
@endsection
