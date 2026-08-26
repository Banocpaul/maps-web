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
     * Retrieve today's saved Mandaluyong weather snapshot. Open-Meteo is
     * called only when the Manila calendar date has no stored snapshot.
     */
    public function getCurrentWeather(): array
    {
        $snapshotDate = now(self::TIMEZONE)->toDateString();
        $snapshot = $this->findSnapshot($snapshotDate);

        if ($snapshot !== null) {
            return $this->snapshotResponse($snapshot, 'database');
        }

        return Cache::lock(
            'daily-weather-snapshot:' . $snapshotDate,
            60
        )->block(15, function () use ($snapshotDate): array {
            $snapshot = $this->findSnapshot($snapshotDate);

            if ($snapshot !== null) {
                return $this->snapshotResponse($snapshot, 'database');
            }

            return $this->refreshCurrentWeather();
        });
    }

    /**
     * Force a fresh Open-Meteo request and replace today's snapshot.
     */
    public function refreshCurrentWeather(): array
    {
        $weather = $this->fetchCurrentWeather();
        $manilaNow = now(self::TIMEZONE);
        $fetchedAt = now();
        $snapshot = DailyWeatherSnapshot::query()->updateOrCreate(
            ['snapshot_date' => $manilaNow->toDateString()],
            [
                'source' => 'Open-Meteo',
                'weather_data' => $weather,
                'fetched_at' => $fetchedAt,
                'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
            ]
        );

        return $this->snapshotResponse($snapshot, 'open-meteo');
    }

    /**
     * Retrieve current Mandaluyong weather, recent rainfall, and the
     * upcoming 24-hour forecast used by citywide prediction.
     */
    private function fetchCurrentWeather(): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(2, 500)
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
                        'temperature_2m_max',
                        'temperature_2m_min',
                        'precipitation_sum',
                        'rain_sum',
                    ]),

                    /*
                     * Seven past days are used for antecedent rainfall.
                     * Two forecast days ensure that a complete rolling
                     * 24-hour period is available even late in the day.
                     */
                    'past_days' => 7,
                    'forecast_days' => 2,

                    'wind_speed_unit' => 'ms',
                    'precipitation_unit' => 'mm',
                    'temperature_unit' => 'celsius',
                    'timezone' => self::TIMEZONE,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to Open-Meteo. Check the internet '
                . 'connection and try again.',
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

    private function findSnapshot(string $snapshotDate): ?DailyWeatherSnapshot
    {
        return DailyWeatherSnapshot::query()
            ->where('snapshot_date', $snapshotDate)
            ->first();
    }

    private function snapshotResponse(
        DailyWeatherSnapshot $snapshot,
        string $retrievedFrom
    ): array {
        $weather = $snapshot->weather_data;

        if (! is_array($weather)) {
            throw new RuntimeException(
                'The stored daily weather snapshot is invalid.'
            );
        }

        return array_merge($weather, [
            'daily_snapshot_date' =>
                (string) $snapshot->snapshot_date,
            'weather_fetched_at' =>
                $snapshot->fetched_at->toIso8601String(),
            'weather_expires_at' =>
                $snapshot->expires_at->toIso8601String(),
            'weather_retrieved_from' => $retrievedFrom,
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

        if (! is_array($current) || ! is_array($hourly)) {
            throw new RuntimeException(
                'Required current or hourly weather data is missing.'
            );
        }

        $currentDateTime = $this->parseDateTime(
            $current['time'] ?? null
        );

        $forecastEnd = $currentDateTime
            ->copy()
            ->addHours(24);

        $hourlyRecords = $this->buildHourlyRecords($hourly);

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

        $forecastRecords = array_values(
            array_filter(
                $hourlyRecords,
                static function (array $record) use (
                    $currentDateTime,
                    $forecastEnd
                ): bool {
                    $time = $record['time'] ?? null;

                    return $time instanceof Carbon
                        && $time->greaterThanOrEqualTo(
                            $currentDateTime
                        )
                        && $time->lessThan($forecastEnd);
                }
            )
        );

        $forecastRainfall = array_sum(
            array_column(
                $forecastRecords,
                'precipitation_mm'
            )
        );

        $forecastTemperatures = $this->numericColumn(
            $forecastRecords,
            'temperature_c'
        );

        $forecastHumidity = $this->numericColumn(
            $forecastRecords,
            'humidity_pct'
        );

        $forecastWind = $this->numericColumn(
            $forecastRecords,
            'wind_speed'
        );

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

        return [
            'source' => 'Open-Meteo',
            'location' => 'Mandaluyong City',
            'latitude' => self::LATITUDE,
            'longitude' => self::LONGITUDE,
            'timezone' => self::TIMEZONE,

            'date' => $currentDateTime->format('Y-m-d'),
            'time' => $currentDateTime->format('H:i'),
            'observed_at' => $currentDateTime->toIso8601String(),

            'forecast_window_hours' => 24,
            'forecast_start' =>
                $currentDateTime->toIso8601String(),

            'forecast_end' =>
                $forecastEnd->toIso8601String(),

            'forecast_start_display' =>
                $currentDateTime->format('M d, Y h:i A'),

            'forecast_end_display' =>
                $forecastEnd->format('M d, Y h:i A'),

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
                round((float) $forecastRainfall, 2),

            'rainfall_3d_mm' =>
                round($pastRainfall3Days, 2),

            'rainfall_7d_mm' =>
                round($pastRainfall7Days, 2),

            'forecast_tmax_c' =>
                $this->maximum($forecastTemperatures),

            'forecast_tmin_c' =>
                $this->minimum($forecastTemperatures),

            'forecast_mean_temp_c' =>
                $this->average($forecastTemperatures),

            'forecast_avg_humidity_pct' =>
                $this->average($forecastHumidity),

            'forecast_avg_wind_speed' =>
                $this->average($forecastWind),

            'suggested_cause' =>
                $this->suggestCause(
                    (float) $forecastRainfall,
                    $weatherCode
                ),

            'weather_station_count' => 1,
            'weather_match_status' =>
                'Open-Meteo current and forecast weather',
        ];
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
