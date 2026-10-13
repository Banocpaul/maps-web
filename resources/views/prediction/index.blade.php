@extends('layouts.app')

@section('title', 'Flood Prediction | M.A.P.S')

@section('content')
<div class="space-y-6">
    @include('prediction.partials.navigation-tabs', ['activeTab' => 'current'])
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">
                Predictive Analytics
            </p>

            <h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">
                Citywide Flood Prediction
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                View current Mandaluyong weather and generate automated flood-risk
                predictions for all barangays using a 24, 48, or 72-hour forecast window.
            </p>
        </div>



        <div id="prediction-api-status" role="status" class="inline-flex w-fit items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold
            {{ $apiAvailable === null ? 'border-slate-200 bg-slate-50 text-slate-600' : ($apiAvailable
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-rose-200 bg-rose-50 text-rose-700') }}">
            <span class="h-2.5 w-2.5 rounded-full
                {{ $apiAvailable === null ? 'bg-slate-400' : ($apiAvailable ? 'bg-emerald-500' : 'bg-rose-500') }}">
            </span>

            ML API {{ $apiAvailable === null ? 'Checking…' : ($apiAvailable ? 'Online' : 'Offline') }}
        </div>
    </div>

    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if ($apiAvailable === false)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            The ML API is offline. Start it using:

            <code class="mt-2 block rounded bg-amber-100 px-3 py-2 font-mono text-xs">
                python -m uvicorn main:app --reload --host 127.0.0.1 --port 8000
            </code>
        </div>
    @endif

    <div id="prediction-weather-panel" class="space-y-6" aria-live="polite">
        @if ($loadInBackground ?? false)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-busy="true">
                <h2 class="text-lg font-semibold text-slate-900">Current Weather — Mandaluyong City</h2>
                <p class="mt-2 text-sm text-slate-500">Loading current weather and forecast…</p>
            </section>
        @else
            @include('prediction.partials.weather-panel')
        @endif
    </div>

    @if (is_array($citywideResult))
        @php
            $predictions = collect($citywideResult['predictions'] ?? []);
            $severityCounts = $predictions
                ->countBy(fn ($item) => $item['flood_code'] ?? 'Unknown');
            $resultHours = (int) (
                $citywideResult['forecast_hours']
                ?? $selectedForecastHours
                ?? 24
            );
        @endphp

        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">
                    Citywide Result
                </p>

                <h2 class="mt-1 text-2xl font-bold text-slate-900">
                    Mandaluyong Barangay Flood Severity Assessment
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $citywideResult['summary']['total_barangays'] ?? ($citywideResult['barangay_count'] ?? 0) }}
                    barangays analyzed for flood severity over the next {{ $resultHours }} hours.
                    Each barangay is classified as Level A, B, C, or D. Confidence is the model probability of the displayed flood code.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['A', 'B', 'C', 'D'] as $code)
                    @php
                        $summaryClass = match ($code) {
                            'A' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'B' => 'border-sky-200 bg-sky-50 text-sky-800',
                            'C' => 'border-amber-200 bg-amber-50 text-amber-800',
                            'D' => 'border-rose-200 bg-rose-50 text-rose-800',
                        };

                        $severityName = match ($code) {
                            'A' => 'Minor (0.5 ft)',
                            'B' => 'Moderate (1.5 ft)',
                            'C' => 'Severe (2.0 ft)',
                            'D' => 'Critical (2.5 ft+)',
                        };
                    @endphp

                    <div class="rounded-2xl border p-5 {{ $summaryClass }}">
                        <p class="text-sm font-medium">Level {{ $code }}</p>
                        <p class="mt-1 text-xs">{{ $severityName }}</p>
                        <p class="mt-2 text-3xl font-bold">{{ $severityCounts[$code] ?? 0 }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">
                                    Rank
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">
                                    Barangay
                                </th>

                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">
                                    Flood Severity
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-600">
                                    Confidence
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse (($citywideResult['predictions'] ?? []) as $index => $item)
                                @php
                                    $floodCode = $item['flood_code'] ?? 'Unknown';

                                    $severityText = match ($floodCode) {
                                        'A' => 'Level A - Minor Flooding (0.5 ft)',
                                        'B' => 'Level B - Moderate Flooding (1.5 ft)',
                                        'C' => 'Level C - Severe Flooding (2.0 ft)',
                                        'D' => 'Level D - Critical Flooding (2.5 ft+)',
                                        default => 'Severity unavailable',
                                    };

                                    $badgeClass = match ($floodCode) {
                                        'A' => 'bg-emerald-100 text-emerald-700',
                                        'B' => 'bg-sky-100 text-sky-700',
                                        'C' => 'bg-amber-100 text-amber-700',
                                        'D' => 'bg-rose-100 text-rose-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp

                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm text-slate-500">
                                        {{ $item['rank'] ?? ($index + 1) }}
                                    </td>

                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                        {{ $item['barangay'] ?? 'Unknown' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">
                                            {{ $floodCode }}
                                        </span>

                                        <span class="ml-2 text-xs text-slate-600">
                                            {{ $severityText }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @include('prediction.partials.confidence', ['item' => $item])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
                                        No prediction records were returned.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const panel = document.getElementById('prediction-weather-panel');
        let apiAvailable = @json($apiAvailable);
        const initialiseForecast = () => {
            const select = panel.querySelector('#forecast_hours');
            if (!select) return;
            const windows = JSON.parse(select.dataset.forecastWindows || '{}');
            const render = () => {
                const data = windows[String(select.value)];
                if (!data) return;
                panel.querySelector('#forecast-window-title').textContent = `Next ${select.value} Hours`;
                panel.querySelector('#forecast-valid-from').textContent = data.start_display ?? 'Unavailable';
                panel.querySelector('#forecast-valid-until').textContent = data.end_display ?? 'Unavailable';
                panel.querySelector('#forecast-rainfall').textContent = `${Number(data.rainfall_mm ?? 0).toFixed(2)} mm`;
            };
            select.addEventListener('change', render);
            render();
        };
        const updateButton = () => {
            const button = panel.querySelector('#run-citywide-prediction');
            if (button) button.disabled = apiAvailable !== true;
        };
        initialiseForecast();
        @if ($loadInBackground ?? false)
            const request = async (url, type) => {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': type === 'json' ? 'application/json' : 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: AbortSignal.timeout(60000),
                });
                if (!response.ok || response.redirected) throw new Error('Request failed');
                return type === 'json' ? response.json() : response.text();
            };
            request(@json(route('prediction.api-status')), 'json')
                .then(data => { apiAvailable = data.available === true; })
                .catch(() => { apiAvailable = false; })
                .finally(() => {
                    const badge = document.getElementById('prediction-api-status');
                    badge.textContent = apiAvailable ? 'ML API Online' : 'ML API Unavailable';
                    badge.className = 'inline-flex w-fit items-center rounded-full border px-4 py-2 text-sm font-semibold ' +
                        (apiAvailable ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700');
                    updateButton();
                });
            const loadWeather = () => {
                request(@json(route('prediction.weather-panel')), 'html')
                    .then(html => {
                        panel.innerHTML = html;
                        initialiseForecast();
                        updateButton();
                        if (!panel.querySelector('#forecast_hours')) addRetry();
                    })
                    .catch(() => {
                        panel.textContent = 'Current weather could not be loaded. Please try again.';
                        addRetry();
                    });
            };
            const addRetry = () => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = 'Retry weather';
                button.className = 'mt-3 rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white';
                button.addEventListener('click', () => {
                    panel.textContent = 'Loading current weather and forecast…';
                    loadWeather();
                });
                panel.append(button);
            };
            loadWeather();
        @endif
    });
</script>
@endsection
