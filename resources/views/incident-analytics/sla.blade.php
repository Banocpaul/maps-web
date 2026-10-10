@php
    $sla = $sla ?? [];
    $stages = $sla['stages'] ?? [];
    $displayMinutes = fn ($value) => $value === null ? 'N/A' : number_format($value, 1).' min';
@endphp
<section class="mt-6 space-y-4" aria-label="{{ ucfirst($hazard) }} public report SLA">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Service performance</p><h2 class="mt-1 text-xl font-bold text-slate-950">Public Report SLA</h2><p class="mt-1 text-sm text-slate-500">{{ $sla['total'] ?? 0 }} submissions · Submission-year cohort · Elapsed time, including nights and weekends</p></div>
        @if ($user->hasPermission('public-submissions.view'))<a href="{{ route('public-submissions.index', ['incident_type' => $hazard]) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Review reports →</a>@endif
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['completed' => 'Completed handling', 'median' => 'Median total handling', 'p90' => '90th percentile', 'overdue' => 'Open beyond total SLA'] as $field => $label)
        <article @class(['rounded-2xl border p-5 shadow-sm', 'border-slate-900 bg-slate-900 text-white' => $loop->first, 'border-slate-200 bg-white text-slate-950' => ! $loop->first])>
            <p class="text-xs font-semibold uppercase tracking-wide opacity-70">{{ $label }}</p>
            <strong class="mt-3 block text-2xl">{{ in_array($field, ['median', 'p90']) ? $displayMinutes($stages['completion'][$field] ?? null) : ($field === 'overdue' && ($stages['completion']['target'] ?? null) === null ? 'N/A' : ($stages['completion'][$field] ?? 0)) }}</strong>
            <p class="mt-2 text-xs opacity-70">{{ $hazard === 'fire' ? 'Submission to record finalization' : 'Submission to recorded flood completion' }}</p>
        </article>
        @endforeach
    </div>
    <div class="grid gap-4 xl:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Completion SLA Summary</h3><p class="mt-1 text-xs text-slate-500">Completed measurable reports against the configured total target</p><div class="mt-4 h-56"><canvas id="{{ $hazard }}SlaSummaryChart"></canvas></div><p class="mt-2 text-center text-xs text-slate-500">{{ ($stages['completion']['target'] ?? null) === null ? 'Configure a total handling target to show compliance.' : 'Open reports are excluded from completed compliance.' }}</p></article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-slate-950">Open Reports Beyond SLA</h3><p class="mt-1 text-xs text-slate-500">A report may exceed both a stage and the total target</p><div class="mt-4 h-56"><canvas id="{{ $hazard }}SlaDelayChart"></canvas></div></article>
    </div>
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5"><h3 class="font-semibold text-slate-950">Handling time by workflow stage</h3><p class="mt-1 text-xs text-slate-500">Completion includes review and incident handling. Stage rows overlap and must not be added together.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>@foreach (['Stage', 'Target', 'Completed', 'Median', 'P90', 'Within SLA', 'Waiting', 'Overdue'] as $heading)<th class="whitespace-nowrap px-5 py-3">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
            @foreach ($stages as $stage)<tr><td class="whitespace-nowrap px-5 py-4 font-medium">{{ $stage['label'] }}</td><td class="px-5 py-4">{{ $stage['target'] === null ? 'Not configured' : $stage['target'].' min' }}</td><td class="px-5 py-4">{{ $stage['completed'] }}</td><td class="whitespace-nowrap px-5 py-4">{{ $displayMinutes($stage['median']) }}</td><td class="whitespace-nowrap px-5 py-4">{{ $displayMinutes($stage['p90']) }}</td><td class="px-5 py-4">{{ $stage['compliance'] === null ? 'N/A' : $stage['compliance'].'%' }}</td><td class="px-5 py-4">{{ $stage['waiting'] }}</td><td class="px-5 py-4 font-semibold text-rose-600">{{ $stage['target'] === null ? 'N/A' : $stage['overdue'] }}</td></tr>@endforeach
        </tbody></table></div>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Compliance = completed intervals within target ÷ completed measurable intervals. {{ $sla['rejected'] ?? 0 }} rejected reports excluded. {{ $sla['unknown_completion'] ?? 0 }} records lack a completion timestamp or their linked incident is unavailable. {{ collect($stages)->sum('invalid') }} invalid intervals excluded. Physical fire/flood duration is shown separately.</p>
    </article>
    @if ($roleSlug === 'operations-manager')
    <details class="rounded-2xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Configure {{ ucfirst($hazard) }} SLA targets</summary><p class="mt-3 text-xs text-slate-500">Set your department’s agreed targets in minutes. Blank means no target. These are management targets; they do not claim to be official standards. Changes apply to the displayed cohort.</p>
        <form method="POST" action="{{ route('incident-analytics.sla.update') }}" class="mt-4 grid gap-4 sm:grid-cols-4">@csrf<input type="hidden" name="hazard" value="{{ $hazard }}">
            @foreach (['validation' => 'Submission → validation', 'publication' => 'Validation → posting', 'completion' => 'Submission → completion'] as $key => $label)<label class="text-xs font-medium text-slate-600">{{ $label }}<input type="number" name="{{ $key }}_minutes" min="1" max="525600" value="{{ $stages[$key]['target'] ?? '' }}" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="No target"></label>@endforeach
            <button class="self-end rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Save targets</button>
        </form>
    </details>
    @endif
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="p-5"><h3 class="font-semibold text-slate-950">Management Attention</h3><p class="mt-1 text-xs text-slate-500">Open reports ranked by breached targets, then total age. Showing up to 10.</p></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr>@foreach (['Reference', 'Barangay', 'Waiting stage', 'Total age', 'SLA status'] as $label)<th class="px-5 py-3">{{ $label }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">@forelse ($sla['attention'] ?? [] as $report)<tr><td class="px-5 py-3">@if ($user->hasPermission('public-submissions.view'))<a class="font-semibold text-blue-600 hover:underline" href="{{ route('public-submissions.show', $report['id']) }}">{{ $report['reference'] }}</a>@else{{ $report['reference'] }}@endif</td><td class="px-5 py-3">{{ $report['barangay'] }}</td><td class="px-5 py-3">{{ $report['stage'] }}</td><td class="whitespace-nowrap px-5 py-3">{{ $displayMinutes($report['minutes']) }}</td><td class="px-5 py-3"><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-rose-50 text-rose-700' => $report['overdue'], 'bg-slate-100 text-slate-600' => ! $report['overdue']])>{{ $report['overdue'] ? 'Beyond SLA' : 'No breached target' }}</span></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No open public reports match these filters.</td></tr>@endforelse</tbody></table></div></article>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;
    const stages = @json($stages), hazard = @json($hazard), completion = stages.completion ?? {};
    const options = {responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}};
    new Chart(document.getElementById(`${hazard}SlaSummaryChart`), {type:'doughnut',data:{labels:['Within SLA','Beyond SLA'],datasets:[{data:completion.target == null ? [] : [completion.within ?? 0,(completion.completed ?? 0)-(completion.within ?? 0)],backgroundColor:['#14b8a6','#f43f5e'],borderWidth:0}]},options:{...options,cutout:'72%'}});
    new Chart(document.getElementById(`${hazard}SlaDelayChart`), {type:'bar',data:{labels:Object.values(stages).map(s=>s.label),datasets:[{label:'Overdue open reports',data:Object.values(stages).map(s=>s.target == null ? null : s.overdue),backgroundColor:'#3b82f6',borderRadius:5}]},options:{...options,indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
});
</script>
@endpush
