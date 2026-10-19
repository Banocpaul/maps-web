@extends('layouts.app')
@section('title','Historical Incident Analytics | M.A.P.S.')
@section('content')
<div class="mx-auto max-w-7xl space-y-5">
<header><h1 class="text-2xl font-bold">Historical Incident Analytics & Archives</h1><p class="text-slate-600">Explore annual reports, then print or save as PDF.</p></header>
<nav class="flex flex-wrap gap-3"><a class="rounded-lg bg-blue-700 px-4 py-2 text-white" href="{{ route('incident-archives.analytics') }}">Annual Analytics</a><a class="rounded-lg border px-4 py-2 text-blue-700" href="{{ route('incident-archives.index') }}">Archived Records (older than 3 years)</a></nav>
<form action="{{ route('incident-archives.analytics') }}" method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4">
<label>Incident year<select name="year" class="block rounded border p-2">@for($y=$now->year;$y>=2001;$y--)<option value="{{ $y }}" @selected($year===$y)>{{ $y }}</option>@endfor</select></label>
<label>Incident type<select name="type" class="block rounded border p-2">@foreach(['all'=>'Fire & flood','fire'=>'Fire','flood'=>'Flood'] as $v=>$label)<option value="{{ $v }}" @selected($type===$v)>{{ $label }}</option>@endforeach</select></label>
<label>Data scope<select name="scope" class="block rounded border p-2"><option value="all" @selected($scope==='all')>All historical records</option><option value="archived" @selected($scope==='archived')>Archived only</option></select></label>
<button class="rounded-lg bg-blue-700 px-4 py-2 text-white">Generate report</button>
<a target="_blank" rel="noopener" class="rounded-lg border border-blue-500 px-4 py-2 text-blue-700" href="{{ route('incident-archives.analytics', ['year'=>$year,'type'=>$type,'scope'=>$scope,'print'=>1]) }}">Print / Save as PDF</a>
</form>
@include('incident-archives.analytics-content')
</div>
@endsection