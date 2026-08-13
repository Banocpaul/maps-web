@php
    $floodKpis = $floodDashboard['kpis'] ?? [];
    $monthlyTrend = $floodDashboard['monthly_trend'] ?? [];
    $riskDistribution = $floodDashboard['risk_distribution'] ?? [];
    $topBarangays = $floodDashboard['top_barangays'] ?? [];
    $recentFloods = collect($floodDashboard['recent_records'] ?? []);
    $temperature = data_get($liveWeather, 'avg_temp_mean_c');
    $humidity = data_get($liveWeather, 'avg_rh_pct');
    $rainfall24h = data_get($liveWeather, 'avg_rainfall_24h_mm');
    $rainfall3d = data_get($liveWeather, 'rainfall_3d_mm');
@endphp

@include('dashboard.partials.filters')

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm"><p class="text-sm font-medium text-sky-700">Flood Records</p><p class="mt-2 text-3xl font-bold text-sky-800">{{ number_format($floodKpis['total_records'] ?? 0) }}</p><p class="mt-2 text-xs text-sky-700">Records matching current filters</p></article>
    <article class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">High-Risk Records</p><p class="mt-2 text-3xl font-bold text-red-600">{{ number_format($floodKpis['high_risk_records'] ?? 0) }}</p><p class="mt-2 text-xs text-slate-500">Priority analytical observations</p></article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Rainfall — 24 Hours</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ $rainfall24h !== null ? number_format((float) $rainfall24h, 1).' mm' : 'N/A' }}</p><p class="mt-2 text-xs text-slate-500">3-day total: {{ $rainfall3d !== null ? number_format((float) $rainfall3d, 1).' mm' : 'N/A' }}</p></article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">Current Conditions</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ $temperature !== null ? number_format((float) $temperature, 1).'°C' : 'N/A' }}</p><p class="mt-2 text-xs text-slate-500">Humidity: {{ $humidity !== null ? number_format((float) $humidity, 1).'%' : 'N/A' }}</p></article>
</section>

@if ($liveWeatherError)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $liveWeatherError }} Historical analytics remain available.</div>
@endif

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="font-semibold text-slate-950">Analyst Shortcuts</h2><p class="mt-1 text-sm text-slate-500">Prediction, validation, mapping, and export tools</p></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('prediction.index') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Open Flood Prediction</a>
            <a href="{{ route('flood-operation.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Flood Operations</a>
            <a href="{{ route('gis.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Flood GIS Map</a>
        </div>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-3">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-950">Monthly Flood Trend</h2><div class="mt-4 h-72"><canvas id="floodMonthlyChart"></canvas></div></article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-950">Risk Distribution</h2><div class="mt-4 h-72"><canvas id="floodRiskChart"></canvas></div></article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-950">Top Flood-Prone Barangays</h2><div class="mt-4 h-72"><canvas id="floodBarangayChart"></canvas></div></article>
</section>

<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Recent Flood Records</h2><p class="mt-1 text-sm text-slate-500">Latest verified observations used for analysis</p></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Observed</th><th class="px-5 py-3">Barangay</th><th class="px-5 py-3">Risk</th><th class="px-5 py-3">Depth</th><th class="px-5 py-3">Duration</th><th class="px-5 py-3">Rainfall 24h</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentFloods as $record)
                    <tr><td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $record->observed_at?->format('M j, Y g:i A') }}</td><td class="px-5 py-3 font-medium text-slate-900">{{ $record->barangay }}</td><td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $record->risk_level }}</span></td><td class="px-5 py-3 text-slate-600">{{ number_format((float) $record->flood_depth_mm, 1) }} mm</td><td class="px-5 py-3 text-slate-600">{{ number_format((float) $record->duration_hours, 1) }} hrs</td><td class="px-5 py-3 text-slate-600">{{ number_format((float) $record->rainfall_24h_mm, 1) }} mm</td></tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No flood records match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const monthly = @json($monthlyTrend), risk = @json($riskDistribution), top = @json($topBarangays);
    const options = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
    new Chart(document.getElementById('floodMonthlyChart'), {type:'line',data:{labels:monthly.labels ?? [],datasets:[{label:'Flood Records',data:monthly.records ?? monthly.values ?? monthly.counts ?? [],borderColor:'#0369a1',backgroundColor:'rgba(3,105,161,.1)',fill:true,tension:.3}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('floodRiskChart'), {type:'doughnut',data:{labels:risk.labels ?? [],datasets:[{data:risk.values ?? risk.records ?? risk.counts ?? [],backgroundColor:['#22c55e','#f59e0b','#dc2626']}]},options});
    new Chart(document.getElementById('floodBarangayChart'), {type:'bar',data:{labels:top.labels ?? [],datasets:[{label:'Flood Records',data:top.records ?? top.values ?? top.counts ?? [],backgroundColor:'#0284c7'}]},options:{...options,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
});
</script>
@endpush
