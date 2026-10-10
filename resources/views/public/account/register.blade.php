@extends('public.account.layout')
@section('title', 'Create Public Account')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h1 class="text-3xl font-black">Create a public account</h1>
    <p class="mt-3 text-slate-600">Report flood and fire incidents and choose SMS alerts for your barangay. Public maps, weather, and advisories are available without an account.</p>
    <form method="POST" action="{{ route('public.register.store') }}" class="mt-7 space-y-5">
        @csrf
        @include('public.account.fields')
        <label for="email" class="block text-sm font-semibold">Email address<input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <div class="grid gap-5 sm:grid-cols-2">
            <label for="password" class="block text-sm font-semibold">Password (at least 8 characters)<input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
            <label for="password_confirmation" class="block text-sm font-semibold">Confirm password<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8" maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        </div>
        <button class="rounded-xl bg-blue-700 px-6 py-3 font-bold text-white hover:bg-blue-800">Create account</button>
    </form>
    <p class="mt-6 text-sm">Already registered? <a href="{{ route('public.login') }}" class="font-semibold text-blue-700 underline">Public login</a></p>
</section>
@endsection
