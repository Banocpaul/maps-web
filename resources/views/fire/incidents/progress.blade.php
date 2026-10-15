<section class="rounded-2xl border border-slate-200 bg-white p-6 mt-5">
<h2 class="text-xl font-bold">Fire progress timeline</h2>
@if($fireIncident->canUpdateResponse() && auth()->user()->hasPermission('fire.edit'))
<form method="POST" action="{{ route('fire-incidents.progress', $fireIncident) }}" class="grid gap-4 sm:grid-cols-2 mt-4">@csrf
<label>Update time (PHT)<input type="datetime-local" name="observed_at" required value="{{ old('observed_at', now('Asia/Manila')->format('Y-m-d\TH:i')) }}" class="block w-full border rounded-lg p-2"></label>
<label>Status<select name="status" required class="block w-full border rounded-lg p-2">@foreach(['Reported', 'Responding', 'Controlled', 'Resolved'] as $status)<option @selected(old('status', $fireIncident->status) === $status)>{{ $status }}</option>@endforeach</select></label>
<label>BFP alarm<select name="alarm_level" class="block w-full border rounded-lg p-2"><option value="">Keep current / not confirmed</option>@foreach(\App\Models\FireIncidentUpdate::ALARMS as $alarm)<option @selected(old('alarm_level', $fireIncident->alarm_level) === $alarm)>{{ $alarm }}</option>@endforeach</select></label>
<label>BFP source / reference<input name="alarm_source" maxlength="255" value="{{ old('alarm_source') }}" class="block w-full border rounded-lg p-2"></label>
<label class="sm:col-span-2">Situation update<textarea name="notes" required minlength="5" maxlength="2000" class="block w-full border rounded-lg p-2">{{ old('notes') }}</textarea></label>
<p class="text-sm text-slate-600 sm:col-span-2">Saving sends an SMS update to eligible fire-alert subscribers in the affected barangay. Resolved records fire out and moves the incident to assessment.</p>
<button class="rounded-lg bg-red-700 text-white p-3 font-semibold">Save progress and notify</button>
</form>
@endif
@forelse($fireIncident->progressUpdates()->with('actor')->get() as $update)
<div class="border-t mt-4 pt-4"><strong>{{ $update->observed_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT · {{ $update->status }}</strong>
<p>BFP alarm: {{ $update->alarm_level ?? 'Not confirmed' }} · Source: {{ $update->alarm_source ?? 'Not recorded' }}</p>
<p class="whitespace-pre-wrap">{{ $update->notes }}</p><p class="text-sm text-slate-500">{{ $update->actor?->name ?? 'System' }}</p></div>
@empty<p class="mt-4 text-slate-500">No situation updates recorded.</p>@endforelse
</section>
