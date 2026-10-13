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
                                    data-forecast-windows="{{ json_encode($forecastWindows) }}"
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
                    id="run-citywide-prediction"
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

