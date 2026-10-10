@php($record = $fireIncident ?? null)
<div class="incident-field">
    <label for="occurred_at">Time Occurred (PHT)</label>
    <input id="occurred_at" name="occurred_at" type="datetime-local" value="{{ old('occurred_at', $record?->occurred_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') ?? now('Asia/Manila')->format('Y-m-d\TH:i')) }}" required>
    @error('occurred_at')<span class="incident-error">{{ $message }}</span>@enderror
</div>
@foreach(['individuals_affected' => 'Individuals Affected', 'houses_destroyed' => 'Houses Destroyed'] as $field => $label)
<div class="incident-field">
    <label for="{{ $field }}">{{ $label }}</label>
    <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="4294967295" step="1" value="{{ old($field, $record?->{$field}) }}" placeholder="Unknown">
    @error($field)<span class="incident-error">{{ $message }}</span>@enderror
</div>
@endforeach
<div class="incident-field">
    <label for="alarm_level">Alarm (reported)</label>
    <select id="alarm_level" name="alarm_level"><option value="">Not recorded</option>
        @foreach(['1st', '2nd', '3rd', '4th', '5th', 'Task Force Alpha', 'Task Force Bravo', 'General Alarm'] as $alarm)
            <option value="{{ $alarm }}" @selected(old('alarm_level', $record?->alarm_level) === $alarm)>{{ $alarm }}</option>
        @endforeach
    </select>
    @error('alarm_level')<span class="incident-error">{{ $message }}</span>@enderror
</div>
<div class="incident-field">
    <label for="cause">Cause (confirmed)</label>
    <input id="cause" name="cause" maxlength="255" value="{{ old('cause', $record?->cause) }}" placeholder="Leave blank until confirmed">
    @error('cause')<span class="incident-error">{{ $message }}</span>@enderror
</div>
