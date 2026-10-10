@extends('layouts.app')

@section('title', 'Incident Analytics | Mandaluyong Flood & Fire')
@section('page-title', 'Incident Analytics')
@section('page-description', 'Fire and flood incident trends and performance')

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('content')
@php
    $fireKpis = $fireDashboard['kpis'] ?? [];
    $floodKpis = $floodDashboard['kpis'] ?? [];
    $activeIncidents = collect($fireOperations['recent_active'] ?? []);
    $recentFloods = collect($floodDashboard['recent_records'] ?? []);
    $showFire = in_array($roleSlug, ['fire-responder', 'operations-manager'], true);
    $showFlood = in_array($roleSlug, ['flood-analyst', 'operations-manager'], true);
    $selectedAnalytics = $selectedAnalytics ?? ($roleSlug === 'flood-analyst' ? 'flood' : 'fire');
    $rainfall24h = data_get($liveWeather, 'rainfall_24h_mm');
    $temperature = data_get($liveWeather, 'avg_temp_mean_c');
    $humidity = data_get($liveWeather, 'avg_rh_pct');
@endphp

<section id="incident-analytics" class="overflow-hidden rounded-2xl border border-blue-200 bg-blue-50/60 shadow-sm">
    <div class="border-b border-blue-200 bg-gradient-to-r from-blue-50 via-sky-50 to-cyan-50 px-5 py-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">{{ $assignedRole->name }}</p>
                <h1 class="mt-1 text-xl font-semibold text-blue-950">Incident Analytics</h1>
                <p class="mt-1 text-sm text-blue-700">Fire and flood trends, records, and performance.</p>
            </div>
            @if ($showFire && $showFlood)
            <div class="inline-flex w-full rounded-xl border border-blue-200 bg-white/90 p-1 shadow-sm sm:w-auto" role="tablist" aria-label="Incident analytics">
                <button type="button" id="fire-analytics-tab" class="analytics-tab flex-1 rounded-lg px-5 py-2.5 text-sm font-semibold transition duration-200 sm:flex-none" data-analytics-target="fire" role="tab" aria-controls="fire-analytics-panel">Fire Operations</button>
                <button type="button" id="flood-analytics-tab" class="analytics-tab flex-1 rounded-lg px-5 py-2.5 text-sm font-semibold transition duration-200 sm:flex-none" data-analytics-target="flood" role="tab" aria-controls="flood-analytics-panel">Flood Operations</button>
            </div>
            @endif
        </div>
    </div>

    <div class="bg-white/75 p-4 sm:p-5">
        {{-- The hidden analytics field in this form keeps Apply and Reset on the selected hazard. --}}
        @include('dashboard.partials.filters', ['filterRoute' => 'incident-analytics.index'])

        @if ($showFire)
        <div id="fire-analytics-panel" data-analytics-panel="fire" @class(['hidden' => $selectedAnalytics !== 'fire']) role="tabpanel" aria-label="Fire analytics">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm"><p class="text-sm font-medium text-red-700">Active Fire Incidents</p><p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($fireOperations['active'] ?? 0) }}</p><p class="mt-2 text-xs text-red-600">Currently open citywide operations</p></article>
                <article class="rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm"><p class="text-sm font-medium text-orange-700">Major Incidents</p><p class="mt-2 text-3xl font-bold text-orange-700">{{ number_format($fireKpis['major_incidents'] ?? 0) }}</p><p class="mt-2 text-xs text-orange-600">Matches the selected filters</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Fire Resolution Rate</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ $fireKpis['resolution_rate'] ?? 0 }}%</p><p class="mt-2 text-xs text-slate-500">Resolved historical incidents</p></article>
                <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Average Fire Duration</p><p class="mt-2 text-3xl font-bold text-amber-700">{{ $fireKpis['average_duration_label'] ?? 'N/A' }}</p><p class="mt-2 text-xs text-slate-500">For incidents with duration data</p></article>
            </section>

            <section class="mt-6 rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-semibold text-slate-950">Fire Command Queue</h3><p class="mt-1 text-sm text-slate-500">Open incidents requiring continued coordination</p></div>
                    <div class="flex flex-wrap gap-2"><a href="{{ route('fire-incidents.index') }}" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Fire Incidents</a><a href="{{ route('fire-hydrants.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Fire Hydrants</a></div>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($activeIncidents->take(6) as $incident)
                        <a href="{{ route('fire-incidents.show', $incident) }}" class="flex items-center justify-between gap-4 px-3 py-3 transition hover:bg-slate-50"><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $incident->incident_number }} · {{ $incident->barangay?->name ?? 'Unknown' }}</p><p class="mt-1 truncate text-sm text-slate-500">{{ $incident->location }}</p></div><span class="flex-none rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $incident->status }}</span></a>
                    @empty
                        <p class="px-3 py-8 text-center text-sm text-slate-500">There are no active fire incidents.</p>
                    @endforelse
                </div>
            </section>

            @include('dashboard.partials.fire-analytics')
        </div>

        @endif
        @if ($showFlood)
        <div id="flood-analytics-panel" data-analytics-panel="flood" @class(['hidden' => $selectedAnalytics !== 'flood']) role="tabpanel" aria-label="Flood analytics">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm"><p class="text-sm font-medium text-sky-700">Flood Records</p><p class="mt-2 text-3xl font-bold text-sky-800">{{ number_format($floodKpis['total_records'] ?? 0) }}</p><p class="mt-2 text-xs text-sky-700">Matches the selected filters</p></article>
                <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm"><p class="text-sm font-medium text-red-700">High-Risk Flood Records</p><p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($floodKpis['high_risk_records'] ?? 0) }}</p><p class="mt-2 text-xs text-red-600">Priority analytical observations</p></article>
                <article class="rounded-2xl border border-cyan-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Rainfall — 24 Hours</p><p class="mt-2 text-3xl font-bold text-cyan-700">{{ $rainfall24h !== null ? number_format((float) $rainfall24h, 1).' mm' : 'N/A' }}</p><p class="mt-2 text-xs text-slate-500">Current citywide weather</p></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Temperature</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ $temperature !== null ? number_format((float) $temperature, 1).'°C' : 'N/A' }}</p><p class="mt-2 text-xs text-slate-500">Temperature: {{ $temperature !== null ? number_format((float) $temperature, 1).'°C' : 'N/A' }} · Humidity: {{ $humidity !== null ? number_format((float) $humidity, 1).'%' : 'N/A' }}</p></article>
            </section>

            <section class="mt-6 rounded-2xl border border-sky-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 class="font-semibold text-slate-950">Latest Flood Intelligence</h3><p class="mt-1 text-sm text-slate-500">Recent verified barangay observations</p></div>
                    <div class="flex flex-wrap gap-2"><a href="{{ route('prediction.index') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Flood Prediction</a><a href="{{ route('flood-operation.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Flood Operations</a></div>
                </div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($recentFloods->take(6) as $record)
                        @php
                            $recordBarangay = data_get($record, 'barangay', 'Unknown barangay');
                            $recordCode = data_get($record, 'flood_code', 'N/A');
                            $recordRisk = data_get($record, 'risk_level', 'Unknown');
                        @endphp
                        <div class="flex items-center justify-between gap-4 px-3 py-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $recordBarangay }}</p><p class="mt-1 text-sm text-slate-500">Flood code {{ $recordCode }}</p></div><span @class(['flex-none rounded-full px-2.5 py-1 text-xs font-semibold','bg-red-100 text-red-800' => $recordRisk === 'High','bg-amber-100 text-amber-800' => $recordRisk === 'Medium','bg-emerald-100 text-emerald-800' => $recordRisk === 'Low','bg-slate-100 text-slate-700' => !in_array($recordRisk, ['High', 'Medium', 'Low'], true),])>{{ $recordRisk }}</span></div>
                    @empty
                        <p class="px-3 py-8 text-center text-sm text-slate-500">No flood records match the selected filters.</p>
                    @endforelse
                </div>
            </section>

            @include('dashboard.partials.flood-analytics')
        </div>
        @endif
    </div>
</section>

@if ($liveWeatherError)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $liveWeatherError }} Historical analytics remain available.</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('incident-analytics');
    if (!container) return;

    const tabs = [...container.querySelectorAll('.analytics-tab')];
    const panels = [...container.querySelectorAll('[data-analytics-panel]')];
    const filterInput = container.querySelector('[data-analytics-filter-input]');
    const initialAnalytics = @json($selectedAnalytics);

    const setActiveAnalytics = (target, updateUrl = true) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.analyticsTarget === target;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
            tab.classList.toggle('bg-blue-600', active);
            tab.classList.toggle('text-white', active);
            tab.classList.toggle('shadow-sm', active);
            tab.classList.toggle('text-blue-700', !active);
            tab.classList.toggle('hover:bg-blue-100', !active);
        });
        panels.forEach((panel) => {
            const active = panel.dataset.analyticsPanel === target;
            panel.classList.toggle('hidden', !active);
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
        if (filterInput) filterInput.value = target;
        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('analytics', target);
            window.history.replaceState({}, '', url);
        }
        window.requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => setActiveAnalytics(tab.dataset.analyticsTarget));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const nextIndex = (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[nextIndex].focus();
            setActiveAnalytics(tabs[nextIndex].dataset.analyticsTarget);
        });
    });
    setActiveAnalytics(initialAnalytics, false);
});
</script>
@endpush
