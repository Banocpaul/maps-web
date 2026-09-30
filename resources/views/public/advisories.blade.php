<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Public Advisories | M.A.P.S.</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
@php
    $typeLabels = [
        'flood' => 'Flood',
        'fire' => 'Fire',
        'weather' => 'Weather',
        'evacuation' => 'Evacuation',
        'class-suspension' => 'Class Suspension',
        'general' => 'General',
    ];

    $typeClasses = [
        'flood' => 'bg-blue-100 text-blue-800',
        'fire' => 'bg-red-100 text-red-800',
        'weather' => 'bg-cyan-100 text-cyan-800',
        'evacuation' => 'bg-amber-100 text-amber-800',
        'class-suspension' => 'bg-violet-100 text-violet-800',
        'general' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<header class="border-b border-slate-200 bg-white shadow-sm">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <a
            href="{{ route('public.portal') }}"
            class="text-sm font-bold text-blue-600 transition hover:text-blue-800"
        >
            â† Back to Public Portal
        </a>

        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
            Mandaluyong City
        </p>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-gradient-to-r from-red-700 to-red-600 px-6 py-8 text-white sm:px-8">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-100">
                Official Public Information
            </p>

            <h1 class="mt-2 text-3xl font-black sm:text-4xl">
                Public Advisories
            </h1>

            <p class="mt-3 max-w-2xl text-sm leading-6 text-red-50">
                Official warnings, evacuation information, weather notices,
                class suspension announcements, and disaster-related updates.
                Newest advisories are shown first.
            </p>
        </div>

        @if ($advisories->isEmpty())
            <div class="px-6 py-16 text-center sm:px-8">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                </div>

                <h2 class="mt-5 text-xl font-black text-slate-900">
                    No public advisories
                </h2>

                <p class="mt-2 text-sm text-slate-500">
                    There are currently no published advisories.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-200">
                @foreach ($advisories as $advisory)
                    @php
                        $posterName = $advisory->user?->full_name
                            ?: $advisory->user?->name
                            ?: 'Mandaluyong CDRRMO';

                        $roleName = $advisory->user?->role?->name
                            ?? 'Official Department';

                        $photoUrl = null;

                        if ($advisory->photo_path) {
                            try {
                                $photoUrl = \Illuminate\Support\Facades\Storage::disk(
                                    config('filesystems.advisory_disk', 'public')
                                )->url($advisory->photo_path);
                            } catch (\Throwable $exception) {
                                $photoUrl = null;
                            }
                        }
                    @endphp

                    <article class="px-5 py-7 sm:px-8">
                        <div class="grid gap-5 md:grid-cols-[170px_minmax(0,1fr)]">
                            <div>
                                <div class="overflow-hidden rounded-2xl border-2 border-red-200 bg-red-50 text-center shadow-sm">
                                    <div class="bg-red-600 px-3 py-2 text-xs font-black uppercase tracking-[0.18em] text-white">
                                        Advisory Date
                                    </div>

                                    <div class="px-4 py-5">
                                        <p class="text-3xl font-black leading-none text-red-900">
                                            {{ $advisory->advisory_date->format('M d') }}
                                        </p>

                                        <p class="mt-2 text-lg font-black text-red-700">
                                            {{ $advisory->advisory_date->format('Y') }}
                                        </p>

                                        <p class="mt-2 text-xs font-bold uppercase tracking-wider text-red-600">
                                            {{ $advisory->advisory_date->format('l') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wider {{ $typeClasses[$advisory->type] ?? $typeClasses['general'] }}"
                                    >
                                        {{ $typeLabels[$advisory->type] ?? ucfirst(str_replace('-', ' ', $advisory->type)) }}
                                    </span>

                                    <span class="text-xs font-semibold text-slate-400">
                                        Published {{ $advisory->created_at?->format('M d, Y h:i A') }}
                                    </span>
                                </div>

                                <h2 class="mt-3 break-words text-2xl font-black text-slate-900">
                                    {{ $advisory->subject }}
                                </h2>

                                <div class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-slate-700">
                                    {{ $advisory->message }}
                                </div>

                                @if ($photoUrl)
                                    <div class="mt-5">
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Photo attached to {{ $advisory->subject }}"
                                            class="max-h-[520px] w-full rounded-2xl border border-slate-200 object-contain bg-slate-50"
                                            loading="lazy"
                                        >
                                    </div>
                                @endif                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($advisories->hasPages())
                <div class="border-t border-slate-200 px-6 py-5 sm:px-8">
                    {{ $advisories->links() }}
                </div>
            @endif
        @endif
    </section>
</main>

<footer class="mt-10 border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
        Mandaluyong Flood & Fire â€” Public Disaster Information Portal
    </div>
</footer>
</body>
</html>
