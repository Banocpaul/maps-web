@php
    $fireMonthly = $fireDashboard['monthly_trend'] ?? [];
    $fireSeverity = $fireDashboard['severity_distribution'] ?? [];
    $fireBarangays = $fireDashboard['top_barangays'] ?? [];
    $fireTimeOfDay = $fireDashboard['time_distribution'] ?? [];
@endphp

<section class="mt-6" aria-labelledby="fire-analytics-heading">
    <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-red-700">Fire analytics</p>
        <h2 id="fire-analytics-heading" class="mt-1 text-xl font-bold text-slate-950">Fire Incident Intelligence</h2>
        <p class="mt-1 text-sm text-slate-500">Calculated exclusively from recorded fire incidents matching the selected filters.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Month-over-Month Incidents</h3><p class="mt-1 text-xs text-slate-500">Recorded fire incidents per month</p><div class="mt-4 h-72"><canvas id="fireMonthlyChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Severity Distribution</h3><p class="mt-1 text-xs text-slate-500">Minor, moderate, and major incidents</p><div class="mt-4 h-72"><canvas id="fireSeverityChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Top Incident Barangays</h3><p class="mt-1 text-xs text-slate-500">Barangays ranked by recorded incident count</p><div class="mt-4 h-72"><canvas id="fireBarangayChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Incidents by Time of Day</h3><p class="mt-1 text-xs text-slate-500">Morning, afternoon, evening, and night</p><div class="mt-4 h-72"><canvas id="fireTimeChart"></canvas></div></article>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const monthly = @json($fireMonthly), severity = @json($fireSeverity), barangays = @json($fireBarangays), time = @json($fireTimeOfDay);
    const options = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
    new Chart(document.getElementById('fireMonthlyChart'), {type:'line',data:{labels:monthly.labels ?? [],datasets:[{label:'Fire Incidents',data:monthly.incidents ?? [],borderColor:'#dc2626',backgroundColor:'rgba(220,38,38,.12)',fill:true,tension:.3}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireSeverityChart'), {type:'doughnut',data:{labels:severity.labels ?? [],datasets:[{data:severity.values ?? [],backgroundColor:['#fbbf24','#f97316','#dc2626']}]},options});
    new Chart(document.getElementById('fireBarangayChart'), {type:'bar',data:{labels:barangays.labels ?? [],datasets:[{label:'Fire Incidents',data:barangays.incidents ?? [],backgroundColor:'#ef4444'}]},options:{...options,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireTimeChart'), {type:'bar',data:{labels:time.labels ?? [],datasets:[{label:'Fire Incidents',data:time.values ?? [],backgroundColor:['#f59e0b','#fb923c','#ef4444','#7f1d1d']}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
});
</script>
@endpush
