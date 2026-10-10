@extends('public.account.layout')
@section('title', 'My Reports')
@section('content')
<div class="flex flex-wrap items-center justify-between gap-4"><div><h1 class="text-3xl font-black">My Reports</h1><p class="mt-2 text-slate-600">Track the flood and fire incidents you submitted.</p></div><a href="{{ route('public.incident-reports.create') }}" class="rounded-xl bg-blue-700 px-5 py-3 font-bold text-white">Report an Incident</a></div>
<p class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-slate-700">Pending: awaiting review. Accepted: validated by staff. Published: added to official incident records. Rejected: declined with a reason.</p>
<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Reference</th><th class="p-4">Type</th><th class="p-4">Submitted (Manila)</th><th class="p-4">Status</th><th class="p-4">Review result</th></tr></thead><tbody>
    @forelse($reports as $report)
        <tr class="border-t border-slate-100"><td class="p-4 font-semibold">{{ $report->reference }}</td><td class="p-4">{{ ucfirst($report->incident_type) }}</td><td class="p-4">{{ $report->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td class="p-4 font-semibold">{{ $report->status === 'Validated' ? 'Accepted (Validated)' : $report->status }}</td><td class="p-4">{{ $report->status === 'Rejected' ? $report->rejection_reason : ($report->status === 'Published' ? 'Added to the official incident map.' : ($report->status === 'Validated' ? 'Accepted; awaiting official incident details.' : 'Awaiting staff review.')) }}</td></tr>
    @empty
        <tr><td colspan="5" class="p-8 text-center text-slate-500">You have not submitted any reports yet.</td></tr>
    @endforelse
    </tbody></table>
</div>
{{ $reports->links() }}
@endsection
