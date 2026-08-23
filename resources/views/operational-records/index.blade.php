@extends('layouts.app')

@section('title', 'Operational Records | M.A.P.S.')

@section('content')
<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Operations Manager</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Operational Database Records</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">Search, review, and export approved operational records. Database credentials, authentication data, and protected system settings are never exposed here.</p>
        </div>

        @if (auth()->user()?->hasPermission('records.export'))
            <a href="{{ route('operational-records.export', request()->query()) }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">
                Export filtered CSV
            </a>
        @endif
    </header>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @foreach ($datasets as $key => $definition)
            @if ($datasetCounts[$key] !== null)
                <a href="{{ route('operational-records.index', ['dataset' => $key]) }}" @class([
                    'rounded-2xl border p-4 shadow-sm transition',
                    'border-sky-300 bg-sky-50 ring-2 ring-sky-100' => $datasetKey === $key,
                    'border-slate-200 bg-white hover:border-sky-200 hover:bg-slate-50' => $datasetKey !== $key,
                ])>
                    <p class="text-sm font-semibold text-slate-900">{{ $definition['label'] }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ number_format($datasetCounts[$key]) }}</p>
                    <p class="mt-1 text-xs text-slate-500">available records</p>
                </a>
            @endif
        @endforeach
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('operational-records.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <input type="hidden" name="dataset" value="{{ $datasetKey }}">

            <label class="xl:col-span-2">
                <span class="text-xs font-semibold text-slate-600">Search</span>
                <input name="search" value="{{ $filters['search'] }}" placeholder="ID, barangay, source, location..." class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </label>

            <label>
                <span class="text-xs font-semibold text-slate-600">Barangay</span>
                <select name="barangay_id" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected($filters['barangay_id'] === $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="text-xs font-semibold text-slate-600">Status / Risk</span>
                <select name="status" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500" @disabled($dataset['statuses'] === [])>
                    <option value="">All statuses</option>
                    @foreach ($dataset['statuses'] as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="text-xs font-semibold text-slate-600">From date</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </label>

            <label>
                <span class="text-xs font-semibold text-slate-600">To date</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </label>

            <div class="flex items-end gap-2 xl:col-span-6">
                <button class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Apply filters</button>
                <a href="{{ route('operational-records.index', ['dataset' => $datasetKey]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>

                @if ($dataset['crud_route'] && Route::has($dataset['crud_route']))
                    <a href="{{ route($dataset['crud_route']) }}" class="ml-auto rounded-xl border border-sky-300 px-4 py-2.5 text-sm font-semibold text-sky-700 hover:bg-sky-50">Open management interface</a>
                @endif
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-950">{{ $dataset['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ number_format($records->total()) }} matching records</p>
            </div>
            @if ($datasetKey === 'flood-records' && auth()->user()?->hasPermission('flood.create'))
                <a href="{{ route('operational-records.flood.create') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Add flood record</a>
            @endif
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Page {{ $records->currentPage() }} of {{ max($records->lastPage(), 1) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ($dataset['columns'] as $heading)
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $heading }}</th>
                        @endforeach
                        @if ($datasetKey === 'flood-records')
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($records as $record)
                        <tr class="hover:bg-slate-50">
                            @foreach (array_keys($dataset['columns']) as $column)
                                @php $value = data_get($record, $column); @endphp
                                <td class="max-w-xs whitespace-nowrap px-4 py-3 text-slate-700">
                                    @if (is_bool($value) || in_array($column, ['is_active', 'is_alert_triggered', 'receive_flood_alerts', 'receive_fire_alerts'], true))
                                        {{ (bool) $value ? 'Yes' : 'No' }}
                                    @elseif ($value === null || $value === '')
                                        <span class="text-slate-400">—</span>
                                    @else
                                        <span class="block max-w-xs truncate" title="{{ $value }}">{{ $value }}</span>
                                    @endif
                                </td>
                            @endforeach
                            @if ($datasetKey === 'flood-records')
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if (auth()->user()?->hasPermission('flood.edit'))
                                        <a class="font-semibold text-sky-700" href="{{ route('operational-records.flood.edit', $record->id) }}">Edit</a>
                                    @endif
                                    @if (auth()->user()?->hasPermission('flood.delete'))
                                        <form class="ml-3 inline" method="POST" action="{{ route('operational-records.flood.destroy', $record->id) }}" onsubmit="return confirm('Remove this flood record? This action is logged.');">
                                            @csrf @method('DELETE')
                                            <button class="font-semibold text-red-600">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($dataset['columns']) + ($datasetKey === 'flood-records' ? 1 : 0) }}" class="px-5 py-12 text-center text-slate-500">No records match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $records->links() }}</div>
        @endif
    </section>
</div>
@endsection
