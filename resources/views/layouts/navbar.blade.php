<header class="sticky top-0 z-30 h-16 border-b border-slate-200 bg-white">
    <div class="flex h-full items-center justify-between px-6">

        {{-- LEFT --}}
        <div class="flex items-center gap-4">

            {{-- Mobile Sidebar Toggle --}}
            <button
                id="sidebar-open-button"
                type="button"
                class="flex h-10 w-10 items-center justify-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100 lg:hidden"
                aria-label="Open Sidebar"
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
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </button>

            <div>

                <h1 class="text-xl font-semibold text-slate-900">
                    @yield('page-title','Dashboard')
                </h1>

                <p class="text-sm text-slate-500">
                    @yield(
                        'page-description',
                        'Mandaluyong Analytics Predictive System'
                    )
                </p>

            </div>

        </div>

        {{-- RIGHT --}}
        <div class="flex items-center gap-5">

            {{-- Date --}}
            <div class="hidden text-right lg:block">

                <div class="text-xs uppercase tracking-wide text-slate-400">
                    Today
                </div>

                <div class="text-sm font-medium text-slate-700">
                    {{ now()->timezone('Asia/Manila')->format('F d, Y') }}
                </div>

            </div>

            {{-- Notification --}}
            <button
                class="relative flex h-10 w-10 items-center justify-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100"
                title="Notifications"
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
                        d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.311 6.022 23.85 23.85 0 0 0 5.454 1.31m5.714 0a24.3 24.3 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                    />
                </svg>

                <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500"></span>

            </button>

            {{-- Profile --}}
            @if(Route::has('profile'))

                <a
                    href="{{ route('profile') }}"
                    class="flex items-center gap-3 border-l border-slate-300 pl-5"
                >

                    <div class="flex h-10 w-10 items-center justify-center rounded-md bg-[#16324F] text-sm font-semibold text-white">
                        {{ strtoupper(substr(auth()->user()?->full_name ?? 'U',0,1)) }}
                    </div>

                    <div class="hidden md:block">

                        <div class="text-sm font-semibold text-slate-800">
                            {{ auth()->user()?->full_name ?? 'Authorized User' }}
                        </div>

                        <div class="text-xs text-slate-500">
                            {{ auth()->user()?->role?->name ?? 'System User' }}
                        </div>

                    </div>

                </a>

            @endif

        </div>

    </div>
</header>