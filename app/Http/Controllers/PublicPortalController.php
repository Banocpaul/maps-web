<?php

namespace App\Http\Controllers;

use App\Services\LiveWeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use RuntimeException;
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
     */
    public function weather(): View
    {
        $forecast = [];
        $weatherError = null;

        try {
            $forecast = Cache::remember(
                'public_weather_forecast_mandaluyong_v1',
                now()->addMinutes(30),
                function (): array {
                    return $this->getSevenDayForecast();
                }
            );
        } catch (Throwable $exception) {
            report($exception);

            $weatherError = 'The 7-day weather forecast is temporarily unavailable.';
        }

        return view('public.weather', [
            'forecast' => $forecast,
            'weatherError' => $weatherError,
        ]);
    }

    /**
     * Retrieve exactly 7 days of forecast data from Open-Meteo.
     */
    private function getSevenDayForecast(): array
    {
        /*
         * Approximate center coordinates of Mandaluyong City.
         */
        $latitude = 14.5794;
        $longitude = 121.0359;

        $response = Http::timeout(10)
            ->retry(2, 250)
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,

                'daily' => implode(',', [
                    'weather_code',
                    'temperature_2m_max',
                    'temperature_2m_min',
                    'precipitation_probability_max',
                    'precipitation_sum',
                    'wind_speed_10m_max',
                ]),

                'temperature_unit' => 'celsius',
                'wind_speed_unit' => 'kmh',
                'precipitation_unit' => 'mm',

                'timezone' => 'Asia/Manila',

                'forecast_days' => 7,
            ]);

        $response->throw();

        $data = $response->json();

        if (
            !isset($data['daily']) ||
            !isset($data['daily']['time']) ||
            !is_array($data['daily']['time'])
        ) {
            throw new RuntimeException(
                'Open-Meteo returned an invalid forecast response.'
            );
        }

        $daily = $data['daily'];

        $forecast = [];

        foreach ($daily['time'] as $index => $date) {
            $weatherCode = (int) ($daily['weather_code'][$index] ?? -1);

            $forecast[] = [
                'date' => $date,

                'weather_code' => $weatherCode,

                'condition' => $this->weatherCondition(
                    $weatherCode
                ),

                'temperature_max' => isset(
                    $daily['temperature_2m_max'][$index]
                )
                    ? round(
                        (float) $daily['temperature_2m_max'][$index],
                        1
                    )
                    : null,

                'temperature_min' => isset(
                    $daily['temperature_2m_min'][$index]
                )
                    ? round(
                        (float) $daily['temperature_2m_min'][$index],
                        1
                    )
                    : null,

                'rain_probability' => isset(
                    $daily['precipitation_probability_max'][$index]
                )
                    ? (int) $daily['precipitation_probability_max'][$index]
                    : null,

                'rainfall_mm' => isset(
                    $daily['precipitation_sum'][$index]
                )
                    ? round(
                        (float) $daily['precipitation_sum'][$index],
                        1
                    )
                    : null,

                'wind_speed_kph' => isset(
                    $daily['wind_speed_10m_max'][$index]
                )
                    ? round(
                        (float) $daily['wind_speed_10m_max'][$index],
                        1
                    )
                    : null,
            ];
        }

        /*
         * Extra safeguard:
         * never allow more than 7 forecast days.
         */
        return array_slice($forecast, 0, 7);
    }

    /**
     * Convert Open-Meteo WMO weather codes into
     * readable weather conditions.
     */
    private function weatherCondition(int $code): string
    {
        return match ($code) {
            0 => 'Clear Sky',

            1 => 'Mainly Clear',

            2 => 'Partly Cloudy',

            3 => 'Overcast',

            45,
            48 => 'Foggy',

            51,
            53,
            55 => 'Drizzle',

            56,
            57 => 'Freezing Drizzle',

            61 => 'Light Rain',

            63 => 'Moderate Rain',

            65 => 'Heavy Rain',

            66,
            67 => 'Freezing Rain',

            71,
            73,
            75,
            77 => 'Snow',

            80 => 'Light Rain Showers',

            81 => 'Moderate Rain Showers',

            82 => 'Heavy Rain Showers',

            85,
            86 => 'Snow Showers',

            95 => 'Thunderstorm',

            96,
            99 => 'Thunderstorm with Hail',

            default => 'Unknown Weather',
        };
    }
}