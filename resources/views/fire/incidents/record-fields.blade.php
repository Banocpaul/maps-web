@php($record = $fireIncident ?? null)
<div class="incident-field">
    <label for="occurred_at">Time Occurred (PHT)</label>
    <input id="occurred_at" name="occurred_at" type="datetime-local" value="{{ old('occurred_at', $record?->occurred_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') ?? now('Asia/Manila')->format('Y-m-d\TH:i')) }}" required>
    @error('occurred_at')<span class="incident-error">{{ $message }}</span>@enderror
</div>
<div class="incident-field">
    <label for="alarm_level">BFP alarm (confirmed)</label>
    <select id="alarm_level" name="alarm_level"><option value="">Not recorded</option>
        @foreach(['1st', '2nd', '3rd', '4th', '5th', 'Task Force Alpha', 'Task Force Bravo', 'General Alarm'] as $alarm)
            <option value="{{ $alarm }}" @selected(old('alarm_level', $record?->alarm_level ?? $publicReport->alarm_level ?? null) === $alarm)>{{ $alarm }}</option>
        @endforeach
    </select>
    @error('alarm_level')<span class="incident-error">{{ $message }}</span>@enderror
</div>
<p class="incident-help">Complete affected people, houses destroyed and cause using Finalize Record after fire out.</p>

@include('incident-reports.casualty-fields', ['record' => $record ?? $publicReport ?? null])
