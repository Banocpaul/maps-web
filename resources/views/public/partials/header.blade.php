<header class="border-b border-slate-200 bg-slate-950 text-white shadow-lg">

        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">

            <a href="{{ route('public.portal') }}" class="flex items-center gap-3">

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

                        Mandaluyong Flood & Fire

                    </p>

                    <p class="text-xs text-slate-400">

                        Mandaluyong Flood Prediction and Fire Response System

                    </p>

                </div>

            </a>

            <nav class="flex flex-wrap items-center gap-3" aria-label="Public portal navigation">
            @if($reportPage ?? false)
                <a href="{{ route('public.portal') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">Back to Public Portal</a>
            @else
                <a href="{{ route('public.incident-reports.create') }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500">Report an Incident</a>
            @endif

            <a

                href="{{ route('login') }}"

                class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white hover:text-slate-950"

            >

                Staff Login

            </a>
            </nav>

        </div>

    </header>
