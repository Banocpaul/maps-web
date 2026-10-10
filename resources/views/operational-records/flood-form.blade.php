@extends('layouts.app')
@section('title', 'Plot Flood | M.A.P.S.')
@section('page-title', 'Flood Incident Records')
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
@endpush
@section('content')
@php
    $editing = $record !== null;
    $closed = $editing && $record->status === 'Subsided';
    $selectedBarangay = old('barangay', $record?->barangay);
    $geometry = old('geometry_geojson', $record?->geometry_geojson);
    if (is_string($geometry)) $geometry = json_decode($geometry, true);
    $mapConfiguration = [
        'geometry' => $geometry, 'closed' => $closed,
        'barangays' => $barangays->mapWithKeys(fn ($barangay) => [$barangay->name => [
            'latitude' => $barangay->latitude, 'longitude' => $barangay->longitude,
            'elevation_m' => $barangay->elevation_m, 'nearest_waterway' => $barangay->nearest_waterway,
        ]]),
    ];
@endphp
<div class="mx-auto max-w-5xl space-y-5">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $editing ? 'Flood record' : 'Plot flood' }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $editing ? $record->flood_start_datetime : now('Asia/Manila')->format('M d, Y') }} · Philippine time{{ $editing ? ' · '.$record->status : ' · Today only' }}</p>
        </div>
        @if (auth()->user()->hasPermission('gis.view'))
            <a href="{{ route('gis.index', ['hazard' => 'flood']) }}" class="text-sm font-semibold text-sky-700">Flood GIS Map</a>
        @endif
    </header>
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if ($editing)
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <span class="text-sm font-semibold">Code {{ $record->flood_code }}</span>
            @include('operational-records.partials.flood-actions')
        </div>
    @endif
    <form id="flood-record-form" method="POST" action="{{ $editing ? route('operational-records.flood.update', $record->id) : route('operational-records.flood.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <label><span class="text-sm font-semibold text-slate-700">Barangay</span><select id="flood-record-barangay" name="barangay" required @disabled($closed) class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select barangay</option>@foreach ($barangays as $barangay)<option value="{{ $barangay->name }}" @selected($selectedBarangay === $barangay->name)>{{ $barangay->name }}</option>@endforeach</select></label>
            <label><span class="text-sm font-semibold text-slate-700">Flood code</span>
                <select id="flood-record-code" @if (!$editing) name="flood_code" @endif required @disabled($editing) class="mt-1 w-full rounded-xl border-slate-300">@foreach (['A', 'B', 'C', 'D'] as $code)<option value="{{ $code }}" @selected(($editing ? $record->flood_code : old('flood_code', 'A')) === $code)>{{ $code }}</option>@endforeach</select>
            </label>
        </div>
        <p class="text-sm text-slate-600">Draw a line along the flooded stretch. Barangay and weather details fill automatically.</p>
        <div id="flood-record-map" data-config="{{ json_encode($mapConfiguration) }}" class="rounded-xl border border-slate-200" style="height:420px;z-index:0" aria-label="Draw the flooded stretch"></div>
        <input id="flood-record-geometry" type="hidden" name="geometry_geojson" value="{{ $geometry ? json_encode($geometry) : '' }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p id="flood-record-map-status" role="status" aria-live="polite" class="text-sm text-slate-600">{{ $geometry ? 'Flood line loaded.' : 'Use the line tool on the map.' }}</p>
            @if (!$closed)<button id="flood-record-clear" type="button" class="text-sm font-semibold text-sky-700">Clear line</button>@endif
        </div>
        <p id="flood-record-profile" class="text-sm text-slate-600"></p>
        @if ($editing)
            <details class="rounded-xl bg-slate-50 p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-slate-700">Automatic data{{ $record->enrichment_status ? ' · '.$record->enrichment_status : '' }}</summary>
                @if ($record->enrichment_note)<p class="mt-3 text-amber-800">{{ $record->enrichment_note }}</p>@endif
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach (['nearest_waterway' => 'Waterway', 'elevation_m' => 'Elevation (m)', 'distance_to_waterway_m' => 'Waterway distance (m)', 'rainfall_24h_mm' => 'Rainfall 24h (mm)', 'rainfall_3d_mm' => 'Rainfall 3d (mm)', 'rainfall_7d_mm' => 'Rainfall 7d (mm)', 'temperature_c' => 'Temperature (°C)', 'temp_max_c' => 'Today’s elapsed max (°C)', 'temp_min_c' => 'Today’s elapsed min (°C)', 'wind_speed_kph' => 'Wind (km/h)', 'wind_direction_deg' => 'Wind direction (°)', 'humidity_pct' => 'Humidity (%)', 'drainage_index' => 'Drainage index', 'impervious_surface_ratio' => 'Impervious surface ratio', 'population_density_per_km2' => 'Population / km²', 'historical_flood_count_5y' => 'Historical flood count', 'storm_signal' => 'Storm signal', 'weather_observed_at' => 'Weather captured (PHT)'] as $field => $label)
                        <div><dt class="text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium text-slate-800">{{ $record->{$field} ?? 'Unavailable' }}</dd></div>
                    @endforeach
                </dl>
            </details>
        @endif
        <div class="flex justify-end gap-3">
            <a href="{{ auth()->user()->hasPermission('records.view') ? route('operational-records.index', ['dataset' => 'flood-records']) : (auth()->user()->hasPermission('gis.view') ? route('gis.index', ['hazard' => 'flood']) : url()->previous()) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 font-semibold text-slate-700">{{ $closed ? 'Back' : 'Cancel' }}</a>
            @if (!$closed)<button class="rounded-xl bg-sky-700 px-5 py-2.5 font-semibold text-white">{{ $editing ? 'Save location' : 'Record flood' }}</button>@endif
        </div>
    </form>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
@endsection
