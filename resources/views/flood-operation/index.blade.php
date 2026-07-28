@extends('layouts.app')

@section('title', 'Flood Operations Center | M.A.P.S.')

@section('content')
<div class="space-y-6">

    {{-- ============================================================
         PAGE HEADER
         ============================================================ --}}
    <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">
                Flood Operations
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">
                Citywide Flood Vulnerability Simulation
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Enter a rainfall and weather scenario once, then run the
                machine-learning model across all Mandaluyong barangays to
                identify which locations may be vulnerable.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span
                id="model-status"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm"
            >
                <span
                    id="model-status-dot"
                    class="h-2.5 w-2.5 rounded-full bg-slate-400"
                ></span>

                <span id="model-status-text">
                    Model not tested
                </span>
            </span>

            <a
                href="{{ route('prediction.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Open Flood Prediction
            </a>
        </div>
    </section>

    {{-- ============================================================
         MESSAGE AREA
         ============================================================ --}}
    <div
        id="operation-message"
        class="hidden rounded-2xl border px-5 py-4 text-sm"
        role="alert"
    ></div>

    {{-- ============================================================
         SCENARIO FORM
         ============================================================ --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">
                        Weather Scenario Input
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">
                        These conditions will be applied to all barangays during
                        the citywide simulation.
                    </p>
                </div>

                <button
                    id="load-weather-button"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Use Current Weather
                </button>
            </div>
        </div>

        <form id="simulation-form" class="p-5 sm:p-6">
            @csrf

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">

                {{-- Rainfall 24h --}}
                <div>
                    <label
                        for="rainfall_24h_mm"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Rainfall in Last 24 Hours
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_24h_mm"
                            name="rainfall_24h_mm"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="Example: 50"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            mm
                        </span>
                    </div>
                </div>

                {{-- Rainfall 3d --}}
                <div>
                    <label
                        for="rainfall_3d_mm"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Rainfall in Last 3 Days
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_3d_mm"
                            name="rainfall_3d_mm"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="Example: 120"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            mm
                        </span>
                    </div>
                </div>

                {{-- Rainfall 7d --}}
                <div>
                    <label
                        for="rainfall_7d_mm"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Rainfall in Last 7 Days
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_7d_mm"
                            name="rainfall_7d_mm"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="Example: 250"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            mm
                        </span>
                    </div>
                </div>

                {{-- Temperature --}}
                <div>
                    <label
                        for="temperature_c"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Temperature
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="temperature_c"
                            name="temperature_c"
                            type="number"
                            min="0"
                            max="60"
                            step="0.01"
                            required
                            placeholder="Example: 29"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            °C
                        </span>
                    </div>
                </div>

                {{-- Humidity --}}
                <div>
                    <label
                        for="humidity_pct"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Humidity
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="humidity_pct"
                            name="humidity_pct"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            required
                            placeholder="Example: 85"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            %
                        </span>
                    </div>
                </div>

                {{-- Wind Speed --}}
                <div>
                    <label
                        for="wind_speed_kph"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Wind Speed
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="wind_speed_kph"
                            name="wind_speed_kph"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="Example: 18"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            km/h
                        </span>
                    </div>
                </div>

                {{-- Tide Level --}}
                <div>
                    <label
                        for="tide_level_m"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Tide Level
                    </label>

                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="tide_level_m"
                            name="tide_level_m"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            placeholder="Example: 1.4"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >

                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">
                            m
                        </span>
                    </div>
                </div>

                {{-- Storm Signal --}}
                <div>
                    <label
                        for="storm_signal"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Storm Signal
                    </label>

                    <select
                        id="storm_signal"
                        name="storm_signal"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >
                        <option value="0">No Storm Signal</option>
                        <option value="1">Signal No. 1</option>
                        <option value="2">Signal No. 2</option>
                        <option value="3">Signal No. 3</option>
                        <option value="4">Signal No. 4</option>
                        <option value="5">Signal No. 5</option>
                    </select>
                </div>

                {{-- Month --}}
                <div>
                    <label
                        for="month"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Month
                    </label>

                    <select
                        id="month"
                        name="month"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>

                {{-- Weekend --}}
                <div>
                    <label
                        for="is_weekend"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Weekend
                    </label>

                    <select
                        id="is_weekend"
                        name="is_weekend"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>

                {{-- Wet Season --}}
                <div>
                    <label
                        for="wet_season"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Wet Season
                    </label>

                    <select
                        id="wet_season"
                        name="wet_season"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center">
                <button
                    id="run-simulation-button"
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Run Citywide Simulation
                </button>

                <button
                    id="reset-simulation-button"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Reset Fields
                </button>

                <p
                    id="last-simulation-time"
                    class="text-sm text-slate-500 sm:ml-auto"
                >
                    No simulation performed
                </p>
            </div>
        </form>
    </section>

    {{-- ============================================================
         DATASET MANAGEMENT
         ============================================================ --}}
    @include('flood-operation.dataset-manager')

    {{-- ============================================================
         LOADING STATE
         ============================================================ --}}
    <section
        id="loading-section"
        class="hidden rounded-2xl border border-sky-200 bg-sky-50 p-5"
    >
        <div class="flex items-center gap-4">
            <svg
                class="h-7 w-7 animate-spin text-sky-700"
                viewBox="0 0 24 24"
                fill="none"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>

                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"
                ></path>
            </svg>

            <div>
                <p class="font-semibold text-sky-900">
                    Running vulnerability simulation
                </p>

                <p class="mt-1 text-sm text-sky-700">
                    The machine-learning API is evaluating all available
                    barangays.
                </p>
            </div>
        </div>
    </section>

    {{-- ============================================================
         RESULTS CONTAINER
         ============================================================ --}}
    <div id="results-container" class="hidden space-y-6">

        {{-- Summary cards --}}
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    High Risk
                </p>

                <p
                    id="high-risk-count"
                    class="mt-2 text-3xl font-bold text-red-600"
                >
                    0
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Barangays
                </p>
            </article>

            <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    Medium Risk
                </p>

                <p
                    id="medium-risk-count"
                    class="mt-2 text-3xl font-bold text-amber-600"
                >
                    0
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Barangays
                </p>
            </article>

            <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    Low Risk
                </p>

                <p
                    id="low-risk-count"
                    class="mt-2 text-3xl font-bold text-emerald-600"
                >
                    0
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    Barangays
                </p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    Most Vulnerable
                </p>

                <p
                    id="most-vulnerable-name"
                    class="mt-2 text-lg font-bold text-slate-950"
                >
                    —
                </p>

                <p
                    id="most-vulnerable-details"
                    class="mt-1 text-sm text-slate-500"
                >
                    No result
                </p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">
                    Average Predicted Depth
                </p>

                <p
                    id="average-depth"
                    class="mt-2 text-3xl font-bold text-slate-950"
                >
                    0.0
                </p>

                <p class="mt-1 text-sm text-slate-500">
                    millimeters
                </p>
            </article>
        </section>

        {{-- Operational notice --}}
        <section
            id="operational-notice"
            class="hidden rounded-2xl border px-5 py-4"
        >
            <h2
                id="operational-notice-title"
                class="font-semibold"
            ></h2>

            <p
                id="operational-notice-message"
                class="mt-1 text-sm"
            ></p>
        </section>

        {{-- Results table --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">
                        Barangay Vulnerability Ranking
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">
                        Barangays are ranked from highest to lowest vulnerability.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        id="result-search"
                        type="search"
                        placeholder="Search barangay..."
                        class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >

                    <select
                        id="risk-filter"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                    >
                        <option value="all">All Risk Levels</option>
                        <option value="high">High Risk</option>
                        <option value="medium">Medium Risk</option>
                        <option value="low">Low Risk</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Rank
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Barangay
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Risk
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Probability
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Predicted Depth
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Duration
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Recommended Action
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        id="results-table-body"
                        class="divide-y divide-slate-200 bg-white"
                    ></tbody>
                </table>
            </div>

            <div
                id="empty-results"
                class="hidden px-6 py-12 text-center text-sm text-slate-500"
            >
                No barangays match the selected filter.
            </div>
        </section>

        {{-- GIS preparation --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">
                        GIS Vulnerability Visualization
                    </h2>

                    <p class="mt-1 text-sm text-slate-600">
                        Citywide simulation results can later be connected to
                        the GIS map for barangay risk highlighting.
                    </p>
                </div>

                <a
                    href="{{ route('gis.index') }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Open GIS Mapping
                </a>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                    <span class="h-4 w-4 rounded-full bg-red-600"></span>

                    <span class="text-sm font-medium text-red-900">
                        High vulnerability
                    </span>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <span class="h-4 w-4 rounded-full bg-amber-500"></span>

                    <span class="text-sm font-medium text-amber-900">
                        Medium vulnerability
                    </span>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <span class="h-4 w-4 rounded-full bg-emerald-600"></span>

                    <span class="text-sm font-medium text-emerald-900">
                        Low vulnerability
                    </span>
                </div>
            </div>
        </section>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const form = document.getElementById('simulation-form');
    const runButton = document.getElementById('run-simulation-button');
    const resetButton = document.getElementById('reset-simulation-button');
    const weatherButton = document.getElementById('load-weather-button');

    const loadingSection = document.getElementById('loading-section');
    const resultsContainer = document.getElementById('results-container');
    const resultsTableBody = document.getElementById('results-table-body');
    const emptyResults = document.getElementById('empty-results');

    const searchInput = document.getElementById('result-search');
    const riskFilter = document.getElementById('risk-filter');

    const messageBox = document.getElementById('operation-message');

    let citywideResults = [];

    setAutomaticDateValues();

    form.addEventListener('submit', runSimulation);
    resetButton.addEventListener('click', resetForm);
    weatherButton.addEventListener('click', loadCurrentWeather);
    searchInput.addEventListener('input', renderFilteredResults);
    riskFilter.addEventListener('change', renderFilteredResults);

    function setAutomaticDateValues() {
        const now = new Date();
        const month = now.getMonth() + 1;
        const dayOfWeek = now.getDay();

        document.getElementById('month').value = String(month);

        document.getElementById('is_weekend').value =
            dayOfWeek === 0 || dayOfWeek === 6 ? '1' : '0';

        document.getElementById('wet_season').value =
            month >= 5 && month <= 11 ? '1' : '0';
    }

    async function runSimulation(event) {
        event.preventDefault();
        hideMessage();

        if (!form.reportValidity()) {
            return;
        }

        setLoading(true);

        const payload = {
            rainfall_24h_mm: getNumber('rainfall_24h_mm'),
            rainfall_3d_mm: getNumber('rainfall_3d_mm'),
            rainfall_7d_mm: getNumber('rainfall_7d_mm'),
            temperature_c: getNumber('temperature_c'),
            humidity_pct: getNumber('humidity_pct'),
            wind_speed_kph: getNumber('wind_speed_kph'),
            tide_level_m: getNumber('tide_level_m'),
            storm_signal: getInteger('storm_signal'),
            month: getInteger('month'),
            is_weekend: getInteger('is_weekend'),
            wet_season: getInteger('wet_season')
        };

        try {
            const response = await fetch(
                @json(route('prediction.citywide')),
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                }
            );

            const responseData = await parseJsonResponse(response);

            if (!response.ok) {
                throw new Error(
                    responseData.message ||
                    responseData.error ||
                    'The citywide simulation request failed.'
                );
            }

            citywideResults = normalizeResponse(responseData);

            if (citywideResults.length === 0) {
                console.error('Citywide API response:', responseData);

                throw new Error(
                    'The API returned no barangay predictions. Check the browser console and Laravel log.'
                );
            }

            citywideResults.sort(function (first, second) {
                const riskDifference =
                    riskWeight(second.riskLevel) -
                    riskWeight(first.riskLevel);

                if (riskDifference !== 0) {
                    return riskDifference;
                }

                return second.probability - first.probability;
            });

            citywideResults = citywideResults.map(function (result, index) {
                return {
                    ...result,
                    rank: index + 1
                };
            });

            renderSummary();
            renderFilteredResults();
            renderOperationalNotice();

            resultsContainer.classList.remove('hidden');

            updateModelStatus(
                'Model connected',
                'success'
            );

            document.getElementById('last-simulation-time').textContent =
                'Last simulation: ' + new Date().toLocaleString();

            showMessage(
                'Citywide simulation completed for ' +
                    citywideResults.length +
                    ' barangays.',
                'success'
            );

            resultsContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        } catch (error) {
            console.error(error);

            updateModelStatus(
                'Model connection error',
                'error'
            );

            showMessage(
                error.message ||
                    'Unable to complete the citywide simulation.',
                'error'
            );
        } finally {
            setLoading(false);
        }
    }

    async function loadCurrentWeather() {
        hideMessage();

        weatherButton.disabled = true;
        weatherButton.textContent = 'Loading Weather...';

        try {
            const response = await fetch(
                @json(route('weather.live')),
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            const responseData = await parseJsonResponse(response);

            if (!response.ok) {
                throw new Error(
                    responseData.message ||
                    responseData.error ||
                    'Unable to retrieve current weather.'
                );
            }

            const weather =
                responseData.weather ||
                responseData.data ||
                responseData.current ||
                responseData;

            setField(
                'rainfall_24h_mm',
                firstValue(weather, [
                    'rainfall_24h_mm',
                    'rainfall_24h',
                    'rainfall',
                    'precipitation'
                ])
            );

            setField(
                'rainfall_3d_mm',
                firstValue(weather, [
                    'rainfall_3d_mm',
                    'rainfall_3d',
                    'three_day_rainfall'
                ])
            );

            setField(
                'rainfall_7d_mm',
                firstValue(weather, [
                    'rainfall_7d_mm',
                    'rainfall_7d',
                    'seven_day_rainfall'
                ])
            );

            setField(
                'temperature_c',
                firstValue(weather, [
                    'temperature_c',
                    'temperature',
                    'temperature_2m'
                ])
            );

            setField(
                'humidity_pct',
                firstValue(weather, [
                    'humidity_pct',
                    'humidity',
                    'relative_humidity_2m'
                ])
            );

            setField(
                'wind_speed_kph',
                firstValue(weather, [
                    'wind_speed_kph',
                    'wind_speed',
                    'wind_speed_10m'
                ])
            );

            setField(
                'tide_level_m',
                firstValue(weather, [
                    'tide_level_m',
                    'tide_level'
                ])
            );

            showMessage(
                'Current weather values were loaded. Review them before running the simulation.',
                'success'
            );
        } catch (error) {
            console.error(error);

            showMessage(
                error.message ||
                    'Unable to retrieve current weather.',
                'error'
            );
        } finally {
            weatherButton.disabled = false;
            weatherButton.textContent = 'Use Current Weather';
        }
    }

    function normalizeResponse(responseData) {
        let rawResults = [];

        if (Array.isArray(responseData)) {
            rawResults = responseData;
        } else if (Array.isArray(responseData.results)) {
            rawResults = responseData.results;
        } else if (Array.isArray(responseData.predictions)) {
            rawResults = responseData.predictions;
        } else if (Array.isArray(responseData.data)) {
            rawResults = responseData.data;
        } else if (
            responseData.data &&
            Array.isArray(responseData.data.results)
        ) {
            rawResults = responseData.data.results;
        } else if (
            responseData.data &&
            Array.isArray(responseData.data.predictions)
        ) {
            rawResults = responseData.data.predictions;
        } else if (
            responseData.citywide &&
            Array.isArray(responseData.citywide)
        ) {
            rawResults = responseData.citywide;
        } else if (
            responseData.citywide &&
            Array.isArray(responseData.citywide.results)
        ) {
            rawResults = responseData.citywide.results;
        }

        return rawResults
            .map(normalizeResult)
            .filter(function (result) {
                return result.barangay !== '';
            });
    }

    function normalizeResult(item) {
        const barangay = String(
            firstValue(item, [
                'barangay',
                'barangay_name',
                'name',
                'location'
            ]) || ''
        );

        const riskLevel = normalizeRisk(
            firstValue(item, [
                'risk_level',
                'risk',
                'predicted_risk',
                'prediction',
                'classification',
                'predicted_class'
            ])
        );

        let probability = toNumber(
            firstValue(item, [
                'probability',
                'confidence',
                'risk_probability',
                'high_risk_probability',
                'prediction_probability',
                'confidence_score'
            ])
        );

        if (probability > 0 && probability <= 1) {
            probability *= 100;
        }

        probability = Math.max(
            0,
            Math.min(probability, 100)
        );

        return {
            barangay: barangay,
            riskLevel: riskLevel,
            probability: probability,
            depth: toNumber(
                firstValue(item, [
                    'flood_depth_mm',
                    'predicted_depth_mm',
                    'predicted_flood_depth',
                    'depth_mm',
                    'depth'
                ])
            ),
            duration: toNumber(
                firstValue(item, [
                    'duration_hours',
                    'predicted_duration_hours',
                    'predicted_duration',
                    'duration'
                ])
            ),
            recommendation:
                firstValue(item, [
                    'recommendation',
                    'recommended_action',
                    'action'
                ]) || recommendationForRisk(riskLevel)
        };
    }

    function renderSummary() {
        const highResults = citywideResults.filter(
            result => result.riskLevel === 'High'
        );

        const mediumResults = citywideResults.filter(
            result => result.riskLevel === 'Medium'
        );

        const lowResults = citywideResults.filter(
            result => result.riskLevel === 'Low'
        );

        const averageDepth =
            citywideResults.reduce(
                (sum, result) => sum + result.depth,
                0
            ) / citywideResults.length;

        const mostVulnerable = citywideResults[0];

        document.getElementById('high-risk-count').textContent =
            highResults.length;

        document.getElementById('medium-risk-count').textContent =
            mediumResults.length;

        document.getElementById('low-risk-count').textContent =
            lowResults.length;

        document.getElementById('average-depth').textContent =
            averageDepth.toFixed(1);

        document.getElementById('most-vulnerable-name').textContent =
            mostVulnerable?.barangay || '—';

        document.getElementById('most-vulnerable-details').textContent =
            mostVulnerable
                ? mostVulnerable.riskLevel +
                  ' risk · ' +
                  formatPercentage(mostVulnerable.probability)
                : 'No result';
    }

    function renderFilteredResults() {
        if (citywideResults.length === 0) {
            return;
        }

        const searchTerm =
            searchInput.value.trim().toLowerCase();

        const selectedRisk =
            riskFilter.value.toLowerCase();

        const filteredResults = citywideResults.filter(
            function (result) {
                const matchesSearch =
                    result.barangay
                        .toLowerCase()
                        .includes(searchTerm);

                const matchesRisk =
                    selectedRisk === 'all' ||
                    result.riskLevel.toLowerCase() === selectedRisk;

                return matchesSearch && matchesRisk;
            }
        );

        resultsTableBody.innerHTML = '';

        filteredResults.forEach(function (result) {
            const row = document.createElement('tr');

            row.className = 'hover:bg-slate-50';

            row.innerHTML = `
                <td class="whitespace-nowrap px-4 py-4 text-sm font-semibold text-slate-700">
                    #${escapeHtml(result.rank)}
                </td>

                <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-950">
                    ${escapeHtml(result.barangay)}
                </td>

                <td class="whitespace-nowrap px-4 py-4 text-sm">
                    ${riskBadge(result.riskLevel)}
                </td>

                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">
                    ${escapeHtml(formatPercentage(result.probability))}
                </td>

                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">
                    ${escapeHtml(result.depth.toFixed(1))} mm
                </td>

                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">
                    ${escapeHtml(result.duration.toFixed(1))} hours
                </td>

                <td class="min-w-64 px-4 py-4 text-sm leading-5 text-slate-600">
                    ${escapeHtml(result.recommendation)}
                </td>
            `;

            resultsTableBody.appendChild(row);
        });

        emptyResults.classList.toggle(
            'hidden',
            filteredResults.length > 0
        );
    }

    function renderOperationalNotice() {
        const notice =
            document.getElementById('operational-notice');

        const title =
            document.getElementById('operational-notice-title');

        const message =
            document.getElementById('operational-notice-message');

        const highCount = citywideResults.filter(
            result => result.riskLevel === 'High'
        ).length;

        const mediumCount = citywideResults.filter(
            result => result.riskLevel === 'Medium'
        ).length;

        notice.className = 'rounded-2xl border px-5 py-4';

        if (highCount > 0) {
            notice.classList.add(
                'border-red-200',
                'bg-red-50',
                'text-red-900'
            );

            title.textContent =
                'High-risk barangays detected';

            message.textContent =
                highCount +
                ' barangay or barangays were classified as high risk. Prioritize monitoring, preparation, and internal alerts.';
        } else if (mediumCount > 0) {
            notice.classList.add(
                'border-amber-200',
                'bg-amber-50',
                'text-amber-900'
            );

            title.textContent =
                'Moderate vulnerability detected';

            message.textContent =
                mediumCount +
                ' barangay or barangays were classified as medium risk. Continue monitoring rainfall, waterways, and drainage conditions.';
        } else {
            notice.classList.add(
                'border-emerald-200',
                'bg-emerald-50',
                'text-emerald-900'
            );

            title.textContent =
                'No high-risk barangays detected';

            message.textContent =
                'The current scenario produced low-risk classifications. Continue routine monitoring because conditions may change.';
        }
    }

    function resetForm() {
        form.reset();
        setAutomaticDateValues();

        citywideResults = [];

        resultsContainer.classList.add('hidden');
        loadingSection.classList.add('hidden');
        resultsTableBody.innerHTML = '';

        searchInput.value = '';
        riskFilter.value = 'all';

        document.getElementById('last-simulation-time').textContent =
            'No simulation performed';

        updateModelStatus(
            'Model not tested',
            'neutral'
        );

        hideMessage();
    }

    function setLoading(isLoading) {
        runButton.disabled = isLoading;
        weatherButton.disabled = isLoading;

        loadingSection.classList.toggle(
            'hidden',
            !isLoading
        );

        runButton.textContent = isLoading
            ? 'Running Simulation...'
            : 'Run Citywide Simulation';
    }

    function updateModelStatus(text, type) {
        const container =
            document.getElementById('model-status');

        const dot =
            document.getElementById('model-status-dot');

        document.getElementById('model-status-text').textContent =
            text;

        container.className =
            'inline-flex items-center gap-2 rounded-full border bg-white px-4 py-2 text-sm font-medium shadow-sm';

        if (type === 'success') {
            container.classList.add(
                'border-emerald-200',
                'text-emerald-700'
            );

            dot.className =
                'h-2.5 w-2.5 rounded-full bg-emerald-500';
        } else if (type === 'error') {
            container.classList.add(
                'border-red-200',
                'text-red-700'
            );

            dot.className =
                'h-2.5 w-2.5 rounded-full bg-red-500';
        } else {
            container.classList.add(
                'border-slate-200',
                'text-slate-600'
            );

            dot.className =
                'h-2.5 w-2.5 rounded-full bg-slate-400';
        }
    }

    function showMessage(message, type) {
        messageBox.textContent = message;

        messageBox.className =
            'rounded-2xl border px-5 py-4 text-sm';

        if (type === 'success') {
            messageBox.classList.add(
                'border-emerald-200',
                'bg-emerald-50',
                'text-emerald-800'
            );
        } else {
            messageBox.classList.add(
                'border-red-200',
                'bg-red-50',
                'text-red-800'
            );
        }
    }

    function hideMessage() {
        messageBox.classList.add('hidden');
        messageBox.textContent = '';
    }

    async function parseJsonResponse(response) {
        const responseText = await response.text();

        if (responseText.trim() === '') {
            return {};
        }

        try {
            return JSON.parse(responseText);
        } catch (error) {
            console.error(
                'Non-JSON server response:',
                responseText
            );

            throw new Error(
                'The server returned an invalid response. Check the Laravel log and browser console.'
            );
        }
    }

    function normalizeRisk(value) {
        const normalized = String(value || '')
            .trim()
            .toLowerCase();

        if (
            normalized === 'high' ||
            normalized === '2' ||
            normalized.includes('high')
        ) {
            return 'High';
        }

        if (
            normalized === 'medium' ||
            normalized === 'moderate' ||
            normalized === '1' ||
            normalized.includes('medium') ||
            normalized.includes('moderate')
        ) {
            return 'Medium';
        }

        return 'Low';
    }

    function riskWeight(riskLevel) {
        if (riskLevel === 'High') {
            return 3;
        }

        if (riskLevel === 'Medium') {
            return 2;
        }

        return 1;
    }

    function riskBadge(riskLevel) {
        if (riskLevel === 'High') {
            return `
                <span class="inline-flex rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">
                    High
                </span>
            `;
        }

        if (riskLevel === 'Medium') {
            return `
                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    Medium
                </span>
            `;
        }

        return `
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                Low
            </span>
        `;
    }

    function recommendationForRisk(riskLevel) {
        if (riskLevel === 'High') {
            return 'Prioritize monitoring, prepare response teams, and consider sending internal alerts.';
        }

        if (riskLevel === 'Medium') {
            return 'Monitor rainfall, drainage, waterways, and barangay conditions closely.';
        }

        return 'Maintain routine monitoring and continue checking weather changes.';
    }

    function firstValue(object, keys) {
        if (!object || typeof object !== 'object') {
            return null;
        }

        for (const key of keys) {
            if (
                Object.prototype.hasOwnProperty.call(object, key) &&
                object[key] !== null &&
                object[key] !== ''
            ) {
                return object[key];
            }
        }

        return null;
    }

    function setField(fieldId, value) {
        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return;
        }

        document.getElementById(fieldId).value = value;
    }

    function getNumber(fieldId) {
        return toNumber(
            document.getElementById(fieldId).value
        );
    }

    function getInteger(fieldId) {
        const value = parseInt(
            document.getElementById(fieldId).value,
            10
        );

        return Number.isFinite(value) ? value : 0;
    }

    function toNumber(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    }

    function formatPercentage(value) {
        return toNumber(value).toFixed(1) + '%';
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
</script>
@endpush