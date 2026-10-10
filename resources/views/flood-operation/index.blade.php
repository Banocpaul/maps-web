@extends('layouts.app')

@section('title', 'Flood Operations Center | Mandaluyong Flood & Fire')

@section('content')
<div class="space-y-6">
    <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">
                Flood Operations
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">
                Citywide Flood Severity Simulation
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Enter a hypothetical rainfall accumulation scenario. The system
                automatically combines it with today's date, current weather,
                and each barangay's geographic profile before running the A-D
                flood severity model.
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
                <span id="model-status-text">Model not tested</span>
            </span>

            <a
                href="{{ route('prediction.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Open Flood Prediction
            </a>
        </div>
    </section>

    <div
        id="operation-message"
        class="hidden rounded-2xl border px-5 py-4 text-sm"
        role="alert"
    ></div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">
                        Rainfall Scenario Input
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Enter cumulative rainfall only. The 24-hour value must
                        not exceed the 3-day value, and the 3-day value must not
                        exceed the 7-day value.
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-600">
                    Simulation date:
                    <span class="font-semibold text-slate-900">
                        {{ now(config('app.timezone'))->format('F d, Y') }}
                    </span>
                </div>
            </div>
        </div>

        <form id="simulation-form" class="p-5 sm:p-6">
            @csrf

            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="rainfall_24h_mm" class="block text-sm font-medium text-slate-700">
                        Rainfall in Last 24 Hours
                    </label>
                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_24h_mm"
                            name="rainfall_24h_mm"
                            type="number"
                            min="0"
                            max="2000"
                            step="0.01"
                            required
                            placeholder="Example: 30"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >
                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">mm</span>
                    </div>
                </div>

                <div>
                    <label for="rainfall_3d_mm" class="block text-sm font-medium text-slate-700">
                        Rainfall in Last 3 Days
                    </label>
                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_3d_mm"
                            name="rainfall_3d_mm"
                            type="number"
                            min="0"
                            max="4000"
                            step="0.01"
                            required
                            placeholder="Example: 80"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >
                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">mm</span>
                    </div>
                </div>

                <div>
                    <label for="rainfall_7d_mm" class="block text-sm font-medium text-slate-700">
                        Rainfall in Last 7 Days
                    </label>
                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                        <input
                            id="rainfall_7d_mm"
                            name="rainfall_7d_mm"
                            type="number"
                            min="0"
                            max="8000"
                            step="0.01"
                            required
                            placeholder="Example: 140"
                            class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm outline-none focus:ring-0"
                        >
                        <span class="flex items-center border-l border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">mm</span>
                    </div>
                </div>
            </div>

            <div class="mt-5 rounded-xl border border-sky-100 bg-sky-50 p-4">
                <p class="text-sm font-semibold text-sky-900">
                    Automatically supplied by the system
                </p>
                <p class="mt-1 text-sm leading-6 text-sky-800">
                    Today's date and time, current temperature, daily maximum and
                    minimum temperature, wind speed, wind direction, storm-signal
                    default, barangay, nearest waterway, elevation, and distance
                    to waterway. Rainfall-derived features are calculated from
                    the three values above.
                </p>
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

                <p id="last-simulation-time" class="text-sm text-slate-500 sm:ml-auto">
                    No simulation performed
                </p>
            </div>
        </form>
    </section>

    <section id="loading-section" class="hidden rounded-2xl border border-sky-200 bg-sky-50 p-5">
        <div class="flex items-center gap-4">
            <svg class="h-7 w-7 animate-spin text-sky-700" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
            </svg>
            <div>
                <p class="font-semibold text-sky-900">Running rainfall severity simulation</p>
                <p class="mt-1 text-sm text-sky-700">
                    The A-D Random Forest is evaluating all active barangays.
                </p>
            </div>
        </div>
    </section>

    <div id="results-container" class="hidden space-y-6">
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Code A · 0.5 ft</p>
                <p id="severity-a-count" class="mt-2 text-3xl font-bold text-emerald-700">0</p>
            </article>
            <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Code B · 1.5 ft</p>
                <p id="severity-b-count" class="mt-2 text-3xl font-bold text-amber-700">0</p>
            </article>
            <article class="rounded-2xl border border-orange-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Code C · 2.0 ft</p>
                <p id="severity-c-count" class="mt-2 text-3xl font-bold text-orange-700">0</p>
            </article>
            <article class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Code D · 2.5 ft+</p>
                <p id="severity-d-count" class="mt-2 text-3xl font-bold text-red-700">0</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Simulation Results</h2>
                        <p id="scenario-summary" class="mt-1 text-sm text-slate-600"></p>
                        <a id="simulation-history-link" class="mt-2 hidden text-sm font-semibold text-sky-700 hover:text-sky-900">Review Saved Run</a>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <input
                            id="barangay-search"
                            type="search"
                            placeholder="Search barangay"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                        >
                        <select
                            id="severity-filter"
                            class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                        >
                            <option value="all">All severity codes</option>
                            <option value="A">Code A</option>
                            <option value="B">Code B</option>
                            <option value="C">Code C</option>
                            <option value="D">Code D</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Rank</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Barangay</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flood Severity</th>
                        </tr>
                    </thead>
                    <tbody id="results-table-body" class="divide-y divide-slate-100 bg-white"></tbody>
                </table>
            </div>

            <div id="empty-results" class="hidden px-5 py-10 text-center text-sm text-slate-500">
                No barangays match the selected filter.
            </div>
        </section>
    </div>

    @include('flood-operation.dataset-manager')
