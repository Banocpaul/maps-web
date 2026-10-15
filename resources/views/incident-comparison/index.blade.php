@extends('layouts.app')
@section('title', 'Incident Comparison Report | M.A.P.S.')
@section('page-title', 'Incident Comparison Report')
@section('content')
@include('incident-comparison.styles')
<section class="ytd-panel ytd-no-print"><form method="GET" class="ytd-controls">
<label>Calendar year<select name="year">@for($year = now('Asia/Manila')->year; $year >= 2001; $year--)<option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }} versus {{ $year - 1 }}{{ $year === now('Asia/Manila')->year ? ' (current year incomplete)' : '' }}</option>@endfor</select></label>
<label>Hazard<select name="hazard">@if(count($types) > 1)<option value="all" @selected($filters['hazard'] === 'all')>Flood & fire</option>@endif @foreach($types as $type)<option value="{{ $type }}" @selected($filters['hazard'] === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
<label>Barangay<select name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string)$filters['barangay_id'] === (string)$barangay->id)>{{ $barangay->name }}</option>@endforeach</select></label>
<button>Update report</button><a class="ytd-link" target="_blank" rel="noopener" href="{{ route('incident-comparison.index', array_merge($filters, ['print' => 1])) }}">Print / Save as PDF</a>
</form></section>
@include('incident-comparison.content')
@endsection
