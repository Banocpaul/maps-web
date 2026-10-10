@extends('layouts.app')
@section('title', 'Account Change Requests')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">Account Change Requests</h1><a href="{{ route('users.index') }}" class="font-semibold text-blue-700 underline">User Management</a></div>
    <p class="mt-2 text-sm text-slate-600">Review username and password changes. Passwords remain private.</p>
    <div class="mt-6 overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="bg-slate-100"><tr><th class="p-3">Account</th><th class="p-3">Requested (PHT)</th><th class="p-3">Requested changes</th><th class="p-3">Status</th><th class="p-3">Review</th></tr></thead>
        <tbody class="divide-y divide-slate-200">
        @forelse($changes as $change)
            <tr><td class="p-3"><p class="font-semibold">{{ $change->user->name }}</p><p class="text-slate-500">{{ $change->user->email }}</p></td><td class="whitespace-nowrap p-3">{{ $change->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td><td class="p-3">@if($change->requested_email)<p class="break-all">Username: {{ $change->requested_email }}</p>@endif @if($change->changes_password)<p>Password change</p>@endif</td><td class="p-3">{{ $change->status }}</td><td class="p-3">
                @if($change->status === 'Pending')
                    <div class="flex gap-2">
                    @foreach(['Approved' => 'Approve', 'Rejected' => 'Reject'] as $decision => $label)
                        <form method="POST" action="{{ route('users.password-requests.review', $change) }}">@csrf<input type="hidden" name="decision" value="{{ $decision }}"><button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold hover:bg-slate-100">{{ $label }}</button></form>
                    @endforeach
                    </div>
                @else <span class="text-slate-500">Processed</span> @endif
            </td></tr>
        @empty <tr><td colspan="5" class="p-6 text-center text-slate-500">No account change requests.</td></tr> @endforelse
        </tbody>
    </table></div>
    <div class="mt-5">{{ $changes->links() }}</div>
</section>
@endsection
