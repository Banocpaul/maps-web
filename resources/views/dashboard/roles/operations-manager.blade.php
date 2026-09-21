@php
    $fireKpis = $fireDashboard['kpis'] ?? [];
    $floodKpis = $floodDashboard['kpis'] ?? [];

    $activeIncidents = collect(
        $fireOperations['recent_active'] ?? []
    );

    $recentFloods = collect(
        $floodDashboard['recent_records'] ?? []
    );

    $rainfall24h = data_get(
        $liveWeather,
        'avg_rainfall_24h_mm'
    );
@endphp

@include('dashboard.partials.filters')

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
        <p class="text-sm font-medium text-red-700">
            Active Fire Incidents
        </p>

        <p class="mt-2 text-3xl font-bold text-red-700">
            {{ number_format($fireOperations['active'] ?? 0) }}
        </p>

        <p class="mt-2 text-xs text-red-600">
            Citywide open operations
        </p>
    </article>

    <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
        <p class="text-sm font-medium text-sky-700">
            High-Risk Flood Records
        </p>

        <p class="mt-2 text-3xl font-bold text-sky-800">
            {{ number_format($floodKpis['high_risk_records'] ?? 0) }}
        </p>

        <p class="mt-2 text-xs text-sky-700">
            Matches current filters
        </p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">
            Rainfall — 24 Hours
        </p>

        <p class="mt-2 text-3xl font-bold text-slate-950">
            {{ $rainfall24h !== null
                ? number_format((float) $rainfall24h, 1) . ' mm'
                : 'N/A' }}
        </p>

        <p class="mt-2 text-xs text-slate-500">
            Current citywide weather
        </p>
    </article>

    <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">
            SMS Sent Today
        </p>

        <p class="mt-2 text-3xl font-bold text-emerald-700">
            {{ number_format(
                $operationsSummary['sms_sent_today'] ?? 0
            ) }}
        </p>

        <p class="mt-2 text-xs text-slate-500">
            Successful internal alerts
        </p>
    </article>

    <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">
            SMS Failed Today
        </p>

        <p class="mt-2 text-3xl font-bold text-amber-600">
            {{ number_format(
                $operationsSummary['sms_failed_today'] ?? 0
            ) }}
        </p>

        <p class="mt-2 text-xs text-slate-500">
            Deliveries requiring review
        </p>
    </article>
</section>

<section
    id="operations-analytics"
    class="mt-6 overflow-hidden rounded-2xl border border-blue-200 bg-blue-50/60 shadow-sm"
>
    <div class="border-b border-blue-200 bg-gradient-to-r from-blue-50 via-sky-50 to-cyan-50 px-5 py-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">
                    Operational overview
                </p>

                <h2 class="mt-1 text-lg font-semibold text-blue-950">
                    Disaster Analytics
                </h2>

                <p class="mt-1 text-sm text-blue-700">
                    Switch between fire and flood operational insights
                </p>
            </div>

            <div
                class="inline-flex w-full rounded-xl border border-blue-200 bg-white/90 p-1 shadow-sm sm:w-auto"
                role="tablist"
                aria-label="Disaster analytics"
            >
                <button
                    type="button"
                    id="fire-analytics-tab"
                    class="analytics-tab flex-1 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 sm:flex-none"
                    data-analytics-target="fire"
                    role="tab"
                    aria-selected="true"
                    aria-controls="fire-analytics-panel"
                >
                    Fire Analytics
                </button>

                <button
                    type="button"
                    id="flood-analytics-tab"
                    class="analytics-tab flex-1 rounded-lg px-5 py-2.5 text-sm font-semibold text-blue-700 transition duration-200 hover:bg-blue-100 sm:flex-none"
                    data-analytics-target="flood"
                    role="tab"
                    aria-selected="false"
                    aria-controls="flood-analytics-panel"
                    tabindex="-1"
                >
                    Flood Analytics
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white/75 p-4 sm:p-5">
        <div
            id="fire-analytics-panel"
            data-analytics-panel="fire"
            role="tabpanel"
            aria-labelledby="fire-analytics-tab"
        >
            @include('dashboard.partials.fire-analytics')
        </div>

        <div
            id="flood-analytics-panel"
            data-analytics-panel="flood"
            class="hidden"
            role="tabpanel"
            aria-labelledby="flood-analytics-tab"
        >
            @include('dashboard.partials.flood-analytics')
        </div>
    </div>
</section>

@if ($liveWeatherError)
    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ $liveWeatherError }}
        Operational records remain available.
    </div>
@endif

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="font-semibold text-slate-950">
                Command Shortcuts
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Open the operational module required for coordination
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('operational-records.index') }}"
                class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Database Records
            </a>

            <a
                href="{{ route('gis.index') }}"
                class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800"
            >
                Open Citywide Map
            </a>

            <a
                href="{{ route('fire-incidents.index') }}"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Fire Operations
            </a>

            <a
                href="{{ route('flood-operation.index') }}"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Flood Operations
            </a>

            @if (Route::has('sms.index'))
                <a
                    href="{{ route('sms.index') }}"
                    class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    SMS Center
                </a>
            @endif
        </div>
    </div>
