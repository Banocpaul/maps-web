@extends('layouts.app')

@section('title', 'Create Public Advisory')

@section('content')
<div class="space-y-6">
    <div>
        <a
            href="{{ route('advisories.index') }}"
            class="text-sm font-bold text-blue-600 transition hover:text-blue-800"
        >
            ← Back to Public Advisories
        </a>

        <p class="mt-5 text-sm font-bold uppercase tracking-[0.16em] text-blue-600">
            Public Information
        </p>

        <h1 class="mt-1 text-3xl font-black text-slate-900">
            Create Public Advisory
        </h1>

        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
            Publish an official disaster-related notice for Mandaluyong residents.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('advisories.store') }}"
        enctype="multipart/form-data"
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"
    >
        @csrf

        @include('advisories._form')
    </form>
</div>
@endsection
