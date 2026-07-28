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

    <title>Sign In | M.A.P.S.</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="relative min-h-screen overflow-hidden">

        {{-- Subtle institutional background --}}
        <div class="absolute inset-0 bg-slate-100"></div>

        <div
            class="absolute inset-0 opacity-[0.035]"
            style="
                background-image:
                    linear-gradient(rgba(15, 23, 42, 0.8) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(15, 23, 42, 0.8) 1px, transparent 1px);
                background-size: 48px 48px;
            "
        ></div>

        <div class="absolute left-0 top-0 h-1 w-full bg-blue-700"></div>

        <main class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="grid w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10 lg:grid-cols-[42%_58%]"
            >

                {{-- =====================================================
                     INSTITUTIONAL INFORMATION PANEL
                     ===================================================== --}}
                <section
                    class="relative hidden min-h-[650px] overflow-hidden bg-[#13233f] px-10 py-10 text-white lg:flex lg:flex-col"
                >
                    {{-- Subtle panel pattern --}}
                    <div
                        class="absolute inset-0 opacity-[0.045]"
                        style="
                            background-image:
                                radial-gradient(circle at 1px 1px, white 1px, transparent 0);
                            background-size: 24px 24px;
                        "
                    ></div>

                    <div class="absolute -bottom-32 -right-32 h-80 w-80 rounded-full border border-white/10"></div>
                    <div class="absolute -bottom-20 -right-20 h-56 w-56 rounded-full border border-white/10"></div>

                    <div class="relative z-10 flex h-full flex-col">

                        {{-- Brand --}}
                        <header class="flex items-center gap-4 border-b border-white/10 pb-8">
                            <div
                                class="flex h-14 w-14 flex-none items-center justify-center rounded-xl bg-blue-600"
                            >
                                <svg
                                    class="h-8 w-8 text-white"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 6.75V15m6-6v8.25m.5-12.75-7 3-4-1.5v13.5l4 1.5 7-3 4 1.5V6l-4-1.5Z"
                                    />

                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.25"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="text-2xl font-bold tracking-[0.18em]">
                                    M.A.P.S.
                                </p>

                                <p class="mt-1 text-xs leading-5 text-blue-100">
                                    Mandaluyong Analytics Predictive System
                                </p>
                            </div>
                        </header>

                        {{-- Portal description --}}
                        <div class="mt-14">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-300"
                            >
                                Mandaluyong City
                            </p>

                            <h1 class="mt-4 max-w-sm text-3xl font-bold leading-tight">
                                Disaster Risk Management Operations Portal
                            </h1>

                            <p class="mt-5 max-w-sm text-sm leading-7 text-slate-300">
                                A centralized information system supporting disaster
                                preparedness, operational monitoring, risk assessment,
                                and evidence-based decision-making.
                            </p>
                        </div>

                        {{-- System capabilities --}}
                        <div class="mt-10">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400"
                            >
                                System Capabilities
                            </p>

                            <div class="mt-5 space-y-4">

                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-white/10 text-blue-200"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 3v18m6-15-6 6-6-6m12 12-6-6-6 6"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <p class="text-sm font-semibold text-white">
                                            Flood Risk Prediction
                                        </p>

                                        <p class="mt-1 text-xs leading-5 text-slate-400">
                                            Barangay-level risk assessment and predictive analytics.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-white/10 text-blue-200"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9 18.75 3.75 16.5V5.25L9 7.5m0 11.25 6-2.25m-6 2.25V7.5m6 9 5.25 2.25V7.5L15 5.25m0 11.25V5.25m0 0L9 7.5"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <p class="text-sm font-semibold text-white">
                                            Geographic Information System
                                        </p>

                                        <p class="mt-1 text-xs leading-5 text-slate-400">
                                            Hazard mapping, barangay visualization, and hydrant locations.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-white/10 text-blue-200"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 3.75c1.5 3 4.5 4.5 4.5 8.25A4.5 4.5 0 0 1 12 16.5 4.5 4.5 0 0 1 7.5 12c0-2.25 1.125-4.125 2.625-5.625.375 1.875 1.125 3 1.875 3.75.375-2.25 0-4.125 0-6.375Z"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9.75 15.75c0 2.25 1.125 3.75 2.25 4.5 1.125-.75 2.25-2.25 2.25-4.5"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <p class="text-sm font-semibold text-white">
                                            Fire Response Support
                                        </p>

                                        <p class="mt-1 text-xs leading-5 text-slate-400">
                                            Incident records, hydrant identification, and responder support.
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-white/10 text-blue-200"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M4 19.5h16M6.5 17V10m5 7V5m5 12v-8"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <p class="text-sm font-semibold text-white">
                                            Operational Analytics
                                        </p>

                                        <p class="mt-1 text-xs leading-5 text-slate-400">
                                            Historical trends, reports, and decision-support information.
                                        </p>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Institutional footer --}}
                        <footer class="mt-auto border-t border-white/10 pt-6">
                            <p class="text-xs font-semibold text-slate-300">
                                Developed for disaster risk reduction and response operations
                            </p>

                            <p class="mt-2 text-xs leading-5 text-slate-500">
                                Mandaluyong City Disaster Risk Reduction and Management Office
                            </p>
                        </footer>
                    </div>
                </section>

                {{-- =====================================================
                     LOGIN PANEL
                     ===================================================== --}}
                <section class="flex min-h-[650px] items-center bg-white px-6 py-10 sm:px-10 lg:px-14">
                    <div class="mx-auto w-full max-w-md">

                        {{-- Mobile branding --}}
                        <div class="mb-10 border-b border-slate-200 pb-6 lg:hidden">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-blue-700"
                                >
                                    <svg
                                        class="h-7 w-7 text-white"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 6.75V15m6-6v8.25m.5-12.75-7 3-4-1.5v13.5l4 1.5 7-3 4 1.5V6l-4-1.5Z"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.25"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p class="text-xl font-bold tracking-[0.16em] text-slate-900">
                                        M.A.P.S.
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Mandaluyong Analytics Predictive System
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Form heading --}}
                        <header>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700"
                            >
                                Authorized Personnel Access
                            </p>

                            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                                Sign in to M.A.P.S.
                            </h2>

                            <p class="mt-3 text-sm leading-6 text-slate-500">
                                Enter your authorized account credentials to access the
                                disaster management operations portal.
                            </p>
                        </header>

                        {{-- Success message --}}
                        @if (session('success'))
                            <div
                                class="mt-7 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-emerald-800"
                                role="alert"
                            >
                                <div
                                    class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m5 13 4 4L19 7"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">
                                        Operation completed
                                    </p>

                                    <p class="mt-1 text-sm text-emerald-700">
                                        {{ session('success') }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Validation errors --}}
                        @if ($errors->any())
                            <div
                                class="mt-7 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-red-800"
                                role="alert"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-red-100 text-red-700"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                            />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            Unable to sign in
                                        </p>

                                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Login form --}}
                        <form
                            method="POST"
                            action="{{ route('login.attempt') }}"
                            class="mt-8 space-y-5"
                        >
                            @csrf

                            {{-- Email --}}
                            <div>
                                <label
                                    for="email"
                                    class="mb-2 block text-sm font-semibold text-slate-700"
                                >
                                    Email address
                                </label>

                                <div class="relative">
                                    <div
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15a2.25 2.25 0 0 1-2.25-2.25V6.75M21.75 6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0-8.21 5.474a2.75 2.75 0 0 1-3.08 0L2.25 6.75"
                                            />
                                        </svg>
                                    </div>

                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        autocomplete="email"
                                        required
                                        autofocus
                                        placeholder="Enter your email address"
                                        class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                                    >
                                </div>
                            </div>

                            {{-- Password --}}
                            <div>
                                <label
                                    for="password"
                                    class="mb-2 block text-sm font-semibold text-slate-700"
                                >
                                    Password
                                </label>

                                <div class="relative">
                                    <div
                                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6a2.25 2.25 0 0 1 2.25-2.25Z"
                                            />
                                        </svg>
                                    </div>

                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        autocomplete="current-password"
                                        required
                                        placeholder="Enter your password"
                                        class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10"
                                    >
                                </div>
                            </div>

                            {{-- Remember account --}}
                            <div class="flex items-center justify-between gap-4">
                                <label
                                    for="remember"
                                    class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600"
                                >
                                    <input
                                        id="remember"
                                        type="checkbox"
                                        name="remember"
                                        value="1"
                                        @checked(old('remember'))
                                        class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                                    >

                                    <span>Remember this account</span>
                                </label>

                                <span class="text-xs text-slate-400">
                                    Authorized users only
                                </span>
                            </div>

                            {{-- Submit --}}
                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-700/20"
                            >
                                <span>Sign In</span>

                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"
                                    />
                                </svg>
                            </button>
                        </form>

                        {{-- Public access --}}
                        <div class="mt-7 border-t border-slate-200 pt-6">
                            <div class="text-center">
                                <p class="text-sm font-semibold text-slate-700">
                                    Public information access
                                </p>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    View public disaster information without signing in.
                                </p>
                            </div>

                            <a
                                href="{{ route('public.portal') }}"
                                class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-600/10"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 18.75 3.75 16.5V5.25L9 7.5m0 11.25 6-2.25m-6 2.25V7.5m6 9 5.25 2.25V7.5L15 5.25m0 11.25V5.25m0 0L9 7.5"
                                    />
                                </svg>

                                <span>View Public Disaster Information</span>
                            </a>
                        </div>

                        {{-- System footer --}}
                        <footer class="mt-8 border-t border-slate-200 pt-5">
                            <div
                                class="flex flex-col gap-2 text-center text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:text-left"
                            >
                                <span>M.A.P.S. Version 1.0</span>

                                <span>CDRRMO Operations System</span>
                            </div>
                        </footer>

                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>