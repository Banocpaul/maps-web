<!DOCTYPE html>
<html lang="en" class="h-full">
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

    <title>
        @yield('title', 'M.A.P.S.')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    @stack('styles')
</head>

<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-screen">

        {{-- Mobile sidebar overlay --}}
        <div
            id="sidebar-overlay"
            class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm lg:hidden"
            aria-hidden="true"
        ></div>

        {{-- Shared sidebar --}}
        @include('layouts.sidebar')

        <div class="min-h-screen lg:pl-64">

            {{-- Shared navbar --}}
            @include('layouts.navbar')

            <main class="min-h-[calc(100vh-5rem)]">
                <div class="mx-auto w-full max-w-[1920px] px-4 py-6 sm:px-6 lg:px-8">

                    {{-- Session success alert --}}
                    @if (session('success'))
                        <div
                            class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-emerald-800 shadow-sm"
                            role="alert"
                        >
                            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m5 13 4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">
                                    Operation successful
                                </p>

                                <p class="mt-1 text-sm text-emerald-700">
                                    {{ session('success') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                data-dismiss-alert
                                class="rounded-lg p-1 text-emerald-500 transition hover:bg-emerald-100 hover:text-emerald-700"
                                aria-label="Dismiss alert"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    @endif

                    {{-- Session error alert --}}
                    @if (session('error'))
                        <div
                            class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-red-800 shadow-sm"
                            role="alert"
                        >
                            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-red-100 text-red-600">
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                    />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">
                                    Operation failed
                                </p>

                                <p class="mt-1 text-sm text-red-700">
                                    {{ session('error') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                data-dismiss-alert
                                class="rounded-lg p-1 text-red-500 transition hover:bg-red-100 hover:text-red-700"
                                aria-label="Dismiss alert"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    @endif

                    {{-- Validation errors shared by forms --}}
                    @if ($errors->any())
                        <div
                            class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-800 shadow-sm"
                            role="alert"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-red-100 text-red-600">
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p class="font-semibold">
                                        Please review the following:
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

                    @yield('content')
                </div>
            </main>

            @include('layouts.footer')
        </div>
    </div>



    @stack('scripts')
</body>
</html>