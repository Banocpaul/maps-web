@extends('layouts.app')
@section('title', 'Add Fire Hydrant | M.A.P.S.')
@section('content')
<div class="mx-auto max-w-4xl"><div class="mb-6"><h1 class="text-2xl font-bold text-slate-950">Add Fire Hydrant</h1><p class="mt-2 text-sm text-slate-600">Record a verified hydrant location and operational status.</p></div><form method="POST" action="{{ route('fire-hydrants.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">@include('fire.hydrants._form', ['fireHydrant' => null, 'method' => 'POST', 'submitLabel' => 'Create Hydrant'])</form></div>
@endsection
