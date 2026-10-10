<?php

namespace App\Services;

use App\Models\DailyWeatherSnapshot;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    private const MAX_DAILY_ATTEMPTS = 3;
    private const RETRY_MINUTES = 15;
    private int $timeoutSeconds = 30;
    private int $connectTimeoutSeconds = 10;

    /** One successful snapshot per Manila day; at most three failed/successful attempts. */
    public function getCurrentWeather(): array
    {
        $snapshotDate = now(self::TIMEZONE)->toDateString();
        $snapshot = $this->findSnapshot($snapshotDate);

        if ($this->hasUsableSnapshot($snapshot)) {
            return $this->snapshotResponse($snapshot, 'database');
        }
        if ($this->retryIsBlocked($snapshot)) {
            return $this->staleFallbackOrFail($snapshotDate);
        }

        try {
            // A shared database lock also protects against the web app and cron job racing.
            return Cache::store('database')
                ->lock('maps:daily-weather-snapshot:' . $snapshotDate, 180)
                ->block(10, function () use ($snapshotDate): array {
                    $snapshot = $this->findSnapshot($snapshotDate);
                    if ($this->hasUsableSnapshot($snapshot)) {
                        return $this->snapshotResponse($snapshot, 'database');
                    }
                    if ($this->retryIsBlocked($snapshot)) {
                        return $this->staleFallbackOrFail($snapshotDate);
                    }

                    $manilaNow = now(self::TIMEZONE);
                    $attempt = $this->attemptCount($snapshot) + 1;
                    $retryAt = $manilaNow->copy()->startOfMinute()
                        ->addMinutes(self::RETRY_MINUTES);
                    $metadata = [
                        'attempts' => $attempt,
                        'last_attempt_at' => $manilaNow->toIso8601String(),
                        'next_retry_at' => $retryAt->toIso8601String(),
                        'last_error' => null,
                    ];
                    $attributes = [
                        'source' => 'Open-Meteo-Attempting',
                        'weather_data' => ['_daily_fetch' => $metadata],
                        'fetched_at' => now(),
                        'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                    ];

                    // Persist the attempt BEFORE the HTTP request, including on process restarts.
                    if ($snapshot === null) {
                        $snapshot = DailyWeatherSnapshot::query()->create(
                            array_merge(['snapshot_date' => $snapshotDate], $attributes)
                        );
                    } else {
                        $snapshot->update($attributes);
                    }

                    try {
                        $weather = $this->fetchCurrentWeather();
                        $forecastDates = $weather['seven_day_forecast']['time'] ?? [];
                        if (count($forecastDates) !== 7 || $forecastDates[0] !== $snapshotDate) {
                            throw new RuntimeException(
                                'Open-Meteo did not provide seven forecast days beginning on ' . $snapshotDate . '.'
                            );
                        }
                        $metadata['next_retry_at'] = null;
                        $weather['_daily_fetch'] = $metadata;
                        $snapshot->update([
                            'source' => 'Open-Meteo',
                            'weather_data' => $weather,
                            'fetched_at' => now(),
                            'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                        ]);
                        $snapshot->refresh();
                        Log::info('Daily Open-Meteo snapshot saved.', [
                            'snapshot_date' => $snapshotDate,
                            'attempt' => $attempt,
                        ]);
                        return $this->snapshotResponse($snapshot, 'open-meteo');
                    } catch (Throwable $exception) {
                        $metadata['last_error'] = $exception->getMessage();
                        $snapshot->update([
                            'source' => 'Open-Meteo-Attempt-Failed',
                            'weather_data' => ['_daily_fetch' => $metadata],
                            'fetched_at' => now(),
                            'expires_at' => $manilaNow->copy()->endOfDay()->utc(),
                        ]);
                        Log::warning('Daily Open-Meteo request failed.', [
                            'message' => $exception->getMessage(),
                            'snapshot_date' => $snapshotDate,
                            'attempt' => $attempt,
                            'next_retry_at' => $attempt < self::MAX_DAILY_ATTEMPTS
                                ? $metadata['next_retry_at'] : null,
                        ]);
                        return $this->staleFallbackOrFail($snapshotDate, $exception);
                    }
                });
        } catch (LockTimeoutException $exception) {
            // A concurrent fetch may have completed while this caller waited.
            $snapshot = $this->findSnapshot($snapshotDate);
            if ($this->hasUsableSnapshot($snapshot)) {
                return $this->snapshotResponse($snapshot, 'database');
            }
            return $this->staleFallbackOrFail($snapshotDate, $exception);
        }
    }

    private function attemptCount(?DailyWeatherSnapshot $snapshot): int
    {
        if ($snapshot === null) {
            return 0;
        }
        $weather = $snapshot->weather_data;
        $metadata = is_array($weather) ? ($weather['_daily_fetch'] ?? []) : [];
        if (is_array($metadata) && isset($metadata['attempts'])) {
            return max(0, (int) $metadata['attempts']);
        }
        // Old failed records count as one attempt, so deploying this code permits recovery.
        return 1;
    }

    private function retryIsBlocked(?DailyWeatherSnapshot $snapshot): bool
    {
        if ($snapshot === null) {
            return false;
        }
        if ($this->attemptCount($snapshot) >= self::MAX_DAILY_ATTEMPTS) {
            return true;
        }
        $weather = $snapshot->weather_data;
        $metadata = is_array($weather) ? ($weather['_daily_fetch'] ?? []) : [];
        $retryValue = is_array($metadata) ? ($metadata['next_retry_at'] ?? null) : null;
        if (is_string($retryValue) && $retryValue !== '') {
            try {
                return now(self::TIMEZONE)->lessThan(Carbon::parse($retryValue));
            } catch (Throwable) {
                // Fall through to the saved attempt timestamp if metadata is malformed.
            }
        }
        return $snapshot->fetched_at !== null
            && now(self::TIMEZONE)->lessThan(
                $snapshot->fetched_at->copy()->addMinutes(self::RETRY_MINUTES)
            );
    }

    /** Uses the same policy: a successful snapshot is never fetched again that day. */
    public function refreshCurrentWeather(): array
    {
        return $this->getCurrentWeather();
    }

    /** Keep real dates and values; never pad a stale forecast with invented days. */
    public function getSevenDayForecast(): array
    {
        $weather = $this->getCurrentWeather();
        $forecast = $weather['seven_day_forecast'] ?? [];
        if (! is_array($forecast)) {
            return [];
        }
        $today = now(self::TIMEZONE)->toDateString();
        $dates = $forecast['time'] ?? [];
        $indices = [];
        foreach (is_array($dates) ? $dates : [] as $index => $date) {
            if (is_string($date) && $date >= $today) {
                $indices[] = $index;
            }
        }
        $indices = array_slice($indices, 0, 7);
        foreach ([
            'time', 'weather_code', 'temperature_2m_max', 'temperature_2m_min',
            'precipitation_probability_max', 'precipitation_sum', 'rain_sum',
            'wind_speed_10m_max',
        ] as $key) {
            $values = is_array($forecast[$key] ?? null) ? $forecast[$key] : [];
            $forecast[$key] = array_map(
                static fn ($index) => $values[$index] ?? null,
                $indices
            );
        }
        $days = is_array($forecast['days'] ?? null) ? $forecast['days'] : [];
        $forecast['days'] = array_values(array_filter($days,
            static fn ($day) => is_array($day)
                && is_string($day['date'] ?? null)
                && $day['date'] >= $today
        ));
        $forecast['days'] = array_slice($forecast['days'], 0, 7);
        foreach ([
            'daily_snapshot_date', 'weather_fetched_at', 'weather_expires_at',
            'weather_retrieved_from', 'weather_is_stale',
        ] as $key) {
            $forecast[$key] = $weather[$key] ?? null;
        }
        $forecast['days_available'] = count($forecast['time']);
        return $forecast;
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
                'Cannot connect to Open-Meteo: ' . $exception->getMessage(),
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
        if ($snapshot === null || $snapshot->source !== 'Open-Meteo') {
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
            && is_array($weather['seven_day_forecast']['time'] ?? null)
            && count($weather['seven_day_forecast']['time']) === 7
            && ($weather['seven_day_forecast']['time'][0] ?? null)
                === $this->snapshotDateString($snapshot)
            && isset(
                $weather['date'],
                $weather['avg_temp_mean_c'],
                $weather['seven_day_forecast'],
                $weather['forecast_windows']['24'],
                $weather['forecast_windows']['48'],
                $weather['forecast_windows']['72']
            );
    }

    private function snapshotDateString(DailyWeatherSnapshot $snapshot): string
    {
        $value = $snapshot->snapshot_date;
        return $value instanceof Carbon
            ? $value->toDateString()
            : substr((string) $value, 0, 10);
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
                    'fallback_date' => $this->snapshotDateString($previous),
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
                $this->snapshotDateString($snapshot),

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
                . ': ' . substr((string) ($response->json('reason') ?? $response->body()), 0, 300)
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

        // Incident records use only elapsed hourly temperatures, never forecast extremes.
        $elapsedTemperatures = array_column(array_filter($hourlyRecords,
            fn (array $row): bool => $row['time']->toDateString() === $currentDateTime->toDateString()
                && $row['time']->lessThanOrEqualTo($currentDateTime)
                && $row['temperature_c'] !== null
        ), 'temperature_c');

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

        /*
         * Exact consecutive 24-hour windows used by the severity model.
         * They are generated from the same single daily Open-Meteo
         * response as the page weather.
         */
        $predictionWindows = $this->buildPredictionWindows(
            $hourlyRecords,
            $currentDateTime
        );

        $window24 = $forecastWindows['24'];

        $currentTemperature = $this->nullableFloat(
            $current['temperature_2m'] ?? null
        );
        if ($currentTemperature !== null) $elapsedTemperatures[] = $currentTemperature;

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
            now(self::TIMEZONE),
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
            'observed_temp_max_c' => $this->maximum($elapsedTemperatures),
            'observed_temp_min_c' => $this->minimum($elapsedTemperatures),
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

            /*
             * Consecutive 24-hour model windows. Wind values in these
             * windows are converted to km/h because the Random Forest
             * was trained with WIND_SPEED_KPH.
             */
            'prediction_windows' => $predictionWindows,

            'seven_day_forecast' => $sevenDayForecast,

            'weather_station_count' => 1,
            'weather_match_status' =>
                'Open-Meteo daily snapshot with 24/48/72-hour forecast windows',
        ];
    }

    /**
     * Build the three consecutive 24-hour windows used by FastAPI.
     *
     * The rolling 3-day and 7-day rainfall values are calculated
     * directly from the same hourly Open-Meteo response, so no second
     * weather request is needed.
     */
    private function buildPredictionWindows(
        array $records,
        Carbon $currentDateTime
    ): array {
        $windows = [];

        foreach ([0, 24, 48] as $index => $offsetHours) {
            $start = $currentDateTime
                ->copy()
                ->addHours($offsetHours);

            $end = $start
                ->copy()
                ->addHours(24);

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

            $rainfall24 = array_sum(
                array_column(
                    $windowRecords,
                    'precipitation_mm'
                )
            );

            $rainfall3d = $this->sumRainfallBetween(
                records: $records,
                start: $start->copy()->subHours(48),
                end: $end
            );

            $rainfall7d = $this->sumRainfallBetween(
                records: $records,
                start: $start->copy()->subHours(144),
                end: $end
            );

            $temperatures = $this->numericColumn(
                $windowRecords,
                'temperature_c'
            );

            $humidity = $this->numericColumn(
                $windowRecords,
                'humidity_pct'
            );

            $windMs = $this->numericColumn(
                $windowRecords,
                'wind_speed'
            );

            $windMeanMs = $this->average($windMs);
            $windMaxMs = $this->maximum($windMs);

            $hourlyRain = $this->numericColumn(
                $windowRecords,
                'precipitation_mm'
            );

            $windows[] = [
                'window_number' => $index + 1,
                'hours_from_now_start' => $offsetHours,
                'hours_from_now_end' => $offsetHours + 24,
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'start_display' => $start->format(
                    'M d, Y h:i A'
                ),
                'end_display' => $end->format(
                    'M d, Y h:i A'
                ),
                'rainfall_24h_mm' =>
                    round((float) $rainfall24, 2),
                'rainfall_3d_mm' =>
                    round((float) $rainfall3d, 2),
                'rainfall_7d_mm' =>
                    round((float) $rainfall7d, 2),
                'max_hourly_rain_mm' =>
                    $this->maximum($hourlyRain) ?? 0.0,
                'temperature_mean_c' =>
                    $this->average($temperatures),
                'temperature_max_c' =>
                    $this->maximum($temperatures),
                'temperature_min_c' =>
                    $this->minimum($temperatures),
                'humidity_mean_pct' =>
                    $this->average($humidity),

                /*
                 * Open-Meteo is requested in m/s by this Laravel
                 * service. Convert to km/h for the trained model.
                 */
                'wind_speed_mean_kph' =>
                    $windMeanMs === null
                        ? null
                        : round($windMeanMs * 3.6, 2),

                'wind_speed_max_kph' =>
                    $windMaxMs === null
                        ? null
                        : round($windMaxMs * 3.6, 2),
            ];
        }

        return $windows;
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
