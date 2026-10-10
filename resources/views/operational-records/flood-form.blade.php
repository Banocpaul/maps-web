@extends('layouts.app')
@section('title', ($record ? 'Edit' : 'Add') . ' Flood Incident Record | M.A.P.S')
@section('content')
@php
    $editing = $record !== null;
    $value = fn ($field, $default = '') => old($field, data_get($record, $field, $default));
    $numberFields = [
        'duration_hours' => 'Duration (hours)', 'latitude' => 'Latitude', 'longitude' => 'Longitude',
        'elevation_m' => 'Elevation (m)', 'distance_to_waterway_m' => 'Distance to Waterway (m)',
        'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'rainfall_3d_mm' => 'Rainfall 3d (mm)', 'rainfall_7d_mm' => 'Rainfall 7d (mm)',
        'temperature_c' => 'Temperature (°C)', 'temp_max_c' => 'Maximum Temperature (°C)', 'temp_min_c' => 'Minimum Temperature (°C)',
        'wind_speed_kph' => 'Wind Speed (km/h)', 'wind_direction_deg' => 'Wind Direction (°)', 'storm_signal' => 'Storm Signal',
    ];
    $barangayNames = $barangays->pluck('name')->push($value('barangay'))->filter()->unique()->sort();
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Flood Incident Records</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $editing ? 'Edit flood record' : 'Add flood record' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Enter dates and times in Philippine Time (PHT). Subsided incidents require a subsided time.</p>
    </header>
    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ $editing ? route('operational-records.flood.update', $record->id) : route('operational-records.flood.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <label><span class="text-sm font-semibold text-slate-700">Event ID</span><input required name="event_id" value="{{ $value('event_id') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @foreach (['observation_datetime' => 'Observation', 'flood_start_datetime' => 'Flood Start', 'flood_subsided_datetime' => 'Flood Subsided'] as $field => $label)
                <label><span class="text-sm font-semibold text-slate-700">{{ $label }} (PHT)</span><input @required($field !== 'flood_subsided_datetime') type="datetime-local" step="1" name="{{ $field }}" value="{{ str_replace(' ', 'T', $value($field) ?? '') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @endforeach
            <label><span class="text-sm font-semibold text-slate-700">Barangay</span><select required name="barangay" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select barangay</option>@foreach ($barangayNames as $name)<option value="{{ $name }}" @selected($value('barangay') === $name)>{{ $name }}</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold text-slate-700">Status</span><select required name="status" class="mt-1 w-full rounded-xl border-slate-300">@foreach (['Active', 'Subsided'] as $status)<option value="{{ $status }}" @selected($value('status', 'Active') === $status)>{{ $status }}</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold text-slate-700">Flood Code</span><select required name="flood_code" class="mt-1 w-full rounded-xl border-slate-300">@foreach (['A', 'B', 'C', 'D'] as $code)<option value="{{ $code }}" @selected($value('flood_code', 'A') === $code)>{{ $code }}</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold text-slate-700">Nearest Waterway</span><input name="nearest_waterway" value="{{ $value('nearest_waterway') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @foreach ($numberFields as $field => $label)
                <label><span class="text-sm font-semibold text-slate-700">{{ $label }}</span><input type="number" step="{{ $field === 'storm_signal' ? '1' : 'any' }}" name="{{ $field }}" value="{{ $value($field) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @endforeach
        </div>
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('operational-records.index', ['dataset' => 'flood-records']) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 font-semibold text-slate-700">Cancel</a><button class="rounded-xl bg-sky-700 px-5 py-2.5 font-semibold text-white">{{ $editing ? 'Save changes' : 'Create record' }}</button></div>
    </form>
</div>
@endsection
