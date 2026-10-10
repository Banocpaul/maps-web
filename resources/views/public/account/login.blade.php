@extends('public.account.layout')
@section('title', 'Public Login')
@section('content')
<section class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h1 class="text-3xl font-black">Public login</h1>
    <p class="mt-3 text-slate-600">Sign in to report an incident or manage alerts for your barangay.</p>
    <form method="POST" action="{{ route('public.login.attempt') }}" class="mt-7 space-y-5">
        @csrf
        <label for="email" class="block text-sm font-semibold">Email address<input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <label for="password" class="block text-sm font-semibold">Password<input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="remember" value="1" class="h-4 w-4">Remember me</label>
        <button class="w-full rounded-xl bg-blue-700 px-6 py-3 font-bold text-white hover:bg-blue-800">Sign in</button>
    </form>
    <p class="mt-6 text-sm">New here? <a href="{{ route('public.register') }}" class="font-semibold text-blue-700 underline">Create a public account</a></p>
    <a href="{{ route('public.portal') }}" class="mt-4 block text-sm font-semibold text-slate-600 underline">Continue viewing without an account</a>
</section>
@endsection