</section>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const analyticsContainer = document.getElementById(
                'operations-analytics'
            );

            if (!analyticsContainer) {
                return;
            }

            const tabs = Array.from(
                analyticsContainer.querySelectorAll('.analytics-tab')
            );

            const panels = Array.from(
                analyticsContainer.querySelectorAll(
                    '[data-analytics-panel]'
                )
            );

            const setActiveAnalytics = (target) => {
                tabs.forEach((tab) => {
                    const isActive =
                        tab.dataset.analyticsTarget === target;

                    tab.setAttribute(
                        'aria-selected',
                        isActive ? 'true' : 'false'
                    );

                    tab.tabIndex = isActive ? 0 : -1;
                    tab.classList.toggle('bg-blue-600', isActive);
                    tab.classList.toggle('text-white', isActive);
                    tab.classList.toggle('shadow-sm', isActive);
                    tab.classList.toggle('text-blue-700', !isActive);
                    tab.classList.toggle('hover:bg-blue-100', !isActive);
                });

                panels.forEach((panel) => {
                    const isActive =
                        panel.dataset.analyticsPanel === target;

                    panel.classList.toggle('hidden', !isActive);
                    panel.setAttribute(
                        'aria-hidden',
                        isActive ? 'false' : 'true'
                    );
                });

                window.requestAnimationFrame(() => {
                    window.dispatchEvent(new Event('resize'));
                });
            };

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => {
                    setActiveAnalytics(tab.dataset.analyticsTarget);
                });

                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();

                    const direction =
                        event.key === 'ArrowRight' ? 1 : -1;

                    const nextIndex =
                        (index + direction + tabs.length) % tabs.length;

                    tabs[nextIndex].focus();
                    setActiveAnalytics(
                        tabs[nextIndex].dataset.analyticsTarget
                    );
                });
            });

            setActiveAnalytics('fire');
        });
    </script>
@endpush

<section class="mt-6 grid gap-6 xl:grid-cols-2">
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-950">
                    Active Fire Operations
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Incidents requiring continued coordination
                </p>
            </div>

            <a
                href="{{ route('fire-incidents.index') }}"
                class="text-sm font-semibold text-sky-700"
            >
                View all
            </a>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($activeIncidents->take(6) as $incident)
                <a
                    href="{{ route('fire-incidents.show', $incident) }}"
                    class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50"
                >
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">
                            {{ $incident->incident_number }}
                            ·
                            {{ $incident->barangay?->name ?? 'Unknown' }}
                        </p>

                        <p class="mt-1 truncate text-sm text-slate-500">
                            {{ $incident->location }}
                        </p>
                    </div>

                    <span class="flex-none rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                        {{ $incident->status }}
                    </span>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">
                    No active fire incidents.
                </div>
            @endforelse
        </div>
    </article>

    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold text-slate-950">
                    Latest Flood Intelligence
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Recent verified barangay observations
                </p>
            </div>

            <a
                href="{{ route('flood-operation.index') }}"
                class="text-sm font-semibold text-sky-700"
            >
                View center
            </a>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($recentFloods->take(6) as $record)
                @php
                    $recordBarangay = data_get(
                        $record,
                        'barangay',
                        'Unknown barangay'
                    );

                    $recordObservedAt =
                        data_get($record, 'observed_at')
                        ?? data_get($record, 'recorded_at')
                        ?? data_get($record, 'date');

                    $recordDepth = (float) data_get(
                        $record,
                        'flood_depth_mm',
                        0
                    );

                    $recordRisk = data_get(
                        $record,
                        'risk_level',
                        'Unknown'
                    );
                @endphp

                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">
                            {{ $recordBarangay }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            @if ($recordObservedAt)
                                {{ \Carbon\Carbon::parse($recordObservedAt)
                                    ->timezone('Asia/Manila')
                                    ->format('M j, Y g:i A') }}
                            @else
                                Observation date unavailable
                            @endif

                            · {{ number_format($recordDepth, 1) }} mm
                        </p>
                    </div>

                    <span
                        @class([
                            'flex-none rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-red-100 text-red-800' =>
                                $recordRisk === 'High',
                            'bg-amber-100 text-amber-800' =>
                                $recordRisk === 'Medium',
                            'bg-emerald-100 text-emerald-800' =>
                                $recordRisk === 'Low',
                            'bg-slate-100 text-slate-700' =>
                                !in_array(
                                    $recordRisk,
                                    ['High', 'Medium', 'Low'],
                                    true
                                ),
                        ])
                    >
                        {{ $recordRisk }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500">
                    No flood records match the selected filters.
                </div>
            @endforelse
        </div>
    </article>
</section>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">
            Historical Fire Incidents
        </p>

        <p class="mt-2 text-2xl font-bold text-slate-950">
            {{ number_format($fireKpis['total_incidents'] ?? 0) }}
        </p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">
            Fire Resolution Rate
        </p>

        <p class="mt-2 text-2xl font-bold text-slate-950">
            {{ $fireKpis['resolution_rate'] ?? 0 }}%
        </p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">
            Historical Flood Records
        </p>

        <p class="mt-2 text-2xl font-bold text-slate-950">
            {{ number_format($floodKpis['total_records'] ?? 0) }}
        </p>
    </article>

    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">
            Controlled Fire Incidents
        </p>

        <p class="mt-2 text-2xl font-bold text-slate-950">
            {{ number_format($fireOperations['controlled'] ?? 0) }}
        </p>
    </article>
</section>
