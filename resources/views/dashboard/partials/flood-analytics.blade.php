@php
    $floodMonthly = $floodDashboard['monthly_trend'] ?? [];
    $floodRisk = $floodDashboard['risk_distribution'] ?? [];
    $floodBarangays = $floodDashboard['top_barangays'] ?? [];
@endphp

<section class="mt-6" aria-labelledby="flood-analytics-heading">
    <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Flood analytics</p>
        <h2 id="flood-analytics-heading" class="mt-1 text-xl font-bold text-slate-950">Flood Risk Intelligence</h2>
        <p class="mt-1 text-sm text-slate-500">Calculated exclusively from recorded flood observations matching the selected filters.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Month-over-Month Flood Records</h3><p class="mt-1 text-xs text-slate-500">Recorded flood observations per month</p><div class="mt-4 h-72"><canvas id="floodMonthlyChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Risk Distribution</h3><p class="mt-1 text-xs text-slate-500">Low, medium, and high-risk observations</p><div class="mt-4 h-72"><canvas id="floodRiskChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Top Flood-Prone Barangays</h3><p class="mt-1 text-xs text-slate-500">Barangays ranked by recorded flood observations</p><div class="mt-4 h-72"><canvas id="floodBarangayChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Monthly Depth and Rainfall</h3><p class="mt-1 text-xs text-slate-500">Average flood depth and 24-hour rainfall in millimeters</p><div class="mt-4 h-72"><canvas id="floodDepthRainChart"></canvas></div></article>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const monthly = @json($floodMonthly), risk = @json($floodRisk), barangays = @json($floodBarangays);
    const options = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
    new Chart(document.getElementById('floodMonthlyChart'), {type:'line',data:{labels:monthly.labels ?? [],datasets:[{label:'Flood Records',data:monthly.records ?? [],borderColor:'#0369a1',backgroundColor:'rgba(3,105,161,.12)',fill:true,tension:.3}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('floodRiskChart'), {type:'doughnut',data:{labels:risk.labels ?? [],datasets:[{data:risk.values ?? [],backgroundColor:['#22c55e','#f59e0b','#dc2626']}]},options});
    new Chart(document.getElementById('floodBarangayChart'), {type:'bar',data:{labels:barangays.labels ?? [],datasets:[{label:'Flood Records',data:barangays.records ?? barangays.values ?? [],backgroundColor:'#0284c7'}]},options:{...options,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('floodDepthRainChart'), {type:'line',data:{labels:monthly.labels ?? [],datasets:[{label:'Average Flood Depth (mm)',data:monthly.average_depth_mm ?? [],borderColor:'#1d4ed8',backgroundColor:'rgba(29,78,216,.1)',tension:.3},{label:'Average Rainfall 24h (mm)',data:monthly.average_rainfall_24h_mm ?? [],borderColor:'#06b6d4',backgroundColor:'rgba(6,182,212,.1)',tension:.3}]},options:{...options,scales:{y:{beginAtZero:true}}}});
});
</script>
@endpush
