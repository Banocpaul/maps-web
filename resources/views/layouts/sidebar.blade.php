@php
    $currentUser = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | Resolve the assigned role directly through the relationship
    |--------------------------------------------------------------------------
    */
    $currentRole = $currentUser?->role()->first();

    /*
    |--------------------------------------------------------------------------
    | Resolve routes with existing fallbacks
    |--------------------------------------------------------------------------
    */

    $floodOperationRouteName = null;

    if (Route::has('flood-operation.index')) {
        $floodOperationRouteName = 'flood-operation.index';
    } elseif (Route::has('flood.index')) {
        $floodOperationRouteName = 'flood.index';
    }

    $smsRouteName = null;

    if (Route::has('sms.index')) {
        $smsRouteName = 'sms.index';
    } elseif (Route::has('sms-center.index')) {
        $smsRouteName = 'sms-center.index';
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation configuration
    |--------------------------------------------------------------------------
    */

    $operationsNavigation = [
        [
            'label' => 'Dashboard',
            'route' => Route::has('dashboard') ? 'dashboard' : null,
            'active' => ['dashboard'],
            'permission' => null,
            'icon' => 'dashboard',
        ],
        [
            'label' => 'Flood Prediction',
            'route' => Route::has('prediction.index')
                ? 'prediction.index'
                : null,
            'active' => ['prediction.*'],
            'permission' => 'prediction.view',
            'icon' => 'prediction',
        ],
        [
            'label' => 'Flood Operations',
            'route' => $floodOperationRouteName,
            'active' => [
                'flood-operation.*',
                'flood.*',
            ],
            'permission' => 'prediction.view',
            'icon' => 'flood',
        ],
        [
            'label' => 'Fire Incidents',
            'route' => Route::has('fire-incidents.index')
                ? 'fire-incidents.index'
                : null,
            'active' => ['fire-incidents.*'],
            'permission' => 'fire.view',
            'icon' => 'fire',
        ],
        [
            'label' => 'Fire Hydrants',
            'route' => Route::has('fire-hydrants.index')
                ? 'fire-hydrants.index'
                : null,
            'active' => ['fire-hydrants.*'],
            'permission' => 'fire.view',
            'icon' => 'hydrant',
        ],
        [
            'label' => 'GIS Mapping',
            'route' => Route::has('gis.index')
                ? 'gis.index'
                : null,
            'active' => ['gis.*'],
            'permission' => 'gis.view',
            'icon' => 'map',
        ],
        [
            'label' => 'SMS Center',
            'route' => $smsRouteName,
            'active' => Route::has('sms.index')
                ? ['sms.*']
                : ['sms-center.*'],
            'permission' => 'sms.view',
            'icon' => 'message',
        ],
    ];

    $managementNavigation = [
        [
            'label' => 'Reports',
            'route' => Route::has('reports.index')
                ? 'reports.index'
                : null,
            'active' => ['reports.*'],
            'permission' => 'reports.view',
            'icon' => 'reports',
        ],
        [
            'label' => 'Public Submissions',
            'route' => Route::has('public-submissions.index')
                ? 'public-submissions.index'
                : null,
            'active' => ['public-submissions.*'],
            'permission' => 'public-submissions.view',
            'icon' => 'submissions',
        ],
        [
            'label' => 'User Management',
            'route' => Route::has('users.index')
                ? 'users.index'
                : null,
            'active' => ['users.*'],
            'permission' => 'users.manage',
            'icon' => 'users',
        ],
        [
            'label' => 'Settings',
            'route' => Route::has('settings.index')
                ? 'settings.index'
                : null,
            'active' => ['settings.*'],
            'permission' => 'settings.manage',
            'icon' => 'settings',
        ],
    ];

    if ($currentUser?->isAdministrator()) {
        $managementNavigation[] = [
            'label' => 'Activity Logs',
            'route' => Route::has('activity-logs.index')
                ? 'activity-logs.index'
                : null,
            'active' => ['activity-logs.*'],
            'permission' => 'activity-logs.view',
            'icon' => 'activity',
        ];
    }
@endphp

<aside
    id="application-sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-700 bg-[#16324f] text-slate-200 transition-transform duration-200 ease-out lg:translate-x-0"
    aria-label="Primary navigation"
>
    {{-- Brand --}}
    <div class="flex h-20 flex-none items-center justify-between border-b border-white/10 px-5">
        <a
            href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}"
            class="flex min-w-0 items-center gap-3"
        >
            <div class="flex h-10 w-10 flex-none items-center justify-center rounded-md bg-white text-[#16324f]">
                <svg
                    class="h-6 w-6"
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

                    <circle cx="12" cy="12" r="2.25" />
                </svg>
            </div>

            <div class="min-w-0">
                <p class="truncate text-lg font-semibold tracking-[0.08em] text-white">
                    M.A.P.S.
                </p>

                <p class="truncate text-[11px] text-slate-300">
                    Operations Portal
                </p>
            </div>
        </a>

        <button
            id="sidebar-close-button"
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-md text-slate-300 transition hover:bg-white/10 hover:text-white lg:hidden"
            aria-label="Close navigation"
        >
            <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12"
                />
            </svg>
        </button>
    </div>

    {{-- Office identification --}}
    <div class="flex-none border-b border-white/10 px-5 py-4">
        <p class="text-xs font-medium text-white">
            Disaster Operations
        </p>

        <p class="mt-1 text-[11px] leading-4 text-slate-400">
            Mandaluyong City CDRRMO
        </p>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 overflow-y-auto px-3 py-5">
        <section aria-labelledby="operations-navigation-heading">
            <h2
                id="operations-navigation-heading"
                class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400"
            >
                Operations
            </h2>

            <div class="space-y-0.5">
                @foreach ($operationsNavigation as $item)
                    @php
                        $canViewItem = $item['permission'] === null
                            || $currentUser?->hasPermission($item['permission']);

                        $isActive = collect($item['active'])
                            ->contains(
                                fn ($pattern) =>
                                    request()->routeIs($pattern)
                            );
                    @endphp

                    @if ($canViewItem && $item['route'])
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-sm transition',
                                'bg-white/10 font-medium text-white' => $isActive,
                                'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $isActive,
                            ])
                            @if ($isActive)
                                aria-current="page"
                            @endif
                        >
                            @if ($isActive)
                                <span
                                    class="absolute bottom-2 left-0 top-2 w-0.5 rounded-r bg-sky-400"
                                    aria-hidden="true"
                                ></span>
                            @endif

                            <span
                                @class([
                                    'flex h-5 w-5 flex-none items-center justify-center',
                                    'text-sky-300' => $isActive,
                                    'text-slate-400 group-hover:text-slate-200' => ! $isActive,
                                ])
                            >
                                @switch($item['icon'])
                                    @case('dashboard')
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
                                                d="M4 4h6v6H4V4Zm10 0h6v6h-6V4Zm0 10h6v6h-6v-6ZM4 14h6v6H4v-6Z"
                                            />
                                        </svg>
                                        @break

                                    @case('prediction')
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
                                                d="M4 18 9 13l3 3 8-9"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15 7h5v5"
                                            />
                                        </svg>
                                        @break

                                    @case('flood')
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
                                                d="M12 3.5c-2.4 3.2-5.5 6.8-5.5 10A5.5 5.5 0 0 0 12 19a5.5 5.5 0 0 0 5.5-5.5c0-3.2-3.1-6.8-5.5-10Z"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9.5 14.5c.5 1 1.3 1.5 2.5 1.5"
                                            />
                                        </svg>
                                        @break

                                    @case('fire')
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
                                                d="M12 3.5c1.5 3 4.5 4.7 4.5 8.5A4.5 4.5 0 0 1 12 16.5 4.5 4.5 0 0 1 7.5 12c0-2.4 1.2-4.2 2.7-5.8.2 1.8.9 3.2 1.8 4 .4-2.3.1-4.3 0-6.7Z"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9.8 16c0 2.1 1.1 3.5 2.2 4.2 1.1-.7 2.2-2.1 2.2-4.2"
                                            />
                                        </svg>
                                        @break

                                    @case('hydrant')
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
                                                d="M8 10h8m-6-3h4m-7 6h10v7H7v-7Zm3-6V4h4v3M7 15H4v3h3m10-3h3v3h-3"
                                            />
                                        </svg>
                                        @break

                                    @case('map')
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
                                                d="m9 19-5-2V5l5 2m0 12 6-2m-6 2V7m6 10 5 2V7l-5-2m0 12V5m0 0L9 7"
                                            />
                                        </svg>
                                        @break

                                    @case('message')
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
                                                d="M7 8h10M7 12h7m7 0a9 9 0 1 1-3.3-7A9 9 0 0 1 21 12Z"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m5.5 18.5-1.5 2.5 4-.8"
                                            />
                                        </svg>
                                        @break
                                @endswitch
                            </span>

                            <span class="min-w-0 truncate">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>

        <section
            class="mt-7"
            aria-labelledby="management-navigation-heading"
        >
            <h2
                id="management-navigation-heading"
                class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400"
            >
                Management
            </h2>

            <div class="space-y-0.5">
                @foreach ($managementNavigation as $item)
                    @php
                        $canViewItem = $item['permission'] === null
                            || $currentUser?->hasPermission($item['permission']);

                        $isActive = collect($item['active'])
                            ->contains(
                                fn ($pattern) =>
                                    request()->routeIs($pattern)
                            );
                    @endphp

                    @if ($canViewItem && $item['route'])
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'group relative flex min-h-10 items-center gap-3 rounded-md px-3 py-2 text-sm transition',
                                'bg-white/10 font-medium text-white' => $isActive,
                                'text-slate-300 hover:bg-white/[0.06] hover:text-white' => ! $isActive,
                            ])
                            @if ($isActive)
                                aria-current="page"
                            @endif
                        >
                            @if ($isActive)
                                <span
                                    class="absolute bottom-2 left-0 top-2 w-0.5 rounded-r bg-sky-400"
                                    aria-hidden="true"
                                ></span>
                            @endif

                            <span
                                @class([
                                    'flex h-5 w-5 flex-none items-center justify-center',
                                    'text-sky-300' => $isActive,
                                    'text-slate-400 group-hover:text-slate-200' => ! $isActive,
                                ])
                            >
                                @switch($item['icon'])
                                    @case('reports')
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
                                                d="M5 20V11m5 9V5m5 15v-7m4 7V8"
                                            />
                                        </svg>
                                        @break

                                    @case('submissions')
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
                                                d="M8 7h9M8 11h9M8 15h5M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                                            />
                                        </svg>
                                        @break

                                    @case('users')
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
                                                d="M15.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0ZM4.5 20a7.5 7.5 0 0 1 15 0"
                                            />
                                        </svg>
                                        @break

                                    @case('settings')
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="3"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.1 2.1-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-3v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L6.6 17l.1-.1A1.7 1.7 0 0 0 7 15a1.7 1.7 0 0 0-1.6-1H5v-3h.4A1.7 1.7 0 0 0 7 10a1.7 1.7 0 0 0-.3-1.9L6.6 8l2.1-2.1.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2h3v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 8l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.4v3H21a1.7 1.7 0 0 0-1.6 1Z"
                                            />
                                        </svg>
                                        @break

                                    @case('activity')
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
                                                d="M9 5h6m-7 4h8m-8 4h5m-5 4h8M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                                            />
                                        </svg>
                                        @break
                                @endswitch
                            </span>

                            <span class="min-w-0 truncate">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    </nav>

    {{-- User and logout --}}
    <div class="flex-none border-t border-white/10 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 flex-none items-center justify-center rounded-md border border-white/15 bg-white/10 text-sm font-semibold text-white">
                {{ strtoupper(substr($currentUser?->full_name ?? 'U', 0, 1)) }}
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-white">
                    {{ $currentUser?->full_name ?? 'Authorized User' }}
                </p>

                <p class="truncate text-[11px] text-slate-400">
                    {{ $currentRole?->name ?? 'System User' }}
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('logout') }}"
            >
                @csrf

                <button
                    type="submit"
                    class="flex h-9 w-9 items-center justify-center rounded-md text-slate-400 transition hover:bg-red-500/15 hover:text-red-300"
                    title="Sign out"
                    aria-label="Sign out"
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
                            d="M15.5 9V5.5A2.5 2.5 0 0 0 13 3H7a2.5 2.5 0 0 0-2.5 2.5v13A2.5 2.5 0 0 0 7 21h6a2.5 2.5 0 0 0 2.5-2.5V15"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m18 15 3-3m0 0-3-3m3 3H9"
                        />
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>
