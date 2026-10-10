@extends('layouts.app')

@section('title', 'Incident Analytics | M.A.P.S')
@section('page-title', 'Incident Analytics')
@section('page-description', 'Fire and flood incident trends and performance')

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Instrument Sans', sans-serif";
        Chart.defaults.color = '#64748b';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.register({id:'analyticsEmptyState',afterDraw(chart) {
            const hasData = chart.data.datasets.some(ds => ds.data.some(value => value !== null && (typeof value === 'object' || Number(value) > 0)));
            if (hasData) return;
            const {ctx,chartArea} = chart;
            if (!chartArea) return;
            ctx.save();ctx.fillStyle='#64748b';ctx.textAlign='center';ctx.font='13px sans-serif';
            ctx.fillText('No measurable data for this selection', (chartArea.left+chartArea.right)/2, (chartArea.top+chartArea.bottom)/2);ctx.restore();
        }});
    }
    </script>
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

<section id="incident-analytics" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-sm">
    <div class="border-b border-slate-200 bg-white px-5 py-5">
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
            @include('dashboard.partials.fire-analytics')
            @include('incident-analytics.sla', ['hazard' => 'fire', 'sla' => $fireSla ?? []])
        </div>

        @endif
        @if ($showFlood)
        <div id="flood-analytics-panel" data-analytics-panel="flood" @class(['hidden' => $selectedAnalytics !== 'flood']) role="tabpanel" aria-label="Flood analytics">
            @include('dashboard.partials.flood-analytics')
            @include('incident-analytics.flood-insights')
            @include('incident-analytics.sla', ['hazard' => 'flood', 'sla' => $floodSla ?? []])
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
