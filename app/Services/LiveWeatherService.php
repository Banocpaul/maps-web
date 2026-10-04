<?php

namespace App\Services;

use App\Models\DailyWeatherSnapshot;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LiveWeatherService
{
    private const LATITUDE = 14.5794;
    private const LONGITUDE = 121.0359;
    private const TIMEZONE = 'Asia/Manila';
    private const FORECAST_URL =
        'https://api.open-meteo.com/v1/forecast';

    private int $timeoutSeconds = 30;
    private int $connectTimeoutSeconds = 10;

    /**
     * Retrieve today's Mandaluyong weather snapshot.
     *
     * The Open-Meteo request includes seven past days plus seven forecast
     * days, so the same daily snapshot can support 24, 48 and 72-hour
     * prediction windows without making extra weather requests.
     */
    public function getCurrentWeather(): array
    {
        $snapshotDate = now(self::TIMEZONE)->toDateString();
        $snapshot = $this->findSnapshot($snapshotDate);

        if ($this->hasUsableSnapshot($snapshot)) {
            return $this->snapshotResponse($snapshot, 'database');
        }

        if (
            $snapshot !== null
            && $snapshot->source === 'Open-Meteo-Attempt-Failed'
        ) {
            return $this->staleFallbackOrFail($snapshotDate);
        }

        return Cache::lock(
            'daily-weather-snapshot:' . $snapshotDate,
            60
        )->block(15, function () use ($snapshotDate): array {
            $snapshot = $this->findSnapshot($snapshotDate);

            if ($this->hasUsableSnapshot($snapshot)) {
                return $this->snapshotResponse($snapshot, 'database');
            }

            if (
                $snapshot !== null
                && $snapshot->source === 'Open-Meteo-Attempt-Failed'
            ) {
                return $this->staleFallbackOrFail($snapshotDate);
            }

            $manilaNow = now(self::TIMEZONE);

            if ($snapshot === null) {
                $snapshot = DailyWeatherSnapshot::query()->create([
                    'snapshot_date' => $snapshotDate,
                    'source' => 'Open-Meteo-Attempting',
                    'weather_data' => [],
                    'fetched_at' => now(),
                    'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                ]);
            } else {
                $snapshot->update([
                    'source' => 'Open-Meteo-Attempting',
                    'fetched_at' => now(),
                    'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                ]);
            }

            try {
                $weather = $this->fetchCurrentWeather();

                $snapshot->update([
                    'source' => 'Open-Meteo',
                    'weather_data' => $weather,
                    'fetched_at' => now(),
                    'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                ]);

                $snapshot->refresh();

                return $this->snapshotResponse(
                    $snapshot,
                    'open-meteo'
                );
            } catch (Throwable $exception) {
                $snapshot->update([
                    'source' => 'Open-Meteo-Attempt-Failed',
                    'fetched_at' => now(),
                    'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                ]);

                Log::warning(
                    'Daily Open-Meteo request failed; no second request will be made today.',
                    [
                        'message' => $exception->getMessage(),
                        'snapshot_date' => $snapshotDate,
                    ]
                );

                return $this->staleFallbackOrFail(
                    $snapshotDate,
                    $exception
                );
            }
        });
    }

    /**
     * Compatibility method. It still follows the once-per-day snapshot rule.
     */
    public function refreshCurrentWeather(): array
    {
        return $this->getCurrentWeather();
    }

    /**
     * Return the public seven-day forecast from the same daily snapshot.
     */
    public function getSevenDayForecast(): array
    {
        $weather = $this->getCurrentWeather();
        $forecast = $weather['seven_day_forecast'] ?? [];

        return is_array($forecast) ? $forecast : [];
    }

    private function fetchCurrentWeather(): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->get(self::FORECAST_URL, [
                    'latitude' => self::LATITUDE,
                    'longitude' => self::LONGITUDE,

                    'current' => implode(',', [
                        'temperature_2m',
                        'relative_humidity_2m',
                        'precipitation',
                        'rain',
                        'wind_speed_10m',
                        'wind_direction_10m',
                        'weather_code',
                    ]),

                    'hourly' => implode(',', [
                        'temperature_2m',
                        'relative_humidity_2m',
                        'precipitation',
                        'rain',
                        'wind_speed_10m',
                        'wind_direction_10m',
                        'weather_code',
                    ]),

                    'daily' => implode(',', [
                        'weather_code',
                        'temperature_2m_max',
                        'temperature_2m_min',
                        'precipitation_probability_max',
                        'precipitation_sum',
                        'rain_sum',
                        'wind_speed_10m_max',
                    ]),

                    'past_days' => 7,
                    'forecast_days' => 7,
                    'wind_speed_unit' => 'ms',
                    'precipitation_unit' => 'mm',
                    'temperature_unit' => 'celsius',
                    'timezone' => self::TIMEZONE,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to Open-Meteo. Check the internet connection and try again tomorrow.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'An unexpected weather-service error occurred: '
                . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->processResponse($response);
    }

    private function findSnapshot(
        string $snapshotDate
    ): ?DailyWeatherSnapshot {
        return DailyWeatherSnapshot::query()
            ->where('snapshot_date', $snapshotDate)
            ->first();
    }

    private function hasUsableSnapshot(
        ?DailyWeatherSnapshot $snapshot
    ): bool {
        if ($snapshot === null) {
            return false;
        }

        $weather = $snapshot->weather_data;

        /*
         * forecast_windows is required by the new 24/48/72-hour selector.
         * Requiring it also upgrades an older same-day snapshot once after
         * this version is deployed.
         */
        return is_array($weather)
            && $weather !== []
            && isset(
                $weather['date'],
                $weather['avg_temp_mean_c'],
                $weather['seven_day_forecast'],
                $weather['forecast_windows']['24'],
                $weather['forecast_windows']['48'],
                $weather['forecast_windows']['72']
            );
    }

    private function findLatestUsableSnapshotBefore(
        string $snapshotDate
    ): ?DailyWeatherSnapshot {
        $snapshots = DailyWeatherSnapshot::query()
            ->where('snapshot_date', '<', $snapshotDate)
            ->orderByDesc('snapshot_date')
            ->limit(14)
            ->get();

        foreach ($snapshots as $snapshot) {
            if ($this->hasUsableSnapshot($snapshot)) {
                return $snapshot;
            }
        }

        return null;
    }

    private function staleFallbackOrFail(
        string $snapshotDate,
        ?Throwable $originalException = null
    ): array {
        $previous = $this->findLatestUsableSnapshotBefore(
            $snapshotDate
        );

        if ($previous !== null) {
            Log::warning(
                'Serving previous successful weather snapshot as fallback.',
                [
                    'requested_date' => $snapshotDate,
                    'fallback_date' => (string) $previous->snapshot_date,
                ]
            );

            return $this->snapshotResponse(
                $previous,
                'stale-database',
                true
            );
        }

        throw new RuntimeException(
            'Weather data is unavailable. The daily Open-Meteo request failed and no previous successful snapshot is available.',
            previous: $originalException
        );
    }

    private function snapshotResponse(
        DailyWeatherSnapshot $snapshot,
        string $retrievedFrom,
        bool $isStale = false
    ): array {
        $weather = $snapshot->weather_data;

        if (! is_array($weather) || $weather === []) {
            throw new RuntimeException(
                'The stored daily weather snapshot is invalid.'
            );
        }

        return array_merge($weather, [
            'daily_snapshot_date' =>
                (string) $snapshot->snapshot_date,

            'weather_fetched_at' =>
                $snapshot->fetched_at?->toIso8601String(),

            'weather_expires_at' =>
                $snapshot->expires_at?->toIso8601String(),

            'weather_retrieved_from' => $retrievedFrom,
            'weather_is_stale' => $isStale,
        ]);
    }

    public function isAvailable(): bool
    {
        try {
            $weather = $this->getCurrentWeather();

            return isset(
                $weather['date'],
                $weather['time'],
                $weather['avg_temp_mean_c']
            );
        } catch (Throwable $exception) {
            Log::warning('Live weather availability check failed.', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function processResponse(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException(
                'Open-Meteo returned HTTP '
                . $response->status()
                . '.'
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException(
                'Open-Meteo returned invalid JSON.'
            );
        }

        $current = $data['current'] ?? null;
        $hourly = $data['hourly'] ?? null;
        $daily = $data['daily'] ?? null;

        if (
            ! is_array($current)
            || ! is_array($hourly)
            || ! is_array($daily)
        ) {
            throw new RuntimeException(
                'Required current, hourly, or daily weather data is missing.'
            );
        }

        $currentDateTime = $this->parseDateTime(
            $current['time'] ?? null
        );

        $hourlyRecords = $this->buildHourlyRecords($hourly);

        $pastRainfall24Hours = $this->sumRainfallBetween(
            records: $hourlyRecords,
            start: $currentDateTime->copy()->subHours(24),
            end: $currentDateTime
        );

        $pastRainfall3Days = $this->sumRainfallBetween(
            records: $hourlyRecords,
            start: $currentDateTime->copy()->subHours(72),
            end: $currentDateTime
        );

        $pastRainfall7Days = $this->sumRainfallBetween(
            records: $hourlyRecords,
            start: $currentDateTime->copy()->subHours(168),
            end: $currentDateTime
        );

        $forecastWindows = [];

        foreach ([24, 48, 72] as $hours) {
            $forecastWindows[(string) $hours] =
                $this->buildForecastWindow(
                    $hourlyRecords,
                    $currentDateTime,
                    $hours
                );
        }

        $window24 = $forecastWindows['24'];

        $currentTemperature = $this->nullableFloat(
            $current['temperature_2m'] ?? null
        );

        $currentHumidity = $this->nullableFloat(
            $current['relative_humidity_2m'] ?? null
        );

        $currentWind = $this->nullableFloat(
            $current['wind_speed_10m'] ?? null
        );

        $weatherCode = $this->nullableInteger(
            $current['weather_code'] ?? null
        );

        $sevenDayForecast = $this->buildSevenDayForecast(
            $daily,
            $currentDateTime,
            is_array($data['daily_units'] ?? null)
                ? $data['daily_units']
                : []
        );

        return [
            'source' => 'Open-Meteo',
            'location' => 'Mandaluyong City',
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'timezone' => self::TIMEZONE,

            'date' => $currentDateTime->format('Y-m-d'),
            'time' => $currentDateTime->format('H:i'),
            'observed_at' => $currentDateTime->toIso8601String(),

            /*
             * Backward-compatible 24-hour fields.
             */
            'forecast_window_hours' => 24,
            'forecast_start' => $window24['start'],
            'forecast_end' => $window24['end'],
            'forecast_start_display' => $window24['start_display'],
            'forecast_end_display' => $window24['end_display'],

            'current_temperature_c' => $currentTemperature,
            'avg_temp_mean_c' => $currentTemperature,
            'avg_rh_pct' => $currentHumidity,
            'avg_wind_speed' => $currentWind,

            'avg_wind_direction_deg' =>
                $this->nullableFloat(
                    $current['wind_direction_10m'] ?? null
                ),

            'current_precipitation_mm' =>
                $this->nullableFloat(
                    $current['precipitation'] ?? null
                ),

            'current_rain_mm' =>
                $this->nullableFloat(
                    $current['rain'] ?? null
                ),

            'weather_code' => $weatherCode,
            'weather_description' =>
                $this->weatherDescription($weatherCode),

            'forecast_rainfall_24h_mm' =>
                $window24['rainfall_mm'],

            'rainfall_24h_mm' =>
                round($pastRainfall24Hours, 2),

            'rainfall_3d_mm' =>
                round($pastRainfall3Days, 2),

            'rainfall_7d_mm' =>
                round($pastRainfall7Days, 2),

            'forecast_tmax_c' =>
                $window24['temperature_max_c'],

            'forecast_tmin_c' =>
                $window24['temperature_min_c'],

            'forecast_mean_temp_c' =>
                $window24['temperature_mean_c'],

            'forecast_avg_humidity_pct' =>
                $window24['humidity_mean_pct'],

            'forecast_avg_wind_speed' =>
                $window24['wind_speed_mean'],

            'suggested_cause' =>
                $this->suggestCause(
                    (float) $window24['rainfall_mm'],
                    $weatherCode
                ),

            /*
             * New values used by the prediction page dropdown.
             */
            'forecast_windows' => $forecastWindows,

            'seven_day_forecast' => $sevenDayForecast,

            'weather_station_count' => 1,
            'weather_match_status' =>
                'Open-Meteo daily snapshot with 24/48/72-hour forecast windows',
        ];
    }

    /**
     * Build one cumulative future forecast window beginning now.
     */
    private function buildForecastWindow(
        array $records,
        Carbon $start,
        int $hours
    ): array {
        $end = $start->copy()->addHours($hours);

        $windowRecords = array_values(
            array_filter(
                $records,
                static function (array $record) use (
                    $start,
                    $end
                ): bool {
                    $time = $record['time'] ?? null;

                    return $time instanceof Carbon
                        && $time->greaterThanOrEqualTo($start)
                        && $time->lessThan($end);
                }
            )
        );

        $rainfall = array_sum(
            array_column(
                $windowRecords,
                'precipitation_mm'
            )
        );

        $temperatures = $this->numericColumn(
            $windowRecords,
            'temperature_c'
        );

        $humidity = $this->numericColumn(
            $windowRecords,
            'humidity_pct'
        );

        $wind = $this->numericColumn(
            $windowRecords,
            'wind_speed'
        );

        return [
            'hours' => $hours,
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
            'start_display' => $start->format('M d, Y h:i A'),
            'end_display' => $end->format('M d, Y h:i A'),
            'rainfall_mm' => round((float) $rainfall, 2),
            'temperature_mean_c' => $this->average($temperatures),
            'temperature_max_c' => $this->maximum($temperatures),
            'temperature_min_c' => $this->minimum($temperatures),
            'humidity_mean_pct' => $this->average($humidity),
            'wind_speed_mean' => $this->average($wind),
            'wind_speed_max' => $this->maximum($wind),
        ];
    }

    private function buildSevenDayForecast(
        array $daily,
        Carbon $currentDateTime,
        array $dailyUnits = []
    ): array {
        $times = $daily['time'] ?? [];

        $result = [
            'time' => [],
            'weather_code' => [],
            'temperature_2m_max' => [],
            'temperature_2m_min' => [],
            'precipitation_probability_max' => [],
            'precipitation_sum' => [],
            'rain_sum' => [],
            'wind_speed_10m_max' => [],
            'daily_units' => $dailyUnits,
            'days' => [],
        ];

        if (! is_array($times)) {
            return $result;
        }

        $today = $currentDateTime
            ->copy()
            ->startOfDay();

        foreach ($times as $index => $dateValue) {
            if (! is_string($dateValue)) {
                continue;
            }

            try {
                $date = Carbon::parse(
                    $dateValue,
                    self::TIMEZONE
                )->startOfDay();
            } catch (Throwable) {
                continue;
            }

            if ($date->lessThan($today)) {
                continue;
            }

            if (count($result['time']) >= 7) {
                break;
            }

            $weatherCode = $this->nullableInteger(
                $daily['weather_code'][$index] ?? null
            );

            $temperatureMax = $this->nullableFloat(
                $daily['temperature_2m_max'][$index] ?? null
            );

            $temperatureMin = $this->nullableFloat(
                $daily['temperature_2m_min'][$index] ?? null
            );

            $precipitationProbability = $this->nullableInteger(
                $daily['precipitation_probability_max'][$index] ?? null
            );

            $precipitationSum = $this->nullableFloat(
                $daily['precipitation_sum'][$index] ?? null
            );

            $rainSum = $this->nullableFloat(
                $daily['rain_sum'][$index] ?? null
            );

            $windSpeedMax = $this->nullableFloat(
                $daily['wind_speed_10m_max'][$index] ?? null
            );

            $dateString = $date->format('Y-m-d');

            $result['time'][] = $dateString;
            $result['weather_code'][] = $weatherCode;
            $result['temperature_2m_max'][] = $temperatureMax;
            $result['temperature_2m_min'][] = $temperatureMin;
            $result['precipitation_probability_max'][] =
                $precipitationProbability;
            $result['precipitation_sum'][] = $precipitationSum;
            $result['rain_sum'][] = $rainSum;
            $result['wind_speed_10m_max'][] = $windSpeedMax;

            $result['days'][] = [
                'date' => $dateString,
                'day_name' => $date->format('l'),
                'date_display' => $date->format('M d, Y'),
                'weather_code' => $weatherCode,
                'weather_description' =>
                    $this->weatherDescription($weatherCode),
                'temperature_max_c' => $temperatureMax,
                'temperature_min_c' => $temperatureMin,
                'precipitation_probability_max' =>
                    $precipitationProbability,
                'precipitation_sum_mm' => $precipitationSum,
                'rain_sum_mm' => $rainSum,
                'wind_speed_10m_max' => $windSpeedMax,
            ];
        }

        return $result;
    }

    private function buildHourlyRecords(array $hourly): array
    {
        $times = $hourly['time'] ?? [];

        if (! is_array($times)) {
            return [];
        }

        $records = [];

        foreach ($times as $index => $timeValue) {
            if (! is_string($timeValue)) {
                continue;
            }

            try {
                $time = Carbon::parse(
                    $timeValue,
                    self::TIMEZONE
                );
            } catch (Throwable) {
                continue;
            }

            $records[] = [
                'time' => $time,

                'precipitation_mm' => max(
                    0,
                    $this->nullableFloat(
                        $hourly['precipitation'][$index] ?? null
                    ) ?? 0
                ),

                'temperature_c' =>
                    $this->nullableFloat(
                        $hourly['temperature_2m'][$index] ?? null
                    ),

                'humidity_pct' =>
                    $this->nullableFloat(
                        $hourly[
                            'relative_humidity_2m'
                        ][$index] ?? null
                    ),

                'wind_speed' =>
                    $this->nullableFloat(
                        $hourly['wind_speed_10m'][$index] ?? null
                    ),

                'wind_direction_deg' =>
                    $this->nullableFloat(
                        $hourly['wind_direction_10m'][$index] ?? null
                    ),
            ];
        }

        return $records;
    }

    private function sumRainfallBetween(
        array $records,
        Carbon $start,
        Carbon $end
    ): float {
        $total = 0;

        foreach ($records as $record) {
            $time = $record['time'] ?? null;

            if (
                $time instanceof Carbon
                && $time->greaterThan($start)
                && $time->lessThanOrEqualTo($end)
            ) {
                $total += (float) (
                    $record['precipitation_mm'] ?? 0
                );
            }
        }

        return $total;
    }

    private function numericColumn(
        array $records,
        string $key
    ): array {
        $values = [];

        foreach ($records as $record) {
            $value = $record[$key] ?? null;

            if (is_numeric($value)) {
                $values[] = (float) $value;
            }
        }

        return $values;
    }

    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(
            array_sum($values) / count($values),
            2
        );
    }

    private function maximum(array $values): ?float
    {
        return $values === []
            ? null
            : round(max($values), 2);
    }

    private function minimum(array $values): ?float
    {
        return $values === []
            ? null
            : round(min($values), 2);
    }

    private function parseDateTime(mixed $value): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return now(self::TIMEZONE);
        }

        try {
            return Carbon::parse($value, self::TIMEZONE);
        } catch (Throwable) {
            return now(self::TIMEZONE);
        }
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? round((float) $value, 2)
            : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? (int) $value
            : null;
    }

    private function weatherDescription(?int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            in_array($code, [1, 2, 3], true) =>
                'Partly cloudy',
            in_array($code, [45, 48], true) =>
                'Foggy',
            in_array($code, [51, 53, 55, 56, 57], true) =>
                'Drizzle',
            in_array($code, [61, 63, 65, 66, 67], true) =>
                'Rain',
            in_array($code, [80, 81, 82], true) =>
                'Rain showers',
            in_array($code, [95, 96, 99], true) =>
                'Thunderstorm',
            default => 'Weather data available',
        };
    }

    private function suggestCause(
        float $forecastRainfall,
        ?int $weatherCode
    ): string {
        if (
            in_array(
                $weatherCode,
                [95, 96, 99],
                true
            )
        ) {
            return 'Thunderstorm';
        }

        if ($forecastRainfall >= 50) {
            return 'Heavy Rainfall';
        }

        if ($forecastRainfall >= 20) {
            return 'Continuous Rainfall';
        }

        return 'Forecast Weather Conditions';
    }
}
