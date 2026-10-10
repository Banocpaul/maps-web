@extends('public.account.layout')
@section('title', 'My Account & Alerts')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h1 class="text-3xl font-black">My account & alerts</h1>
    <p class="mt-3 text-slate-600">Signed in as {{ $resident->email }}. Manage your barangay and SMS preferences below.</p>
    <form method="POST" action="{{ route('public.account.update') }}" class="mt-7">
        @csrf @method('PUT')
        @include('public.account.fields')
        <button class="mt-6 rounded-xl bg-blue-700 px-6 py-3 font-bold text-white hover:bg-blue-800">Save preferences</button>
    </form>
    <div class="mt-6 flex flex-wrap gap-4 border-t border-slate-200 pt-6">
        <a href="{{ route('public.incident-reports.create') }}" class="font-semibold text-blue-700 underline">Report a flood or fire</a>
        <a href="{{ route('public.reports') }}" class="font-semibold text-blue-700 underline">My Reports</a>
    </div>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h2 class="text-xl font-bold">Password</h2>
    <a href="{{ route('profile') }}" class="mt-3 inline-block font-semibold text-blue-700 underline">Request a password change</a>
</section>
@endsection
