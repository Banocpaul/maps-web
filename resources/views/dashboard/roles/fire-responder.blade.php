@php
    $fireKpis = $fireDashboard['kpis'] ?? [];
    $monthlyTrend = $fireDashboard['monthly_trend'] ?? [];
    $severityDistribution = $fireDashboard['severity_distribution'] ?? [];
    $activeIncidents = collect($fireOperations['recent_active'] ?? []);
@endphp

@include('dashboard.partials.filters')

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
        <p class="text-sm font-medium text-red-700">Active Incidents</p>
        <p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($fireOperations['active'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-red-600">Reported, responding, or controlled</p>
    </article>
    <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Newly Reported</p>
        <p class="mt-2 text-3xl font-bold text-amber-600">{{ number_format($fireOperations['reported'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">Awaiting a response update</p>
    </article>
    <article class="rounded-2xl border border-sky-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Responding</p>
        <p class="mt-2 text-3xl font-bold text-sky-700">{{ number_format($fireOperations['responding'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">Response currently underway</p>
    </article>
    <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Resolved</p>
        <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($fireOperations['resolved'] ?? 0) }}</p>
        <p class="mt-2 text-xs text-slate-500">{{ $fireKpis['resolution_rate'] ?? 0 }}% historical resolution rate</p>
    </article>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="font-semibold text-slate-950">Response Shortcuts</h2><p class="mt-1 text-sm text-slate-500">Common actions for fire operations</p></div>
        <div class="flex flex-wrap gap-2">
            @if (Route::has('fire-incidents.create') && auth()->user()?->hasPermission('fire.create'))
                <a href="{{ route('fire-incidents.create') }}" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">Report Fire Incident</a>
            @endif
            <a href="{{ route('fire-incidents.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">View Incidents</a>
            <a href="{{ route('fire-hydrants.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">View Hydrants</a>
            <a href="{{ route('gis.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open GIS Map</a>
        </div>
    </div>
</section>

<section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Active Incident Queue</h2><p class="mt-1 text-sm text-slate-500">Open an incident to update its operational status</p></div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Incident</th><th class="px-5 py-3">Barangay</th><th class="px-5 py-3">Location</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Reported</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activeIncidents as $incident)
                    <tr>
                        <td class="px-5 py-3 font-semibold text-slate-900">{{ $incident->incident_number }}</td>
                        <td class="px-5 py-3 text-slate-700">{{ $incident->barangay?->name ?? 'Unknown' }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $incident->location }}</td>
                        <td class="px-5 py-3"><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $incident->status }}</span></td>
                        <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $incident->reported_at?->format('M j, g:i A') }}</td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('fire-incidents.show', $incident) }}" class="font-semibold text-sky-700 hover:text-sky-900">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">There are no active fire incidents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-950">Monthly Fire Trend</h2><div class="mt-4 h-72"><canvas id="fireMonthlyChart"></canvas></div></article>
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-950">Severity Distribution</h2><div class="mt-4 h-72"><canvas id="fireSeverityChart"></canvas></div></article>
</section>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const monthly = @json($monthlyTrend);
    const severity = @json($severityDistribution);
    new Chart(document.getElementById('fireMonthlyChart'), {type:'line',data:{labels:monthly.labels ?? [],datasets:[{label:'Fire Incidents',data:monthly.incidents ?? [],borderColor:'#dc2626',backgroundColor:'rgba(220,38,38,.1)',fill:true,tension:.3}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
    new Chart(document.getElementById('fireSeverityChart'), {type:'doughnut',data:{labels:severity.labels ?? [],datasets:[{data:severity.values ?? [],backgroundColor:['#f59e0b','#f97316','#dc2626']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
});
</script>
@endpush
