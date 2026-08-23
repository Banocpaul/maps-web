@extends('layouts.app')
@section('title', 'Edit Fire Hydrant | M.A.P.S.')
@section('content')
<div class="mx-auto max-w-4xl"><div class="mb-6"><h1 class="text-2xl font-bold text-slate-950">Edit {{ $fireHydrant->hydrant_code }}</h1><p class="mt-2 text-sm text-slate-600">Update verified hydrant information.</p></div><form method="POST" action="{{ route('fire-hydrants.update', $fireHydrant) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">@include('fire.hydrants._form', ['method' => 'PUT', 'submitLabel' => 'Save Changes'])</form></div>
@endsection
