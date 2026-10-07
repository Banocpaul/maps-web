<?php

namespace App\Http\Controllers;

use App\Models\PublicAdvisory;
use App\Services\LiveWeatherService;
use Illuminate\View\View;
use Throwable;

class PublicPortalController extends Controller
{
    public function __construct(
        private readonly LiveWeatherService $liveWeatherService
    ) {
    }

    /**
     * Public portal homepage.
     */
    public function index(): View
    {
        $weather = null;
        $weatherError = null;

        try {
            $weather = $this->liveWeatherService->getCurrentWeather();
        } catch (Throwable $exception) {
            report($exception);

            $weatherError = 'Live weather data is temporarily unavailable.';
        }

        return view('public.index', [
            'weather' => $weather,
            'weatherError' => $weatherError,
        ]);
    }

    /**
     * Public 7-day weather forecast page.
     *
     * Uses the SAME daily weather snapshot used by the rest
     * of M.A.P.S. No separate Open-Meteo request is made here.
     */
    public function weather(): View
    {
        $forecast = [];
        $weatherError = null;
        $weatherFetchedAt = null;
        $weatherIsStale = false;

        try {
            $dailyForecast =
                $this->liveWeatherService->getSevenDayForecast();
            $weatherFetchedAt = $dailyForecast['weather_fetched_at'] ?? null;
            $weatherIsStale = (bool) ($dailyForecast['weather_is_stale'] ?? false);

            $days = $dailyForecast['days'] ?? [];

            foreach ($days as $day) {
                $forecast[] = [
                    'date' =>
                        $day['date'] ?? null,

                    'weather_code' =>
                        (int) ($day['weather_code'] ?? -1),

                    'condition' =>
                        $day['weather_description']
                        ?? 'Weather data available',

                    'temperature_max' =>
                        isset($day['temperature_max_c'])
                            ? round(
                                (float) $day['temperature_max_c'],
                                1
                            )
                            : null,

                    'temperature_min' =>
                        isset($day['temperature_min_c'])
                            ? round(
                                (float) $day['temperature_min_c'],
                                1
                            )
                            : null,

                    'rain_probability' =>
                        isset(
                            $day['precipitation_probability_max']
                        )
                            ? (int) $day[
                                'precipitation_probability_max'
                            ]
                            : null,

                    'rainfall_mm' =>
                        isset($day['precipitation_sum_mm'])
                            ? round(
                                (float) $day[
                                    'precipitation_sum_mm'
                                ],
                                1
                            )
                            : null,

                    /*
                     * LiveWeatherService stores Open-Meteo wind
                     * speed in metres per second.
                     *
                     * Convert m/s to km/h for the public page.
                     */
                    'wind_speed_kph' =>
                        isset($day['wind_speed_10m_max'])
                            ? round(
                                (float) $day[
                                    'wind_speed_10m_max'
                                ] * 3.6,
                                1
                            )
                            : null,
                ];
            }

            $forecast = array_slice($forecast, 0, 7);

            if ($forecast === []) {
                throw new \RuntimeException(
                    "No 7-day forecast is stored in today's weather snapshot."
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $weatherError =
                'The 7-day weather forecast is temporarily unavailable.';
        }

        return view('public.weather', [
            'forecast' => $forecast,
            'weatherError' => $weatherError,
            'weatherFetchedAt' => $weatherFetchedAt,
            'weatherIsStale' => $weatherIsStale,
        ]);
    }

    /**
     * Public advisory listing.
     *
     * Residents do not need to sign in.
     * Advisories are shown newest to oldest by advisory date.
     */
    public function advisories(): View
    {
        $advisories = PublicAdvisory::query()
            ->newestFirst()
            ->paginate(10);

        return view('public.advisories', [
            'advisories' => $advisories,
        ]);
    }
}
