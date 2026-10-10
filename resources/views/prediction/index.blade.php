@extends('layouts.app')

@section('title', 'Flood Prediction | M.A.P.S')

@section('content')
<div class="space-y-6">
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

        <a href="{{ route('prediction.history.index') }}" class="inline-flex w-fit rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Prediction History</a>

        <div class="inline-flex w-fit items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold
            {{ $apiAvailable
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-rose-200 bg-rose-50 text-rose-700' }}">
            <span class="h-2.5 w-2.5 rounded-full
                {{ $apiAvailable ? 'bg-emerald-500' : 'bg-rose-500' }}">
            </span>

            ML API {{ $apiAvailable ? 'Online' : 'Offline' }}
        </div>
    </div>

    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @unless ($apiAvailable)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            The ML API is offline. Start it using:

            <code class="mt-2 block rounded bg-amber-100 px-3 py-2 font-mono text-xs">
                python -m uvicorn main:app --reload --host 127.0.0.1 --port 8000
            </code>
        </div>
    @endunless

    @if ($weatherAvailable)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Current Weather — Mandaluyong City
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Source: {{ $liveWeather['source'] ?? 'Open-Meteo' }}
                        </p>
                    </div>

                    <p class="text-xs text-slate-500">
                        Observed:
                        {{ isset($liveWeather['observed_at'])
                            ? \Carbon\Carbon::parse($liveWeather['observed_at'])
                                ->timezone('Asia/Manila')
                                ->format('M d, Y h:i A')
                            : 'Unavailable' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
                <div class="rounded-xl bg-sky-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">
                        Condition
                    </p>

                    <p class="mt-2 text-lg font-bold text-slate-900">
                        {{ $liveWeather['weather_description'] ?? 'Unavailable' }}
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Temperature
                    </p>

                    <p class="mt-2 text-2xl font-bold text-slate-900">
                        {{ number_format(
                            (float) ($liveWeather['current_temperature_c'] ?? 0),
                            1
                        ) }}°C
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Humidity
                    </p>

                    <p class="mt-2 text-2xl font-bold text-slate-900">
                        {{ number_format(
                            (float) ($liveWeather['avg_rh_pct'] ?? 0),
                            1
                        ) }}%
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Current Rain
                    </p>

                    <p class="mt-2 text-2xl font-bold text-slate-900">
                        {{ number_format(
                            (float) ($liveWeather['current_rain_mm'] ?? 0),
                            2
                        ) }}
                        <span class="text-sm text-slate-500">mm</span>
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Wind Speed
                    </p>

                    <p class="mt-2 text-2xl font-bold text-slate-900">
                        {{ number_format(
                            (float) ($liveWeather['avg_wind_speed'] ?? 0),
                            1
                        ) }}
                        <span class="text-sm text-slate-500">m/s</span>
                    </p>
                </div>
            </div>
        </section>

        @php
            $forecastWindows = $liveWeather['forecast_windows'] ?? [];
            $selectedHours = (int) ($selectedForecastHours ?? old('forecast_hours', 24));

            if (! in_array($selectedHours, [24, 48, 72], true)) {
                $selectedHours = 24;
            }

            $selectedWindow = $forecastWindows[(string) $selectedHours]
                ?? $forecastWindows['24']
                ?? [
                    'start_display' => $liveWeather['forecast_start_display'] ?? 'Unavailable',
                    'end_display' => $liveWeather['forecast_end_display'] ?? 'Unavailable',
                    'rainfall_mm' => $liveWeather['forecast_rainfall_24h_mm'] ?? 0,
                ];
        @endphp

        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 sm:p-6">
            <form
                method="POST"
                action="{{ route('prediction.citywide') }}"
                class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center"
            >
                @csrf

                <div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <label
                                for="forecast_hours"
                                class="text-sm font-semibold uppercase tracking-wide text-sky-700"
                            >
                                Forecast Window
                            </label>

                            <div class="mt-2 flex items-center gap-3">
                                <select
                                    id="forecast_hours"
                                    name="forecast_hours"
                                    class="rounded-xl border border-sky-300 bg-white px-4 py-2.5 text-sm font-bold text-sky-950 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200"
                                >
                                    @foreach ([24, 48, 72] as $hours)
                                        <option
                                            value="{{ $hours }}"
                                            @selected($selectedHours === $hours)
                                        >
                                            {{ $hours }} Hours
                                        </option>
                                    @endforeach
                                </select>

                                <h2
                                    id="forecast-window-title"
                                    class="text-2xl font-bold text-sky-950"
                                >
                                    Next {{ $selectedHours }} Hours
                                </h2>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Valid From
                            </p>

                            <p
                                id="forecast-valid-from"
                                class="mt-1 text-sm font-semibold text-sky-950"
                            >
                                {{ $selectedWindow['start_display'] ?? 'Unavailable' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Valid Until
                            </p>

                            <p
                                id="forecast-valid-until"
                                class="mt-1 text-sm font-semibold text-sky-950"
                            >
                                {{ $selectedWindow['end_display'] ?? 'Unavailable' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Forecast Rainfall
                            </p>

                            <p
                                id="forecast-rainfall"
                                class="mt-1 text-sm font-semibold text-sky-950"
                            >
                                {{ number_format(
                                    (float) ($selectedWindow['rainfall_mm'] ?? 0),
                                    2
                                ) }} mm
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 max-w-3xl text-sm leading-6 text-sky-800">
                        The selected window is evaluated in consecutive 24-hour model periods.
                        For 48 or 72 hours, M.A.P.S. reports the highest flood severity expected
                        within the selected period.
                    </p>
                </div>

                <button
                    type="submit"
                    @disabled(! $apiAvailable || ! $weatherAvailable)
                    class="inline-flex w-full items-center justify-center rounded-xl bg-sky-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 lg:w-auto"
                >
                    Run Citywide Prediction
                </button>
            </form>
        </section>
    @else
        <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
            <h2 class="font-semibold text-rose-800">
                Weather Data Unavailable
            </h2>

            <p class="mt-2 text-sm text-rose-700">
                {{ $weatherError ?? 'Current weather could not be retrieved.' }}
            </p>
        </section>
    @endif

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

@if ($weatherAvailable)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('forecast_hours');

            if (!select) {
                return;
            }

            const windows = @json($liveWeather['forecast_windows'] ?? []);
            const title = document.getElementById('forecast-window-title');
            const validFrom = document.getElementById('forecast-valid-from');
            const validUntil = document.getElementById('forecast-valid-until');
            const rainfall = document.getElementById('forecast-rainfall');

            const renderWindow = () => {
                const hours = String(select.value);
                const windowData = windows[hours];

                if (!windowData) {
                    return;
                }

                title.textContent = `Next ${hours} Hours`;
                validFrom.textContent = windowData.start_display ?? 'Unavailable';
                validUntil.textContent = windowData.end_display ?? 'Unavailable';

                const rainValue = Number(windowData.rainfall_mm ?? 0);
                rainfall.textContent = `${rainValue.toFixed(2)} mm`;
            };

            select.addEventListener('change', renderWindow);
            renderWindow();
        });
    </script>
@endif
@endsection
