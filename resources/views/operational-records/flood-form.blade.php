@extends('layouts.app')

@section('title', ($record ? 'Edit' : 'Add') . ' Flood Analytics Record | M.A.P.S.')

@section('content')
@php
    $editing = $record !== null;
    $value = fn (string $field, mixed $default = '') => old($field, $editing ? data_get($record, $field) : $default);
    $numberFields = [
        'storm_signal' => ['Storm Signal', '1'], 'elevation_m' => ['Elevation (m)', '0.01'],
        'distance_to_waterway_m' => ['Distance to Waterway (m)', '0.01'], 'drainage_index' => ['Drainage Index (0-1)', '0.001'],
        'impervious_surface_ratio' => ['Impervious Ratio (0-1)', '0.001'], 'population_density_per_km2' => ['Population Density / km²', '0.01'],
        'historical_flood_count_5y' => ['Historical Flood Count (5y)', '1'], 'rainfall_24h_mm' => ['Rainfall 24h (mm)', '0.01'],
        'rainfall_3d_mm' => ['Rainfall 3d (mm)', '0.01'], 'rainfall_7d_mm' => ['Rainfall 7d (mm)', '0.01'],
        'temperature_c' => ['Temperature (°C)', '0.01'], 'humidity_pct' => ['Humidity (%)', '0.01'],
        'wind_speed_kph' => ['Wind Speed (kph)', '0.01'], 'tide_level_m' => ['Tide Level (m)', '0.01'],
        'flood_depth_mm' => ['Flood Depth (mm)', '0.01'], 'duration_hours' => ['Duration (hours)', '0.01'],
    ];
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <header>
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Flood Analytics Dataset</p>
        <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $editing ? 'Edit flood record' : 'Add flood record' }}</h1>
        <p class="mt-2 text-sm text-slate-600">Date-derived fields such as year, month, weekday, weekend, and wet season are calculated automatically.</p>
    </header>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editing ? route('operational-records.flood.update', $record->id) : route('operational-records.flood.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <label><span class="text-sm font-semibold text-slate-700">Event ID</span><input required name="event_id" value="{{ $value('event_id') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label><span class="text-sm font-semibold text-slate-700">Event Date</span><input required type="date" name="event_date" value="{{ $value('event_date') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label><span class="text-sm font-semibold text-slate-700">Barangay</span><select required name="barangay" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->name }}" @selected($value('barangay') === $barangay->name)>{{ $barangay->name }}</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold text-slate-700">Nearest Waterway</span><input name="nearest_waterway" value="{{ $value('nearest_waterway') }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label><span class="text-sm font-semibold text-slate-700">Risk Level</span><select required name="risk_level" class="mt-1 w-full rounded-xl border-slate-300">@foreach (['Low','Medium','High'] as $risk)<option value="{{ $risk }}" @selected($value('risk_level', 'Low') === $risk)>{{ $risk }}</option>@endforeach</select></label>
            @foreach ($numberFields as $field => [$label, $step])
                <label><span class="text-sm font-semibold text-slate-700">{{ $label }}</span><input required type="number" step="{{ $step }}" name="{{ $field }}" value="{{ $value($field, 0) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            @endforeach
        </div>
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('operational-records.index', ['dataset' => 'flood-records']) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 font-semibold text-slate-700">Cancel</a><button class="rounded-xl bg-sky-700 px-5 py-2.5 font-semibold text-white">{{ $editing ? 'Save changes' : 'Create record' }}</button></div>
    </form>
</div>
@endsection
