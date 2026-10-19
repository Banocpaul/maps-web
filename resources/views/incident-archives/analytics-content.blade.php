<div class="space-y-6">
<header class="rounded-2xl border bg-white p-6">
<h1 class="text-2xl font-bold text-slate-950">M.A.P.S. Annual Historical Incident Report — {{ $year }}</h1>
<p class="mt-2 text-sm text-slate-600">{{ ucfirst($type) === 'All' ? 'Fire and flood' : ucfirst($type) }} incidents · {{ $scope === 'archived' ? 'Archived records only' : 'All historical official records (including archived)' }} · Generated {{ $now->format('M d, Y g:i A') }} PHT</p>
@if($year === $now->year)<p class="mt-2 text-amber-700">Current year is incomplete; figures cover records available to date.</p>@endif
</header>
<div class="grid grid-cols-2 gap-3 md:grid-cols-3">
@foreach(['Total incidents' => number_format($total),'People affected' => $sums['people']['total'] === null ? 'Not recorded' : number_format($sums['people']['total']),'Houses destroyed' => $sums['houses']['total'] === null ? 'Not recorded' : number_format($sums['houses']['total']),'Injured' => $sums['injured']['total'] === null ? 'Not recorded' : number_format($sums['injured']['total']),'Deceased' => $sums['deceased']['total'] === null ? 'Not recorded' : number_format($sums['deceased']['total']),'Barangays with incidents' => number_format($barangays->count())] as $label => $value)
<div class="rounded-xl border bg-white p-4"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p></div>
@endforeach
</div>
<section class="rounded-xl border bg-white p-5"><h2 class="mb-3 text-lg font-bold">Barangays ranked by incident count</h2>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-2">Rank</th><th>Barangay</th><th>Total</th><th>Fire</th><th>Flood</th></tr></thead><tbody>
@forelse($barangays as $row)<tr class="border-b"><td class="p-2">{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ number_format($row['count']) }}</td><td>{{ $row['fire'] }}</td><td>{{ $row['flood'] }}</td></tr>@empty<tr><td colspan="5" class="p-3">No matching records.</td></tr>@endforelse
</tbody></table></div></section>
<section class="rounded-xl border bg-white p-5"><h2 class="mb-3 text-lg font-bold">Monthly incident trends</h2>
<div class="grid grid-cols-12 items-end gap-1" style="height:160px">
@php($peak = max(1, $months->max('count')))
@foreach($months as $m)<div class="flex h-full flex-col justify-end items-center gap-1"><span class="text-xs">{{ $m['count'] }}</span><div class="w-full rounded-t bg-blue-600" style="height:{{ max(2,round(105*$m['count']/$peak)) }}px"></div><span class="text-xs">{{ $m['name'] }}</span></div>@endforeach
</div>
<div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b"><th>Month</th>@foreach($months as $m)<th>{{ $m['name'] }}</th>@endforeach</tr></thead><tbody><tr><td>Total</td>@foreach($months as $m)<td class="text-center">{{ $m['count'] }}</td>@endforeach</tr></tbody></table></div>
</section>
<section class="rounded-xl border bg-white p-5"><h2 class="mb-3 text-lg font-bold">Severity and alarm classification</h2>
<div class="grid gap-2 sm:grid-cols-2">@forelse($severities as $label => $count)<p class="rounded bg-slate-50 p-2">{{ $label }}: <strong>{{ number_format($count) }}</strong></p>@empty<p>No severity classifications recorded.</p>@endforelse</div></section>
<section class="rounded-xl border bg-white p-5"><h2 class="mb-3 text-lg font-bold">Detailed incident register</h2><div class="overflow-x-auto"><table class="w-full text-xs"><thead><tr class="border-b text-left"><th class="p-2">Reference</th><th>Date (PHT)</th><th>Type</th><th>Barangay</th><th>Status</th><th>Severity</th></tr></thead><tbody>@forelse($events as $e)<tr class="border-b"><td class="p-2">{{ $e['id'] }}</td><td>{{ $e['at']->format('Y-m-d') }}</td><td>{{ $e['type'] }}</td><td>{{ $e['barangay'] }}</td><td>{{ $e['status'] }}</td><td>{{ $e['severity'] }}</td></tr>@empty<tr><td colspan="6">No records for this selection.</td></tr>@endforelse</tbody></table></div></section>
<section class="rounded-xl border bg-white p-5 text-sm text-slate-600"><h2 class="font-bold text-slate-900">Data coverage and interpretation</h2>
<p class="mt-2">One official fire incident or flood incident record counts as one observation. Resident submissions and flood training observations are not included to avoid mixing sources. The year is based on incident occurrence/start date (Philippine time), not archive date. Recent historical records are included only when scope is All historical.</p>
@foreach(['people'=>'People affected','houses'=>'Houses destroyed','injured'=>'Injured','deceased'=>'Deceased'] as $key=>$label)<p>{{ $label }}: {{ $sums[$key]['recorded'] }} of {{ $total }} records contain a value; {{ $sums[$key]['missing'] }} missing. Missing is not treated as zero.</p>@endforeach
<p class="mt-2">People affected and houses destroyed are currently available from fire incident records; flood totals are not inferred. Statistics reflect recorded data and should not be interpreted as comprehensive disaster occurrence estimates.</p>
</section>
</div>