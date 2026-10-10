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
    <h2 class="text-xl font-bold">Change password</h2>
    <form method="POST" action="{{ route('public.account.password') }}" class="mt-5 space-y-4">
        @csrf @method('PUT')
        <label for="current_password" class="block text-sm font-semibold">Current password<input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <label for="password" class="block text-sm font-semibold">New password<input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <label for="password_confirmation" class="block text-sm font-semibold">Confirm new password<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <button class="rounded-xl border border-slate-300 px-6 py-3 font-bold hover:bg-slate-50">Change password</button>
    </form>
</section>
@endsection
