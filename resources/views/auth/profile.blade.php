@extends(auth()->user()->isPublicResident() ? 'public.account.layout' : 'layouts.app')
@section('title', 'My Profile')
@section('content')
<section class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="mb-5 text-2xl font-bold">My Profile</h1>
    @include('auth.partials.profile-content')
</section>
@endsection
