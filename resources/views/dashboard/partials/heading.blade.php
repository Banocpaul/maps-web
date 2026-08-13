@php
    $roleMessages = [
        'administrator' => 'Monitor users, security events, and essential system services.',
        'fire-responder' => 'Review active incidents and continue the next required response action.',
        'flood-analyst' => 'Review weather, flood intelligence, and the latest analytical records.',
        'operations-officer' => 'Maintain citywide awareness across fire, flood, GIS, and communications.',
    ];
@endphp

<section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-5 px-6 py-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">
                {{ $assignedRole->name }} Workspace
            </p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950 sm:text-3xl">
                Welcome back, {{ $user->first_name }}.
            </h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                {{ $roleMessages[$roleSlug] ?? 'Here is your current M.A.P.S. overview.' }}
            </p>
        </div>

        <div class="flex-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left sm:text-right">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Today</p>
            <p class="mt-1 font-semibold text-slate-900">{{ now()->format('F j, Y') }}</p>
            <p class="mt-1 text-xs text-slate-500">Asia/Manila</p>
        </div>
    </div>
</section>

