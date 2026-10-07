@php
    $floodKpis = $floodDashboard['kpis'] ?? [];
    $monthlyTrend = $floodDashboard['monthly_trend'] ?? [];
    $riskDistribution = $floodDashboard['risk_distribution'] ?? [];
    $topBarangays = $floodDashboard['top_barangays'] ?? [];
    $recentFloods = collect($floodDashboard['recent_records'] ?? []);

    $temperature = data_get($liveWeather, 'avg_temp_mean_c');
    $humidity = data_get($liveWeather, 'avg_rh_pct');
    $rainfall24h = data_get($liveWeather, 'rainfall_24h_mm');
    $rainfall3d = data_get($liveWeather, 'rainfall_3d_mm');
@endphp


{{-- ========================================================= --}}
{{-- DASHBOARD FILTERS                                         --}}
{{-- ========================================================= --}}

@include('dashboard.partials.filters')


{{-- ========================================================= --}}
{{-- KPI CARDS                                                 --}}
{{-- ========================================================= --}}

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

    {{-- Flood Records --}}
    <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">

        <p class="text-sm font-medium text-sky-700">
            Flood Records
        </p>

        <p class="mt-2 text-3xl font-bold text-sky-800">
            {{ number_format($floodKpis['total_records'] ?? 0) }}
        </p>

        <p class="mt-2 text-xs text-sky-700">
            Records matching current filters
        </p>

    </article>


    {{-- High Risk Records --}}
    <article class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">

        <p class="text-sm font-medium text-slate-500">
            High-Risk Records
        </p>

        <p class="mt-2 text-3xl font-bold text-red-600">
            {{ number_format($floodKpis['high_risk_records'] ?? 0) }}
        </p>

        <p class="mt-2 text-xs text-slate-500">
            Priority analytical observations
        </p>

    </article>


    {{-- Rainfall --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

        <p class="text-sm font-medium text-slate-500">
            Rainfall — 24 Hours
        </p>

        <p class="mt-2 text-3xl font-bold text-slate-950">
            @if ($rainfall24h !== null)
                {{ number_format((float) $rainfall24h, 1) }} mm
            @else
                N/A
            @endif
        </p>

        <p class="mt-2 text-xs text-slate-500">
            3-day total:

            @if ($rainfall3d !== null)
                {{ number_format((float) $rainfall3d, 1) }} mm
            @else
                N/A
            @endif
        </p>

    </article>


    {{-- Current Weather --}}
    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

        <p class="text-sm font-medium text-slate-500">
            Current Conditions
        </p>

        <p class="mt-2 text-3xl font-bold text-slate-950">
            @if ($temperature !== null)
                {{ number_format((float) $temperature, 1) }}°C
            @else
                N/A
            @endif
        </p>

        <p class="mt-2 text-xs text-slate-500">
            Humidity:

            @if ($humidity !== null)
                {{ number_format((float) $humidity, 1) }}%
            @else
                N/A
            @endif
        </p>

    </article>

</section>


{{-- ========================================================= --}}
{{-- LIVE WEATHER ERROR                                        --}}
{{-- ========================================================= --}}

@if (!empty($liveWeatherError))

    <div
        class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
        {{ $liveWeatherError }}

        Historical analytics remain available.
    </div>

@endif


{{-- ========================================================= --}}
{{-- ANALYST SHORTCUTS                                         --}}
{{-- ========================================================= --}}

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >

        <div>

            <h2 class="font-semibold text-slate-950">
                Analyst Shortcuts
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Prediction, validation, mapping, and export tools
            </p>

        </div>


        <div class="flex flex-wrap gap-2">

            {{-- Flood Prediction --}}
            <a
                href="{{ route('prediction.index') }}"
                class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-800"
            >
                Open Flood Prediction
            </a>


            {{-- Flood Operations --}}
            <a
                href="{{ route('flood-operation.index') }}"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Flood Operations
            </a>


            {{-- GIS --}}
            <a
                href="{{ route('public.flood-map', ['filter' => 'flood']) }}"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Flood GIS Map
            </a>

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- FLOOD ANALYTICS CHARTS                                    --}}
{{-- ========================================================= --}}

