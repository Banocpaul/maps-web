<div class="flex items-center gap-4">
    <div class="flex h-16 w-16 flex-none items-center justify-center overflow-hidden rounded-full bg-[#16324F] text-xl font-bold text-white">@include('auth.partials.profile-avatar')</div>
    <div class="min-w-0"><p class="break-words text-lg font-bold">{{ $profileUser->name }}</p><p class="break-all text-sm text-slate-500">{{ $profileUser->email }}</p></div>
</div>
<dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
    <div><dt class="text-slate-500">Role</dt><dd class="mt-1 font-semibold">{{ $profileUser->role()->value('name') ?? 'Unassigned' }}</dd></div>
    <div><dt class="text-slate-500">Barangay</dt><dd class="mt-1 font-semibold">{{ $profileUser->barangay()->value('name') ?? 'Not assigned' }}</dd></div>
</dl>
@if($errors->any())
    <ul role="alert" class="mt-4 space-y-1 rounded-xl bg-red-50 p-4 text-sm text-red-800">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
@endif
<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
    @csrf @method('PUT')
    <input type="hidden" name="_profile_modal" value="{{ $inModal ? '1' : '0' }}">
    <div class="grid gap-4 sm:grid-cols-2">
        <label for="profile_first_name" class="block text-sm font-semibold">First name<input id="profile_first_name" name="first_name" value="{{ old('first_name', $profileUser->first_name) }}" required maxlength="100" autocomplete="given-name" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
        <label for="profile_last_name" class="block text-sm font-semibold">Last name<input id="profile_last_name" name="last_name" value="{{ old('last_name', $profileUser->last_name) }}" required maxlength="100" autocomplete="family-name" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
    </div>
    <label for="profile_photo" class="block text-sm font-semibold">Profile picture<input id="profile_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm"></label>
    <p class="text-xs text-slate-500">JPG, PNG or WebP · Up to 2 MB</p>
    <button class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Save profile</button>
</form>
<div class="mt-6 border-t border-slate-200 pt-5">
    <h3 class="font-bold">Username & password</h3>
    <p class="mt-1 text-sm text-slate-500">Changes need admin approval. Your current login stays active while waiting.</p>
    @if($change)
        <p class="mt-3 rounded-lg bg-slate-100 p-3 text-sm">Latest request: <strong>{{ $change->status }}</strong>@if($change->requested_email)<span class="mt-1 block break-all">New username: {{ $change->requested_email }}</span>@endif</p>
    @endif
    @if($change?->status !== 'Pending')
        <details class="mt-4" @if($errors->hasAny(['email', 'password', 'current_password'])) open @endif><summary class="cursor-pointer text-sm font-semibold text-blue-700">Request login changes</summary>
            <form method="POST" action="{{ route('password.request') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="_profile_modal" value="{{ $inModal ? '1' : '0' }}">
                <label for="account_current_password" class="block text-sm font-semibold">Current password<input id="account_current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
                <label for="account_email" class="block text-sm font-semibold">New username (email)<input id="account_email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" placeholder="Leave blank to keep your username" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
                <label for="account_password" class="block text-sm font-semibold">New password<input id="account_password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" placeholder="Leave blank to keep your password" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
                <label for="account_password_confirmation" class="block text-sm font-semibold">Confirm new password<input id="account_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="255" class="mt-2 block w-full rounded-lg border border-slate-300 p-3"></label>
                <button class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold hover:bg-slate-50">Submit for approval</button>
            </form>
        </details>
    @endif
</div>
