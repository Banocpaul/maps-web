@php
    $fireMonthly = $fireDashboard['monthly_trend'] ?? [];
    $fireSeverity = $fireDashboard['cause_distribution'] ?? [];
    $fireBarangays = $fireDashboard['top_barangays'] ?? [];
    $fireTimeOfDay = $fireDashboard['time_distribution'] ?? [];
@endphp

<section class="mt-6" aria-labelledby="fire-analytics-heading">
    <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-red-700">Fire analytics</p>
        <h2 id="fire-analytics-heading" class="mt-1 text-xl font-bold text-slate-950">Fire Incident Intelligence</h2>
        <p class="mt-1 text-sm text-slate-500">Current fire records matching the selected filters.</p>
        <p class="mt-1 text-sm text-slate-500">Impact totals include finalized records. {{ $fireDashboard['kpis']['for_assessment'] ?? 0 }} records await assessment.</p>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach(['total_incidents' => 'Fire Incidents', 'individuals_affected' => 'Individuals Affected', 'houses_destroyed' => 'Houses Destroyed', 'finalized_records' => 'Finalized Records', 'for_assessment' => 'Awaiting Assessment', 'active_incidents' => 'Active Incidents'] as $field => $label)
            <article @class(['rounded-2xl border p-5 shadow-sm', 'border-slate-900 bg-slate-900 text-white' => $loop->first, 'border-slate-200 bg-white' => ! $loop->first])><p class="text-sm opacity-70">{{ $label }}</p><strong class="mt-1 block text-2xl">{{ $fireDashboard['kpis'][$field] ?? 'Unavailable' }}</strong></article>
        @endforeach
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Incidents by Month</h3><p class="mt-1 text-xs text-slate-500">Recorded fire incidents per calendar month; all-years selection combines the same months across years</p><div class="mt-4 h-72"><canvas id="fireMonthlyChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Cause Distribution</h3><p class="mt-1 text-xs text-slate-500">Recorded causes, including unknown or pending investigation</p><div class="mt-4 h-72"><canvas id="fireCauseChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Incidents by Barangay</h3><p class="mt-1 text-xs text-slate-500">Barangays ranked by recorded incident count</p><div class="mt-4 h-[520px]"><canvas id="fireBarangayChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Incidents by Time of Day</h3><p class="mt-1 text-xs text-slate-500">Morning, afternoon, evening, and night</p><div class="mt-4 h-72"><canvas id="fireTimeChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Alarm Level Distribution</h3><p class="mt-1 text-xs text-slate-500">Recorded alarm levels; no separate fire severity score</p><div class="mt-4 h-72"><canvas id="fireAlarmChart"></canvas></div></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Fire Duration by Alarm</h3><p class="mt-1 text-xs text-slate-500">Median occurrence-to-fire-out time in minutes, with sample counts in tooltips</p><div class="mt-4 h-72"><canvas id="fireDurationChart"></canvas></div></article>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const monthly = @json($fireMonthly), severity = @json($fireSeverity), barangays = @json($fireBarangays), time = @json($fireTimeOfDay);
    const alarm = @json($fireDashboard['alarm_distribution'] ?? []), duration = @json($fireDashboard['duration_by_alarm'] ?? []);
    const palette = ['#2563eb','#14b8a6','#8b5cf6','#f59e0b','#f43f5e','#64748b','#06b6d4','#84cc16'];
    const options = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
    new Chart(document.getElementById('fireMonthlyChart'), {type:'bar',data:{labels:monthly.labels ?? [],datasets:[{label:'Fire Incidents',data:monthly.incidents ?? [],borderColor:'#2563eb',backgroundColor:'#3b82f6',borderRadius:6,fill:true,tension:.3}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireCauseChart'), {type:'doughnut',data:{labels:severity.labels ?? [],datasets:[{data:severity.values ?? [],backgroundColor:palette,borderWidth:0}]},options:{...options,cutout:'70%',plugins:{...options.plugins,tooltip:{callbacks:{label:(ctx)=>{const total=ctx.dataset.data.reduce((a,b)=>a+b,0);return `${ctx.label}: ${ctx.parsed} (${total ? (100*ctx.parsed/total).toFixed(1) : 0}%)`;}}}}}});
    new Chart(document.getElementById('fireBarangayChart'), {type:'bar',data:{labels:barangays.labels ?? [],datasets:[{label:'Fire Incidents',data:barangays.incidents ?? [],backgroundColor:'#3b82f6',borderRadius:5}]},options:{...options,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireTimeChart'), {type:'bar',data:{labels:time.labels ?? [],datasets:[{label:'Fire Incidents',data:time.values ?? [],backgroundColor:['#f59e0b','#fb923c','#ef4444','#7f1d1d']}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireAlarmChart'), {type:'bar',data:{labels:alarm.labels ?? [],datasets:[{label:'Incidents',data:alarm.values ?? [],backgroundColor:palette,borderRadius:5}]},options:{...options,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireDurationChart'), {type:'bar',data:{labels:duration.labels ?? [],datasets:[{label:'Median minutes',data:duration.values ?? [],backgroundColor:'#14b8a6',borderRadius:5}]},options:{...options,indexAxis:'y',plugins:{...options.plugins,tooltip:{callbacks:{afterLabel:(ctx)=>`Completed samples: ${duration.counts?.[ctx.dataIndex] ?? 0}`}}},scales:{x:{beginAtZero:true}}}});
});
</script>
@endpush
