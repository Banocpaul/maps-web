@php
    $roleMessages = [
        'administrator' => 'Review accounts, backups, and system issues.',
        'fire-responder' => 'Review active incidents and continue the next required response action.',
        'flood-analyst' => 'Review flood reports, active floods, and saved forecasts.',
        'operations-manager' => 'Review reports, follow up incidents, and check delivery issues.',
    ];
@endphp

<section data-dismissible-panel data-auto-dismiss="3000" class="relative mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <button type="button" data-dismiss-alert aria-label="Close welcome panel" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-600">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12" /></svg>
    </button>
    <div class="flex flex-col gap-5 py-6 pl-6 pr-14 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">
                {{ $assignedRole->name }} Workspace
            </p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">
                Welcome back, {{ $user->first_name }}.
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                {{ $roleMessages[$roleSlug] ?? 'Here is your current Mandaluyong Flood & Fire overview.' }}
            </p>
        </div>

        <div class="flex-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left sm:text-right">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Today</p>
            <p class="mt-1 font-semibold text-slate-900">{{ now('Asia/Manila')->format('F j, Y') }}</p>
            <p class="mt-1 text-xs text-slate-500">Asia/Manila</p>
        </div>
    </div>
</section>