@include('dashboard.partials.flood-analytics')


{{-- ========================================================= --}}
{{-- RECENT FLOOD RECORDS                                      --}}
{{-- ========================================================= --}}

<section
    class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
>

    {{-- Header --}}
    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="font-semibold text-slate-950">
            Recent Flood Records
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Latest verified observations used for analysis
        </p>

    </div>


    {{-- Table --}}
    <div class="overflow-x-auto">

        <table class="min-w-full divide-y divide-slate-200 text-sm">

            {{-- Table Header --}}
            <thead
                class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"
            >

                <tr>

                    <th class="px-5 py-3">
                        Observed
                    </th>

                    <th class="px-5 py-3">
                        Barangay
                    </th>

                    <th class="px-5 py-3">
                        Risk
                    </th>

                    <th class="px-5 py-3">
                        Flood Code
                    </th>

                    <th class="px-5 py-3">
                        Rainfall 24h
                    </th>

                </tr>

            </thead>


            {{-- Table Body --}}
            <tbody class="divide-y divide-slate-100">

                @forelse ($recentFloods as $record)

                    <tr class="transition hover:bg-slate-50">

                        {{-- Event Date --}}
                        <td
                            class="whitespace-nowrap px-5 py-3 text-slate-600"
                        >

                            @if (!empty($record->event_date))

                                {{ \Carbon\Carbon::parse($record->event_date)->format('M j, Y') }}

                            @else

                                N/A

                            @endif

                        </td>


                        {{-- Barangay --}}
                        <td
                            class="whitespace-nowrap px-5 py-3 font-medium text-slate-900"
                        >
                            {{ $record->barangay ?? 'N/A' }}
                        </td>


                        {{-- Risk Level --}}
                        <td class="whitespace-nowrap px-5 py-3">

                            @php
                                $riskLevel = strtolower(
                                    (string) ($record->risk_level ?? '')
                                );

                                $riskClass = match ($riskLevel) {
                                    'high' =>
                                        'bg-red-100 text-red-700',

                                    'medium' =>
                                        'bg-amber-100 text-amber-700',

                                    'low' =>
                                        'bg-emerald-100 text-emerald-700',

                                    default =>
                                        'bg-slate-100 text-slate-700',
                                };
                            @endphp

                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskClass }}"
                            >
                                {{ $record->risk_level ?? 'N/A' }}
                            </span>

                        </td>


                        {{-- Flood Code --}}
                        <td class="whitespace-nowrap px-5 py-3">

                            @php
                                $floodCode = strtoupper(
                                    (string) ($record->flood_code ?? '')
                                );

                                $floodCodeClass = match ($floodCode) {
                                    'A' =>
                                        'bg-emerald-100 text-emerald-700',

                                    'B' =>
                                        'bg-sky-100 text-sky-700',

                                    'C' =>
                                        'bg-amber-100 text-amber-700',

                                    'D' =>
                                        'bg-red-100 text-red-700',

                                    default =>
                                        'bg-slate-100 text-slate-700',
                                };
                            @endphp

                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $floodCodeClass }}"
                            >
                                {{ $record->flood_code ?? 'N/A' }}
                            </span>

                        </td>


                        {{-- Rainfall --}}
                        <td
                            class="whitespace-nowrap px-5 py-3 text-slate-600"
                        >

                            @if (
                                isset($record->rainfall_24h_mm) &&
                                $record->rainfall_24h_mm !== null
                            )

                                {{ number_format(
                                    (float) $record->rainfall_24h_mm,
                                    1
                                ) }}
                                mm

                            @else

                                N/A

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-slate-500"
                        >
                            No flood records match the selected filters.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>