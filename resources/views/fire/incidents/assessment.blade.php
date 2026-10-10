@extends('layouts.app')

@section('title', 'Finalize Fire Record | M.A.P.S')
@section('page-title', 'Finalize Fire Record')
@section('page-description', $fireIncident->incident_number . ' · For Assessment')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-950">{{ $fireIncident->incident_number }}</h1>
        <p class="mt-1 text-slate-600">{{ $fireIncident->barangay?->name }} · {{ $fireIncident->location }}</p>
        <p class="mt-3 text-sm text-slate-600">Fire out: {{ $fireIncident->fire_out_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT) · {{ $fireIncident->duration_minutes }} minutes</p>
    </section>
    <form method="POST" action="{{ route('fire-incidents.finalize', $fireIncident) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <h2 class="text-lg font-bold text-slate-950">Impact Assessment</h2>
        <p class="mt-2 text-sm text-slate-600">Enter the verified totals. Enter 0 only when none were affected or destroyed.</p>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @foreach(['individuals_affected' => 'Total Individuals Affected', 'houses_destroyed' => 'Total Houses Destroyed'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-semibold text-slate-700">{{ $label }} *</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" max="4294967295" step="1" required value="{{ old($field, $fireIncident->{$field}) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div class="sm:col-span-2">
                <label for="cause" class="block text-sm font-semibold text-slate-700">Cause (if confirmed)</label>
                <input id="cause" name="cause" maxlength="255" value="{{ old('cause', $fireIncident->cause) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('cause')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="remarks" class="block text-sm font-semibold text-slate-700">Remarks</label>
                <textarea id="remarks" name="remarks" maxlength="2000" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('remarks', $fireIncident->remarks) }}</textarea>
                @error('remarks')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="mt-5 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Finalizing confirms these details and locks the record against further changes or deletion.</p>
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <a href="{{ route('fire-incidents.show', $fireIncident) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Cancel</a>
            <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 font-semibold text-white hover:bg-sky-800">Finalize and Lock Record</button>
        </div>
    </form>
</div>
@endsection
