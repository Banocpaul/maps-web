@extends('layouts.app')
@section('title', 'Incident Reports | M.A.P.S.')
@section('content')
@if(auth()->user()->hasPermission('dashboard.view'))<a href="{{ route('incident-comparison.index') }}" class="mb-4 inline-block rounded-lg border border-blue-200 bg-white px-4 py-2 font-semibold text-blue-700">Annual incident comparison report</a>@endif
<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Incident Reports</h1><p class="mt-2 text-slate-600">Reports can be verified by staff or by matching reports from distinct residents. Staff complete official incident details before publication.</p></div>
    <section class="rounded-xl border border-blue-100 bg-white p-5">
    <h2 class="font-bold text-lg">Annual official incident comparison · {{ $annualComparison['year'] }} versus {{ $annualComparison['year'] - 1 }}</h2>
    <p class="text-sm text-slate-500 mt-1">Full January–December totals, including archived records. These compare official incident records, independently of the public submission statuses below.</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
    @foreach($annualComparison['cards'] as $hazard => $card)
    <div class="rounded-xl bg-slate-50 p-4"><h3 class="text-sm font-semibold">{{ $hazard === 'all' ? 'All recorded incidents' : ucfirst($hazard).' incidents' }}</h3><strong class="block text-3xl mt-2">{{ number_format($card['current']) }}</strong>
    <p class="font-semibold mt-2 {{ $card['delta'] > 0 ? 'text-red-700' : ($card['delta'] < 0 ? 'text-emerald-700' : 'text-slate-600') }}">{{ $card['label'] }}</p><p class="text-sm text-slate-500">{{ number_format($card['previous']) }} in {{ $annualComparison['year'] - 1 }}</p></div>
    @endforeach
    </div>
    <a href="{{ route('incident-comparison.index') }}" class="mt-4 inline-block font-semibold text-blue-700">View annual, monthly, barangay and severity comparisons →</a>
    </section>
    @if(auth()->user()->hasAnyRole(['operations-manager', 'operations-officer']) && auth()->user()->hasPermission('public-submissions.review'))
    <details class="rounded-xl border border-slate-200 bg-white p-5">
        <summary class="cursor-pointer font-semibold">Incident reporting settings</summary>
        <p class="mt-3 text-sm text-slate-600">Both flood and fire: matching hazard, distance from the first report, and reporting window. Each resident counts once. Changes apply on new submissions.</p>
        <form method="POST" action="{{ route('public-submissions.settings') }}" class="mt-4 flex flex-wrap items-end gap-4">@csrf
        @foreach(['threshold' => 'Distinct resident threshold', 'radius_m' => 'Matching radius (metres)', 'window_minutes' => 'Reporting window (minutes)'] as $field => $label)
            <label class="text-sm font-semibold">{{ $label }}<input type="number" required name="{{ $field }}" value="{{ old($field, $settings->$field) }}" class="mt-1 block rounded-lg border border-slate-300 p-2"></label>
        @endforeach
        <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Save settings</button></form>
    </details>
    @endif
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach(['Pending', 'Validated', 'Published', 'Rejected'] as $status)
            <a href="{{ route('public-submissions.index', ['status' => $status]) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-sm text-slate-500">{{ $status }}</span><strong class="mt-2 block text-3xl">{{ $counts[$status] ?? 0 }}</strong></a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-4 rounded-xl bg-white p-4">
        <label class="text-sm font-semibold">Incident type<select name="type" class="mt-1 block rounded-lg border border-slate-300 p-2"><option value="">All permitted types</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold">Report status<select name="status" class="mt-1 block rounded-lg border border-slate-300 p-2"><option value="">All statuses</option>@foreach(['Pending', 'Validated', 'Published', 'Rejected'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
        <button class="rounded-lg bg-blue-700 px-4 py-2 font-semibold text-white">Apply filters</button><a href="{{ route('public-submissions.index') }}" class="px-3 py-2 text-slate-600">Clear</a>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Reference</th><th class="p-4">Type</th><th class="p-4">Submitted (Manila)</th><th class="p-4">Status</th><th class="p-4">Action</th></tr></thead><tbody>
        @forelse($reports as $report)
            <tr class="border-t border-slate-100"><td class="p-4 font-semibold">{{ $report->reference }}</td><td class="p-4">{{ ucfirst($report->incident_type) }}</td><td class="p-4">{{ $report->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td class="p-4">{{ $report->status }} @if($report->community_verified)<span class="block text-xs text-blue-700">Public verified · Group #{{ $report->report_group_id }}</span>@endif</td><td class="p-4"><a class="font-semibold text-blue-700" href="{{ route('public-submissions.show', $report) }}">Review report</a></td></tr>
        @empty
            <tr><td colspan="5" class="p-8 text-center text-slate-500">No reports match these filters.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $reports->links() }}
</div>
@endsection
