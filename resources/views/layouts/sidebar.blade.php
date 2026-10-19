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
            'label' => 'Incident Analytics',
            'route' => Route::has('incident-analytics.index')
                ? 'incident-analytics.index'
                : null,
            'active' => ['incident-analytics.*'],
            'permission' => 'dashboard.view',
            'icon' => 'analytics',
        ],
        [
            'label' => 'System Analytics',
            'route' => 'system-analytics.index',
            'active' => ['system-analytics.*'],
            'permission' => 'dashboard.view',
            'icon' => 'analytics',
        ],
        [
            'label' => 'Operational Records',
            'route' => Route::has('operational-records.index')
                ? 'operational-records.index'
                : null,
            'active' => ['operational-records.*'],
            'permission' => 'records.view',
            'icon' => 'records',
        ],
        [
            'label' => 'Flood Prediction',
            'route' => Route::has('prediction.index')
                ? 'prediction.index'
                : null,
            'active' => ['prediction.index', 'prediction.run', 'prediction.citywide'],
            'permission' => 'prediction.view',
            'icon' => 'prediction',
        ],
        [
            'label' => 'Prediction History',
            'route' => Route::has('prediction.history.index') ? 'prediction.history.index' : null,
            'active' => ['prediction.history.*'],
            'permission' => 'prediction.view',
            'icon' => 'analytics',
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

    $operationsNavigation[] = ['label' => 'Incident Archives', 'route' => 'incident-archives.index',
        'active' => ['incident-archives.*'], 'permission' => 'records.view', 'icon' => 'records'];

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
            'label' => 'Incident Reports',
            'route' => Route::has('public-submissions.index')
                ? 'public-submissions.index'
                : null,
            'active' => ['public-submissions.*'],
            'permission' => 'public-submissions.view',
            'icon' => 'submissions',
        ],
        [
            'label' => 'Public Advisories',
            'route' => Route::has('advisories.index')
                ? 'advisories.index'
                : null,
            'active' => ['advisories.*'],
            'permission' => 'advisories.view',
            'icon' => 'advisory',
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
            'label' => 'Backup & Recovery',
            'route' => Route::has('admin.backups.index')
                ? 'admin.backups.index'
                : null,
            'active' => ['admin.backups.*'],
            'permission' => null,
            'icon' => 'backup',
        ];

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

    /*
    |--------------------------------------------------------------------------
    | Role-personalized navigation
    |--------------------------------------------------------------------------
    |
    | Permissions continue to protect every route. These lists only remove
    | unrelated menu items so each role receives a focused workspace.
    |
    */
    $roleSlug = $currentRole?->slug;

    $visibleOperations = match ($roleSlug) {
        'administrator' => [
            'Dashboard',
            'System Analytics',
        ],
        'fire-responder' => [
            'Dashboard',
            'Incident Analytics',
            'Fire Incidents',
            'Fire Hydrants',
            'GIS Mapping',
        ],
        'flood-analyst' => [
            'Dashboard',
            'Incident Analytics',
            'Flood Prediction',
            'Prediction History',
            'Flood Operations',
            'GIS Mapping',
        ],
        'operations-manager', 'operations-officer' => [
            'Dashboard',
            'Incident Analytics',
            'Operational Records',
            'Incident Archives',
            'Flood Prediction',
            'Prediction History',
            'Flood Operations',
            'Fire Incidents',
            'Fire Hydrants',
            'GIS Mapping',
            'SMS Center',
        ],
        'system-viewer' => ['Dashboard', 'Incident Analytics', 'Flood Prediction', 'Prediction History', 'Flood Operations', 'Fire Incidents', 'Fire Hydrants', 'GIS Mapping'],
        default => ['Dashboard'],
    };

    $visibleManagement = match ($roleSlug) {
        'administrator' => [
            'User Management',
            'Settings',
            'Backup & Recovery',
            'Activity Logs',
        ],
        'fire-responder' => [
            'Reports',
            'Incident Reports',
            'Public Advisories',
        ],
        'flood-analyst' => [
            'Reports',
            'Incident Reports',
            'Public Advisories',
        ],
        'operations-manager', 'operations-officer' => [
            'Reports',
            'Incident Reports',
            'Public Advisories',
        ],
        default => [],
    };

    $operationsNavigation = collect($operationsNavigation)
        ->whereIn('label', $visibleOperations)
        ->values()
        ->all();

    $managementNavigation = collect($managementNavigation)
        ->whereIn('label', $visibleManagement)
        ->values()
        ->all();

    $operationsHeading = match ($roleSlug) {
        'administrator' => 'System',
        'fire-responder' => 'Fire Response',
        'flood-analyst' => 'Flood Analytics',
        'operations-manager', 'operations-officer' => 'Command Operations',
        default => 'Operations',
    };

    $managementHeading = $roleSlug === 'administrator'
        ? 'Administration'
        : 'Resources';

    // Retain the existing role allowlist AND permissions before grouping the links.
    $allNavigation = collect(array_merge($operationsNavigation, $managementNavigation))
        ->filter(fn ($item) => $item['route']
            && Route::has($item['route'])
            && ($item['permission'] === null || $currentUser?->hasPermission($item['permission'])))
        ->keyBy('label');

    $navigationGroups = [
        ['id' => 'flood', 'label' => 'Flood Management', 'icon' => 'flood',
            'items' => ['Flood Prediction', 'Prediction History', 'Flood Operations']],
        ['id' => 'fire', 'label' => 'Fire Management', 'icon' => 'fire',
            'items' => ['Fire Incidents', 'Fire Hydrants']],
        ['id' => 'records', 'label' => 'Records & Reports', 'icon' => 'records',
            'items' => ['Operational Records', 'Incident Archives', 'Incident Reports', 'Reports']],
        ['id' => 'communications', 'label' => 'Communications', 'icon' => 'message',
            'items' => ['SMS Center', 'Public Advisories']],
        ['id' => 'administration', 'label' => 'Administration', 'icon' => 'settings',
            'items' => ['User Management', 'Settings', 'Backup & Recovery', 'Activity Logs']],
    ];

    $navigationGroups = collect($navigationGroups)
        ->map(function ($group) use ($allNavigation) {
            $group['links'] = collect($group['items'])
                ->filter(fn ($label) => $allNavigation->has($label))
                ->map(fn ($label) => $allNavigation->get($label))
                ->values()->all();
            $group['active'] = collect($group['links'])
                ->contains(fn ($link) => collect($link['active'])
                    ->contains(fn ($pattern) => request()->routeIs($pattern)));
            return $group;
        })
        ->filter(fn ($group) => count($group['links']) > 0)
        ->values();

