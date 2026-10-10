<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your new password | M.A.P.S</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 px-4 py-12 text-slate-900">
<main class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <img src="{{ asset('images/cdrrmo-logo.jpg') }}" alt="Mandaluyong CDRRMO" class="mb-4 h-12 w-12 rounded-full object-contain">
    <h1 class="text-2xl font-bold">Set your new password</h1>
    <p class="mt-2 text-sm text-slate-600">Your password was reset. Choose a new password to continue. It takes effect immediately.</p>
    @if($errors->any())
        <ul role="alert" class="mt-4 space-y-1 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <form method="POST" action="{{ route('password.complete') }}" class="mt-6 space-y-4">
        @csrf @method('PUT')
        @include('auth.partials.new-password-fields')
        <button class="w-full rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800">Save new password</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm font-semibold text-slate-600 underline">Sign out</button></form>
</main>
</body>
</html>
