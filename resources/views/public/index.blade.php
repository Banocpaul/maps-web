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



    <title>Public Information | M.A.P.S</title>



    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>



<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">



    {{-- Top navigation --}}

    @include('public.partials.header')



    <main>



        {{-- Hero section --}}

        <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white">

            <div class="mx-auto max-w-7xl px-4 pb-12 pt-6 sm:px-6 lg:px-8">

                <div class="max-w-3xl">



                    <h1 class="text-2xl font-black leading-tight sm:text-3xl">
                        Mandaluyong <span class="text-blue-400">Flood &amp; Fire Updates</span>
                    </h1>



                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        View updates freely. Sign in to report incidents or get barangay SMS alerts.
                    </p>

                    <a href="{{ route('public.incident-reports.create') }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700">
                        Report Incident
                    </a>

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



                {{-- Active flood and fire map --}}

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

                        Active Flood & Fire Map

                    </h3>



                    <p class="mt-3 text-sm leading-6 text-slate-600">

                        View publicly available flood-risk information and

                        barangay-level conditions.

                    </p>



                    <a

                        href="{{ route('public.flood-map') }}"

                        class="mt-6 inline-flex rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-700"

                    >

                        Open Active Map

                    </a>

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



                    <a
                        href="{{ route('public.weather') }}"
                        class="mt-6 inline-flex rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-700"
                    >
                        View Weather
                    </a>

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



                    <a
                        href="{{ route('public.advisories') }}"
                        class="mt-6 inline-flex rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700"
                    >
                        View Advisories
                    </a>

                </article>



                



            </div>

        </section>



        {{-- Emergency reminder and hotlines --}}
        <section class="bg-blue-700">
            <div class="mx-auto max-w-7xl px-4 py-10 text-white sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr] lg:items-center">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-[0.16em] text-blue-200">
                            Emergency Reminder
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            During an emergency, contact the proper authorities immediately.
                        </h2>

                        <p class="mt-2 max-w-2xl text-sm text-blue-100">
                            Do not rely only on online information when immediate assistance is required.
                        </p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <a
                                href="tel:0285332225"
                                class="rounded-xl border border-white/15 bg-white/10 p-4 transition hover:bg-white/15"
                            >
                                <p class="text-xs font-bold uppercase tracking-wider text-blue-200">
                                    CDRRMO / Disaster & Rescue
                                </p>
                                <p class="mt-1 text-xl font-black text-white">
                                    (02) 8533-2225
                                </p>
                            </a>

                            <div class="rounded-xl border border-white/15 bg-white/10 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-blue-200">
                                    Fire / BFP Mandaluyong
                                </p>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xl font-black text-white">
                                    <a href="tel:0285322189" class="hover:underline">
                                        (02) 8532-2189
                                    </a>
                                    <span class="text-blue-200">/</span>
                                    <a href="tel:0285322402" class="hover:underline">
                                        (02) 8532-2402
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:text-right">
                        <a
                            href="{{ route('login') }}"
                            class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-blue-700 transition hover:bg-blue-50"
                        >
                            Return to Staff Login
                        </a>
                    </div>
                </div>
            </div>
        </section>



    </main>



    @include('public.partials.footer')



</body>

</html>
