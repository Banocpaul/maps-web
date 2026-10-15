@extends('layouts.app')
@section('title', 'Incident Comparison Report | M.A.P.S.')
@section('page-title', 'Incident Comparison Report')
@section('content')
@include('incident-comparison.styles')
<section class="ytd-panel ytd-no-print"><h2 class="ytd-heading">Compare two years</h2><p class="ytd-muted" style="margin-bottom:16px">Choose any two calendar years. Changes show the comparison year relative to the baseline year.</p>
@if($errors->any())<p role="alert" class="ytd-increase">{{ $errors->first() }}</p>@endif
<form method="GET" class="ytd-controls">
<label>Baseline year<select name="baseline_year">@for($year = now('Asia/Manila')->year; $year >= 2001; $year--)<option value="{{ $year }}" @selected($filters['baseline_year'] === $year)>{{ $year }}{{ $year === now('Asia/Manila')->year ? ' (incomplete)' : '' }}</option>@endfor</select></label>
<span class="ytd-versus" aria-hidden="true">vs</span>
<label>Comparison year<select name="year">@for($year = now('Asia/Manila')->year; $year >= 2001; $year--)<option value="{{ $year }}" @selected($filters['year'] === $year)>{{ $year }}{{ $year === now('Asia/Manila')->year ? ' (incomplete)' : '' }}</option>@endfor</select></label>
<label>Hazard<select name="hazard">@if(count($types) > 1)<option value="all" @selected($filters['hazard'] === 'all')>Flood & fire</option>@endif @foreach($types as $type)<option value="{{ $type }}" @selected($filters['hazard'] === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
<label>Barangay<select name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string)$filters['barangay_id'] === (string)$barangay->id)>{{ $barangay->name }}</option>@endforeach</select></label>
<button>Compare years</button><a class="ytd-link" target="_blank" rel="noopener" href="{{ route('incident-comparison.index', array_merge($filters, ['print' => 1])) }}">Print / Save as PDF</a>
</form></section>
@include('incident-comparison.content')
@endsection
