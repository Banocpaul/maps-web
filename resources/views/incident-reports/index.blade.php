@extends('layouts.app')
@section('title', 'Incident Reports | M.A.P.S.')
@section('content')
<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Incident Reports</h1><p class="mt-2 text-slate-600">Public reports await staff validation before becoming official incidents.</p></div>
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
            <tr class="border-t border-slate-100"><td class="p-4 font-semibold">{{ $report->reference }}</td><td class="p-4">{{ ucfirst($report->incident_type) }}</td><td class="p-4">{{ $report->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td class="p-4">{{ $report->status }}</td><td class="p-4"><a class="font-semibold text-blue-700" href="{{ route('public-submissions.show', $report) }}">Review report</a></td></tr>
        @empty
            <tr><td colspan="5" class="p-8 text-center text-slate-500">No reports match these filters.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $reports->links() }}
</div>
@endsection
