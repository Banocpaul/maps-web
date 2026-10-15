@extends('layouts.app')
@section('title', 'Incident Archives | M.A.P.S.')
@section('content')
<section class="rounded-2xl border bg-white p-6 space-y-5">
<h1 class="text-2xl font-bold">Incident archives</h1>
<div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
<p>Automatic archiving runs once per month, with a catch-up check when the system wakes. Only closed incidents older than three years qualify.</p>
@if($automation?->initial_completed_at)<p>Initial archive completed: {{ \Carbon\Carbon::parse($automation->initial_completed_at, 'UTC')->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT.</p>@else<p>Initial archive: awaiting deployment run.</p>@endif
@if($automation?->last_completed_month)<p>Last completed month: {{ $automation->last_completed_month }}.</p>
@php($archiveResult = json_decode($automation->last_result ?? '{}', true))
<p>Last run: {{ $archiveResult['counts']['fire'] ?? 0 }} fires, {{ $archiveResult['counts']['flood'] ?? 0 }} flood records, {{ $archiveResult['counts']['observations'] ?? 0 }} flood observations archived.</p>
@endif
</div>
<p class="text-slate-600">Only resolved fires and subsided floods dated before {{ $cutoff }} (Philippine time) qualify. Archiving hides records from current lists while preserving historical analytics. Report snapshots are separate.</p>
@if(session('success'))<p class="rounded-lg bg-emerald-50 text-emerald-800 p-3">{{ session('success') }}</p>@endif
<a class="text-blue-700" href="{{ route('operational-records.index') }}">Operational Records</a>
<form method="GET" class="flex flex-wrap items-end gap-4">
<label>Incident type<select name="type" class="block border rounded-lg p-2">@foreach(\App\Services\IncidentArchiveService::TYPES as $value => $label)<option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Show<select name="state" class="block border rounded-lg p-2"><option value="archived" @selected($state === 'archived')>Archived incidents</option><option value="eligible" @selected($state === 'eligible')>Eligible to archive</option></select></label>
<label>Search<input name="search" maxlength="100" value="{{ $search }}" class="block border rounded-lg p-2" placeholder="Reference, barangay or location"></label><button class="rounded-lg bg-blue-700 text-white px-4 py-2">Apply</button>
</form>
<p>{{ $records->total() }} matching incidents</p>
@if($canManage)
<form id="archive-selected" method="POST" action="{{ route('incident-archives.bulk') }}" class="flex flex-wrap items-center gap-3">@csrf
<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="action" value="{{ $state === 'eligible' ? 'archive' : 'restore' }}">
<label><input type="checkbox" onchange="document.querySelectorAll('[data-archive-selection]').forEach(el => el.checked = this.checked)"> Select this page</label>
<input name="reason" required minlength="5" maxlength="1000" aria-label="Reason for selected incidents" placeholder="Reason for selected incidents" class="border rounded-lg p-2">
<button class="rounded-lg bg-blue-700 text-white p-2">{{ $state === 'eligible' ? 'Archive selected' : 'Restore selected' }}</button>
</form>
@endif
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left"><th class="p-3">Incident</th><th class="p-3">Location</th><th class="p-3">Status</th><th class="p-3">Archive details / actions</th></tr></thead><tbody>
@forelse($records as $record)<tr class="border-t"><td class="p-3">@if($canManage)<input data-archive-selection type="checkbox" form="archive-selected" name="ids[]" value="{{ $record->id }}" aria-label="Select incident {{ $record->id }}"> @endif{{ $record->incident_number ?? $record->event_id ?? '#'.$record->id }}<p class="text-slate-500">{{ $type === 'fire' ? ($record->occurred_at ?? $record->reported_at)?->copy()->timezone('Asia/Manila')->format('Y-m-d') : ($type === 'flood' ? $record->flood_start_datetime : $record->observed_at?->copy()->timezone('Asia/Manila')->format('Y-m-d')) }}</p></td>
<td class="p-3">{{ $type === 'fire' ? $record->location : $record->barangay }}</td><td class="p-3">{{ $record->status ?? $record->flood_status }}</td><td class="p-3">
@if($state === 'archived')<p>{{ $record->archive_reason }}</p><p class="text-slate-500">Archived {{ \Carbon\Carbon::parse($record->archived_at, 'UTC')->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p><a class="text-blue-700" href="{{ route('incident-archives.show', ['type' => $type, 'id' => $record->id]) }}">View / print</a>@endif
@if($canManage)<form method="POST" class="flex flex-wrap gap-2 mt-2" action="{{ route($state === 'archived' ? 'incident-archives.restore' : 'incident-archives.archive', ['type' => $type, 'id' => $record->id]) }}">@csrf
<input name="reason" required minlength="5" maxlength="1000" aria-label="Reason" placeholder="{{ $state === 'archived' ? 'Reason for restoring' : 'Reason for archiving' }}" class="border rounded-lg p-2"><button class="border border-blue-300 text-blue-700 rounded-lg p-2">{{ $state === 'archived' ? 'Restore incident' : 'Archive incident' }}</button></form>@endif
</td></tr>@empty<tr><td colspan="4" class="p-6 text-slate-500">No matching incidents.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}
</section>
@endsection