</div>

<script>
(() => {
    const form = document.getElementById('simulation-form');
    if (!form || form.dataset.initialized === '1') {
        return;
    }
    form.dataset.initialized = '1';

    const runButton = document.getElementById('run-simulation-button');
    const resetButton = document.getElementById('reset-simulation-button');
    const loadingSection = document.getElementById('loading-section');
    const resultsContainer = document.getElementById('results-container');
    const resultsTableBody = document.getElementById('results-table-body');
    const emptyResults = document.getElementById('empty-results');
    const searchInput = document.getElementById('barangay-search');
    const severityFilter = document.getElementById('severity-filter');
    const messageBox = document.getElementById('operation-message');

    let citywideResults = [];
    let lastScenario = null;

    form.addEventListener('submit', runSimulation);
    resetButton.addEventListener('click', resetForm);
    searchInput.addEventListener('input', renderFilteredResults);
    severityFilter.addEventListener('change', renderFilteredResults);

    async function runSimulation(event) {
        event.preventDefault();
        hideMessage();

        if (!form.reportValidity()) {
            return;
        }

        const rainfall24h = getNumber('rainfall_24h_mm');
        const rainfall3d = getNumber('rainfall_3d_mm');
        const rainfall7d = getNumber('rainfall_7d_mm');

        if (rainfall3d < rainfall24h) {
            showMessage(
                '3-day rainfall must be greater than or equal to 24-hour rainfall.',
                'error'
            );
            return;
        }

        if (rainfall7d < rainfall3d) {
            showMessage(
                '7-day rainfall must be greater than or equal to 3-day rainfall.',
                'error'
            );
            return;
        }

        lastScenario = {
            rainfall24h,
            rainfall3d,
            rainfall7d
        };

        setLoading(true);

        try {
            const response = await fetch(
                @json(route('flood-operation.simulate')),
                {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        rainfall_24h_mm: rainfall24h,
                        rainfall_3d_mm: rainfall3d,
                        rainfall_7d_mm: rainfall7d
                    })
                }
            );

            const responseData = await parseJsonResponse(response);

            if (!response.ok) {
                throw new Error(
                    responseData.message ||
                    responseData.detail ||
                    'The rainfall simulation request failed.'
                );
            }

            const predictions = Array.isArray(responseData.predictions)
                ? responseData.predictions
                : [];

            citywideResults = predictions.map(normalizeResult);

            if (citywideResults.length === 0) {
                throw new Error('The ML API returned no barangay predictions.');
            }

            citywideResults.sort((first, second) => {
                const severityDifference =
                    severityWeight(second.floodCode) -
                    severityWeight(first.floodCode);

                if (severityDifference !== 0) {
                    return severityDifference;
                }

                return second.confidence - first.confidence;
            });

            citywideResults = citywideResults.map((result, index) => ({
                ...result,
                rank: index + 1
            }));

            renderSummary();
            renderFilteredResults();
            renderScenarioSummary(responseData);
            const historyLink = document.getElementById('simulation-history-link');
            if (responseData.history_url) {
                historyLink.href = responseData.history_url;
                historyLink.classList.remove('hidden');
            }

            resultsContainer.classList.remove('hidden');
            updateModelStatus('Severity model connected', 'success');

            document.getElementById('last-simulation-time').textContent =
                'Last simulation: ' + new Date().toLocaleString();

            showMessage(
                'Rainfall severity simulation completed for ' +
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
            updateModelStatus('Model connection error', 'error');
            showMessage(
                error.message || 'Unable to complete the rainfall simulation.',
                'error'
            );
        } finally {
            setLoading(false);
        }
    }

    function normalizeResult(item) {
        const code = String(item.flood_code || '').toUpperCase();
        return {
            barangay: String(item.barangay || ''),
            floodCode: ['A', 'B', 'C', 'D'].includes(code) ? code : 'A',
            confidence: Number(item.flood_severity_confidence || item.confidence || 0)
        };
    }

    function renderSummary() {
        ['A', 'B', 'C', 'D'].forEach(code => {
            const count = citywideResults.filter(
                result => result.floodCode === code
            ).length;

            document.getElementById(
                'severity-' + code.toLowerCase() + '-count'
            ).textContent = String(count);
        });
    }

    function renderFilteredResults() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedSeverity = severityFilter.value;

        const filtered = citywideResults.filter(result => {
            const matchesSearch = result.barangay
                .toLowerCase()
                .includes(searchTerm);
            const matchesSeverity = selectedSeverity === 'all' ||
                result.floodCode === selectedSeverity;

            return matchesSearch && matchesSeverity;
        });

        resultsTableBody.innerHTML = '';

        filtered.forEach(result => {
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
                    ${severityBadge(result.floodCode)}
                </td>
            `;
            resultsTableBody.appendChild(row);
        });

        emptyResults.classList.toggle('hidden', filtered.length !== 0);
    }

    function renderScenarioSummary(responseData) {
        const simulation = responseData.simulation || {};
        const date = simulation.date || new Date().toLocaleDateString();
        const weather = simulation.automatic_features || {};
        const temp = weather.temperature_c;
        const wind = weather.wind_speed_kph;

        let text =
            'Scenario ' + date + ': ' +
            formatNumber(lastScenario.rainfall24h) + ' mm / 24h, ' +
            formatNumber(lastScenario.rainfall3d) + ' mm / 3d, ' +
            formatNumber(lastScenario.rainfall7d) + ' mm / 7d.';

        if (temp !== null && temp !== undefined) {
            text += ' Automatic temperature: ' + formatNumber(temp) + ' °C.';
        }

        if (wind !== null && wind !== undefined) {
            text += ' Wind: ' + formatNumber(wind) + ' km/h.';
        }

        document.getElementById('scenario-summary').textContent = text;
    }

    function resetForm() {
        form.reset();
        citywideResults = [];
        lastScenario = null;
        resultsContainer.classList.add('hidden');
        hideMessage();
        updateModelStatus('Model not tested', 'neutral');
        document.getElementById('last-simulation-time').textContent =
            'No simulation performed';
        searchInput.value = '';
        severityFilter.value = 'all';
    }

    function setLoading(isLoading) {
        runButton.disabled = isLoading;
        resetButton.disabled = isLoading;
        loadingSection.classList.toggle('hidden', !isLoading);
        runButton.textContent = isLoading
            ? 'Running Simulation...'
            : 'Run Citywide Simulation';
    }

    function updateModelStatus(text, state) {
        const dot = document.getElementById('model-status-dot');
        const label = document.getElementById('model-status-text');
        label.textContent = text;

        dot.className = 'h-2.5 w-2.5 rounded-full ' + (
            state === 'success'
                ? 'bg-emerald-500'
                : state === 'error'
                    ? 'bg-red-500'
                    : 'bg-slate-400'
        );
    }

    function showMessage(message, type) {
        messageBox.textContent = message;
        messageBox.className = type === 'success'
            ? 'rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800'
            : 'rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800';
    }

    function hideMessage() {
        messageBox.className = 'hidden rounded-2xl border px-5 py-4 text-sm';
        messageBox.textContent = '';
    }

    function getNumber(id) {
        const value = Number(document.getElementById(id).value);
        return Number.isFinite(value) ? value : 0;
    }

    function severityWeight(code) {
        return { A: 1, B: 2, C: 3, D: 4 }[code] || 0;
    }

    function severityBadge(code) {
        const config = {
            A: ['Minor · 0.5 ft', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
            B: ['Moderate · 1.5 ft', 'border-amber-200 bg-amber-50 text-amber-800'],
            C: ['Severe · 2.0 ft', 'border-orange-200 bg-orange-50 text-orange-800'],
            D: ['Critical · 2.5 ft+', 'border-red-200 bg-red-50 text-red-800']
        };

        const selected = config[code] || config.A;
        return `
            <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${selected[1]}">
                Code ${escapeHtml(code)} · ${escapeHtml(selected[0])}
            </span>
        `;
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        if (!text) {
            return {};
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('The server returned an invalid response.');
        }
    }

    function formatNumber(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return '0';
        }
        return number.toFixed(2).replace(/\.00$/, '');
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
})();
</script>
@endsection