@endphp

<aside
    id="application-sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-700 bg-[#16324f] text-slate-200 transition-transform duration-200 ease-out lg:translate-x-0"
    aria-label="Primary navigation"
>
    {{-- Brand --}}
    <div class="flex min-h-24 flex-none items-center justify-between gap-2 border-b border-white/10 px-5 py-4">
        <a
            href="{{ Route::has('dashboard') ? route('dashboard') : '#' }}"
            class="flex min-w-0 flex-1 items-center gap-3"
        >
            <img src="{{ asset('images/cdrrmo-logo.jpg') }}" alt="Mandaluyong City CDRRMO logo" width="1080" height="1075" class="h-10 w-10 flex-none rounded-full bg-white object-contain" />

            <div class="min-w-0">
                <p class="truncate text-lg font-semibold tracking-[0.08em] text-white">
                    M.A.P.S
                </p>

                <p class="text-[11px] leading-4 text-slate-300">
                    Flood Prediction & Fire Management System
                </p>
            </div>
        </a>

        <button
            id="sidebar-close-button"
            type="button"
            class="flex h-9 w-9 flex-none items-center justify-center rounded-md text-slate-300 transition hover:bg-white/10 hover:text-white lg:hidden"
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
            {{ $currentUser?->isAdministrator() ? 'System Administration' : 'Disaster Operations' }}
        </p>

        <p class="mt-1 text-[11px] leading-4 text-slate-400">
            Mandaluyong City CDRRMO
        </p>
    </div>

    {{-- Role-aware, collapsible navigation. All existing destinations remain separate. --}}
    <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="Modules">
        <h2 id="operations-navigation-heading"
            class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
            {{ $operationsHeading }}
        </h2>
        <div class="space-y-1">
            @foreach (['Dashboard', 'Incident Analytics', 'System Analytics'] as $topLabel)
                @if ($allNavigation->has($topLabel))
                    @include('layouts.partials.sidebar-link', ['item' => $allNavigation->get($topLabel), 'nested' => false])
                @endif
            @endforeach

            @foreach ($navigationGroups as $group)
                <div class="sidebar-navigation-group" data-sidebar-group="{{ $group['id'] }}"
                    data-active="{{ $group['active'] ? 'true' : 'false' }}">
                    <button type="button" aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                        aria-controls="sidebar-menu-{{ $group['id'] }}"
                        class="sidebar-group-toggle group flex min-h-10 w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium transition hover:bg-white/[0.06] hover:text-white {{ $group['active'] ? 'bg-white/10 text-white' : 'text-slate-300' }}">
                        <span class="flex h-5 w-5 flex-none items-center justify-center {{ $group['active'] ? 'text-sky-300' : 'text-slate-400' }}">
                            @include('layouts.partials.sidebar-icon', ['icon' => $group['icon']])
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ $group['label'] }}</span>
                        <svg class="sidebar-group-chevron h-4 w-4 flex-none transition-transform {{ $group['active'] ? 'rotate-180' : '' }}"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div id="sidebar-menu-{{ $group['id'] }}" class="space-y-0.5 py-1 {{ $group['active'] ? '' : 'hidden' }}">
                        @foreach ($group['links'] as $groupLink)
                            @include('layouts.partials.sidebar-link', ['item' => $groupLink, 'nested' => true])
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($allNavigation->has('GIS Mapping'))
                @include('layouts.partials.sidebar-link', ['item' => $allNavigation->get('GIS Mapping'), 'nested' => false])
            @endif
        </div>
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
