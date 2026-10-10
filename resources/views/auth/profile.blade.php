@extends(auth()->user()->isPublicResident() ? 'public.account.layout' : 'layouts.app')
@section('title', 'My Password')
@section('content')
<section class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h1 class="text-2xl font-bold">My password</h1>
    <p class="mt-2 text-sm text-slate-600">Your new password needs administrator approval. Keep using your current password while waiting.</p>
    @if($change)
        <p class="mt-5 rounded-xl bg-slate-100 p-4 text-sm" role="status">Latest request: <strong>{{ $change->status }}</strong> · {{ $change->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
    @endif
    @if($change?->status !== 'Pending')
        <form method="POST" action="{{ route('password.request') }}" class="mt-6 space-y-4">
            @csrf
            <label for="current_password" class="block text-sm font-semibold">Current password<input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
            @include('auth.partials.new-password-fields')
            <button class="rounded-xl bg-blue-700 px-5 py-3 font-semibold text-white hover:bg-blue-800">Request password change</button>
        </form>
    @endif
</section>
@endsection
