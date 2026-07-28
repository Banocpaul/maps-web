@extends('layouts.app')

@section('title', 'Flood Prediction | M.A.P.S.')

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
                predictions for all barangays using the upcoming 24-hour forecast.
            </p>
        </div>

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
                python -m uvicorn main:app --reload --host 127.0.0.1 --port 8001
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

        <section class="rounded-2xl border border-sky-200 bg-sky-50 p-5 sm:p-6">
            <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">
                        Forecast Window
                    </p>

                    <h2 class="mt-1 text-2xl font-bold text-sky-950">
                        Next 24 Hours
                    </h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Valid From
                            </p>

                            <p class="mt-1 text-sm font-semibold text-sky-950">
                                {{ $liveWeather['forecast_start_display'] ?? 'Unavailable' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Valid Until
                            </p>

                            <p class="mt-1 text-sm font-semibold text-sky-950">
                                {{ $liveWeather['forecast_end_display'] ?? 'Unavailable' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">
                                Forecast Rainfall
                            </p>

                            <p class="mt-1 text-sm font-semibold text-sky-950">
                                {{ number_format(
                                    (float) (
                                        $liveWeather[
                                            'forecast_rainfall_24h_mm'
                                        ] ?? 0
                                    ),
                                    2
                                ) }} mm
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 max-w-3xl text-sm leading-6 text-sky-800">
                        The result estimates each barangay's flood-risk category
                        during this forecast period. It does not identify the exact
                        minute when flooding will occur.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('prediction.citywide') }}"
                >
                    @csrf

                    <button
                        type="submit"
                        @disabled(! $apiAvailable || ! $weatherAvailable)
                        class="inline-flex w-full items-center justify-center rounded-xl bg-sky-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 lg:w-auto"
                    >
                        Run Citywide Prediction
                    </button>
                </form>
            </div>
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
        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">
                    Citywide Result
                </p>

                <h2 class="mt-1 text-2xl font-bold text-slate-900">
                    Mandaluyong Barangay Risk Assessment
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $citywideResult['summary']['total_barangays'] ?? ($citywideResult['barangay_count'] ?? 0) }}
                    barangays analyzed
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['High', 'Medium', 'Low'] as $level)
                    @php
                        $summaryClass = match ($level) {
                            'High' => 'border-rose-200 bg-rose-50 text-rose-800',
                            'Medium' => 'border-amber-200 bg-amber-50 text-amber-800',
                            'Low' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        };
                    @endphp

                    <div class="rounded-2xl border p-5 {{ $summaryClass }}">
                        <p class="text-sm font-medium">{{ $level }} Risk</p>

                        <p class="mt-2 text-3xl font-bold">
                            {{ $citywideResult['summary']['risk_distribution'][$level] ?? ($citywideResult['risk_summary'][$level] ?? 0) }}
                        </p>
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
                                    Risk
                                </th>

                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-600">
                                    Confidence
                                </th>

                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-600">
                                    Depth
                                </th>

                                <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-600">
                                    Duration
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse (($citywideResult['predictions'] ?? []) as $index => $item)
                                @php
                                    $risk = $item['risk_level']
                                        ?? $item['predicted_risk_level']
                                        ?? 'Unknown';

                                    $badgeClass = match ($risk) {
                                        'High' => 'bg-rose-100 text-rose-700',
                                        'Medium' => 'bg-amber-100 text-amber-700',
                                        'Low' => 'bg-emerald-100 text-emerald-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp

                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm text-slate-500">
                                        {{ $index + 1 }}
                                    </td>

                                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                        {{ $item['barangay'] ?? 'Unknown' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">
                                            {{ $risk }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right text-sm text-slate-700">
                                        @php
                                            $confidenceValue =
                                                $item['confidence_percent']
                                                ?? null;

                                            if ($confidenceValue === null) {
                                                $confidenceValue =
                                                    ((float) (
                                                        $item['confidence']
                                                        ?? 0
                                                    )) * 100;
                                            }
                                        @endphp

                                        {{ number_format(
                                            (float) $confidenceValue,
                                            2
                                        ) }}%
                                    </td>

                                    <td class="px-4 py-3 text-right text-sm text-slate-700">
                                        {{ number_format(
                                            (float) (
                                                $item['predicted_depth_mm']
                                                ?? $item[
                                                    'predicted_flood_depth_mm'
                                                ]
                                                ?? 0
                                            ),
                                            2
                                        ) }} mm
                                    </td>

                                    <td class="px-4 py-3 text-right text-sm text-slate-700">
                                        {{ number_format(
                                            (float) (
                                                $item[
                                                    'predicted_duration_hours'
                                                ] ?? 0
                                            ),
                                            2
                                        ) }} h
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
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
@endsection