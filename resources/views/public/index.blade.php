<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>Public Information | M.A.P.S.</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">

    {{-- Top navigation --}}
    <header class="border-b border-slate-200 bg-slate-950 text-white shadow-lg">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">

            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600">
                    <svg
                        class="h-6 w-6 text-white"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 6.75V15m6-6v8.25m.5-12.75-7 3-4-1.5v13.5l4 1.5 7-3 4 1.5V6l-4-1.5Z"
                        />
                        <circle cx="12" cy="12" r="2.25" />
                    </svg>
                </div>

                <div>
                    <p class="text-xl font-black tracking-[0.16em]">
                        M.A.P.S.
                    </p>

                    <p class="text-xs text-slate-400">
                        Mandaluyong Analytics Predictive System
                    </p>
                </div>
            </div>

            <a
                href="{{ route('login') }}"
                class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white hover:text-slate-950"
            >
                Staff Login
            </a>
        </div>
    </header>

    <main>

        {{-- Hero section --}}
        <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
                <div class="max-w-3xl">

                    <span class="inline-flex rounded-full border border-blue-300/20 bg-blue-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">
                        Public Disaster Information Portal
                    </span>

                    <h1 class="mt-6 text-4xl font-black leading-tight sm:text-5xl">
                        Disaster awareness and safety information for
                        <span class="text-blue-400">
                            Mandaluyong residents
                        </span>
                    </h1>

                    <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">
                        Access public flood information, weather updates, safety
                        reminders, emergency contacts, and official disaster
                        advisories without signing in.
                    </p>
                </div>
            </div>
        </section>

        {{-- Current status --}}
        <section class="mx-auto -mt-8 max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xl md:grid-cols-3">

                <div class="rounded-xl bg-emerald-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">
                        System Status
                    </p>

                    <div class="mt-3 flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full bg-emerald-500"></span>

                        <p class="text-lg font-bold text-slate-900">
                            Public Portal Online
                        </p>
                    </div>

                    <p class="mt-2 text-sm text-slate-600">
                        Public disaster information is currently available.
                    </p>
                </div>

                <div class="rounded-xl bg-blue-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-700">
                        Coverage Area
                    </p>

                    <p class="mt-3 text-lg font-bold text-slate-900">
                        Mandaluyong City
                    </p>

                    <p class="mt-2 text-sm text-slate-600">
                        Information may be shown for all supported barangays.
                    </p>
                </div>

                <div class="rounded-xl bg-amber-50 p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-700">
                        Important Reminder
                    </p>

                    <p class="mt-3 text-lg font-bold text-slate-900">
                        Follow official instructions
                    </p>

                    <p class="mt-2 text-sm text-slate-600">
                        Always follow CDRRMO and local government advisories.
                    </p>
                </div>

            </div>
        </section>

        {{-- Public information cards --}}
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">

            <div class="mb-8">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-blue-600">
                    Public Services
                </p>

                <h2 class="mt-2 text-3xl font-black text-slate-900">
                    Available information
                </h2>

                <p class="mt-3 max-w-2xl text-slate-600">
                    Select a public service below to view disaster-related
                    information and preparedness resources.
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">

                {{-- Flood information --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5c1.5 0 1.5-1.5 3-1.5s1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5M3 20c1.5 0 1.5-1.5 3-1.5S7.5 20 9 20s1.5-1.5 3-1.5 1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3 7.5 9.75a5.5 5.5 0 1 0 9 0L12 3Z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Flood Information
                    </h3>

                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        View publicly available flood-risk information and
                        barangay-level conditions.
                    </p>

                    <button
                        type="button"
                        class="mt-6 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700"
                    >
                        View Flood Information
                    </button>
                </article>

                {{-- Weather --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3v2.25M12 18.75V21M3 12h2.25M18.75 12H21M5.64 5.64l1.59 1.59m9.54 9.54 1.59 1.59m0-12.72-1.59 1.59m-9.54 9.54-1.59 1.59"
                            />
                            <circle cx="12" cy="12" r="4" />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Weather Updates
                    </h3>

                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Review current weather conditions relevant to flood and
                        disaster preparedness.
                    </p>

                    <button
                        type="button"
                        class="mt-6 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-700"
                    >
                        View Weather
                    </button>
                </article>

                {{-- Advisories --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-100 text-red-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Public Advisories
                    </h3>

                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Read official warnings, evacuation information, and
                        disaster-related announcements.
                    </p>

                    <button
                        type="button"
                        class="mt-6 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700"
                    >
                        View Advisories
                    </button>
                </article>

                {{-- Map --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 18.75 3.75 16.5V5.25L9 7.5m0 11.25 6-2.25m-6 2.25V7.5m6 9 5.25 2.25V7.5L15 5.25m0 11.25V5.25m0 0L9 7.5"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Public Risk Map
                    </h3>

                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        View a read-only disaster map showing publicly available
                        geographic information.
                    </p>

                    <button
                        type="button"
                        class="mt-6 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700"
                    >
                        Open Public Map
                    </button>
                </article>

                {{-- Safety guides --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.5 5.25A2.25 2.25 0 0 1 6.75 3h4.5A2.25 2.25 0 0 1 13.5 5.25V21a3.75 3.75 0 0 0-3.75-3.75h-3A2.25 2.25 0 0 1 4.5 15V5.25Zm15 0A2.25 2.25 0 0 0 17.25 3h-3A2.25 2.25 0 0 0 12 5.25V21a3.75 3.75 0 0 1 3.75-3.75h1.5A2.25 2.25 0 0 0 19.5 15V5.25Z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Safety Guidelines
                    </h3>

                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        Learn what to prepare and what actions to take before,
                        during, and after disasters.
                    </p>

                    <button
                        type="button"
                        class="mt-6 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700"
                    >
                        Read Safety Guides
                    </button>
                </article>

                {{-- Emergency contacts --}}
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 0 0-1.173.417l-.97 1.293a1.125 1.125 0 0 1-1.21.38 12.035 12.035 0 0 1-7.143-7.143 1.125 1.125 0 0 1 .38-1.21l1.293-.97c.36-.27.525-.728.417-1.173L6.963 3.102A1.125 1.125 0 0 0 5.872 2.25H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-bold">
                        Emergency Contacts
                    </h3>

                    <div class="mt-3 space-y-3 text-sm text-slate-600">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="font-bold text-slate-900">
                                CDRRMO Mandaluyong
                            </p>

                            <p>
                                Contact number to be added
                            </p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="font-bold text-slate-900">
                                Fire and Rescue
                            </p>

                            <p>
                                Contact number to be added
                            </p>
                        </div>
                    </div>
                </article>

            </div>
        </section>

        {{-- Safety reminder --}}
        <section class="bg-blue-700">
            <div class="mx-auto max-w-7xl px-4 py-10 text-white sm:px-6 lg:px-8">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">

                    <div>
                        <p class="text-sm font-bold uppercase tracking-[0.16em] text-blue-200">
                            Emergency Reminder
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            During an emergency, contact the proper authorities immediately.
                        </h2>

                        <p class="mt-2 text-sm text-blue-100">
                            Do not rely only on online information when immediate
                            assistance is required.
                        </p>
                    </div>

                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-blue-700 transition hover:bg-blue-50"
                    >
                        Return to Staff Login
                    </a>
                </div>
            </div>
        </section>

    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
            <p class="font-semibold text-slate-700">
                M.A.P.S. — Mandaluyong Analytics Predictive System
            </p>

            <p>
                Public disaster information portal
            </p>
        </div>
    </footer>

</body>
</html>