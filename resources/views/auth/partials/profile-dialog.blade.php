<dialog id="user-profile-dialog" data-profile-dialog data-profile-url="{{ route('profile', ['modal' => 1]) }}" data-profile-reopen="{{ session('open_profile') || old('_profile_modal') ? 'true' : 'false' }}" aria-labelledby="user-profile-title" class="m-auto max-h-[85vh] w-[calc(100%_-_2rem)] max-w-xl overflow-y-auto rounded-2xl border-0 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/60">
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
        <h2 id="user-profile-title" class="text-xl font-bold">My Profile</h2>
        <button type="button" data-profile-close aria-label="Close profile" autofocus class="rounded-lg px-3 py-1 text-2xl text-slate-500 hover:bg-slate-100">×</button>
    </div>
    <div data-profile-content class="p-6" aria-live="polite"><p class="text-sm text-slate-500">Loading profile…</p></div>
</dialog>
