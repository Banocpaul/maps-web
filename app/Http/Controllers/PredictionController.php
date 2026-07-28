<?php

namespace App\Http\Controllers;

use App\Services\FloodPredictionService;
use App\Services\LiveWeatherService;
use App\Services\PredictionStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PredictionController extends Controller
{
    public function __construct(
        private readonly FloodPredictionService $floodPredictionService,
        private readonly LiveWeatherService $liveWeatherService,
        private readonly PredictionStorageService $predictionStorageService
    ) {
    }

    /**
     * Display current Mandaluyong weather and the citywide prediction page.
     */
    public function index(): View
    {
        $apiAvailable = $this->floodPredictionService->isAvailable();
        $weatherContext = $this->getLiveWeatherContext();

        return view(
            'prediction.index',
            array_merge(
                [
                    'apiAvailable' => $apiAvailable,
                    'citywideResult' => null,
                ],
                $weatherContext
            )
        );
    }

    /**
     * Keep the former single-prediction route from causing an error.
     *
     * Manual barangay prediction has been removed from the interface.
     */
    public function run(Request $request): RedirectResponse
    {
        return redirect()
            ->route('prediction.index')
            ->with(
                'error',
                'Manual barangay prediction has been removed. '
                . 'Use Run Citywide Prediction instead.'
            );
    }

    /**
     * Automatically run predictions for all Mandaluyong barangays using
     * current weather and the upcoming 24-hour weather forecast.
     */
    public function citywide(Request $request): View|RedirectResponse
    {
        try {
            /*
             * Weather information is collected automatically.
             * No prediction input is accepted from the browser.
             */
            $liveWeather = $this->liveWeatherService
                ->getCurrentWeather();

            $predictionData = $this->prepareCitywidePredictionData(
                $liveWeather
            );

            $citywideResult = $this->floodPredictionService
                ->predictCitywide($predictionData);

            $storedPredictionRuns =
                $this->predictionStorageService->saveCitywide(
                    input: $predictionData,
                    citywideResult: $citywideResult,
                    userId: auth()->id()
                );

            Log::info('Automated citywide flood prediction saved.', [
                'saved_prediction_count' =>
                    $storedPredictionRuns->count(),

                'forecast_start' =>
                    $liveWeather['forecast_start'] ?? null,

                'forecast_end' =>
                    $liveWeather['forecast_end'] ?? null,

                'user_id' => auth()->id(),
            ]);

            return view('prediction.index', [
                'apiAvailable' => true,
                'citywideResult' => $citywideResult,
                'liveWeather' => $liveWeather,
                'weatherAvailable' => true,
                'weatherError' => null,
            ]);
        } catch (RuntimeException $exception) {
            Log::error('Automated citywide prediction failed.', [
                'message' => $exception->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with(
                'error',
                $exception->getMessage()
            );
        } catch (Throwable $exception) {
            Log::error(
                'Unexpected automated citywide prediction error.',
                [
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'user_id' => auth()->id(),
                ]
            );

            return back()->with(
                'error',
                'The citywide prediction could not be completed. '
                . 'Check the weather service and ML API, then try again.'
            );
        }
    }

    /**
     * Build the ML API payload from automatic weather information.
     */
    private function prepareCitywidePredictionData(
        array $liveWeather
    ): array {
        return [
            'date' =>
                $liveWeather['date']
                ?? now('Asia/Manila')->format('Y-m-d'),

            'time' =>
                $liveWeather['time']
                ?? now('Asia/Manila')->format('H:i'),

            /*
             * The model requires a cause value. This automated label
             * identifies that the prediction uses forecast conditions.
             */
            'cause' =>
                $liveWeather['suggested_cause']
                ?? 'Forecast Weather Conditions',

            /*
             * Upcoming precipitation forecast for the next 24 hours.
             */
            'avg_rainfall_24h_mm' =>
                $liveWeather['forecast_rainfall_24h_mm'] ?? 0,

            /*
             * Recent rainfall accumulation provides antecedent soil and
             * drainage conditions.
             */
            'rainfall_3d_mm' =>
                $liveWeather['rainfall_3d_mm'] ?? 0,

            'rainfall_7d_mm' =>
                $liveWeather['rainfall_7d_mm'] ?? 0,

            'avg_tmax_c' =>
                $liveWeather['forecast_tmax_c']
                ?? $liveWeather['avg_tmax_c']
                ?? null,

            'avg_tmin_c' =>
                $liveWeather['forecast_tmin_c']
                ?? $liveWeather['avg_tmin_c']
                ?? null,

            'avg_temp_mean_c' =>
                $liveWeather['forecast_mean_temp_c']
                ?? $liveWeather['avg_temp_mean_c']
                ?? null,

            'avg_rh_pct' =>
                $liveWeather['forecast_avg_humidity_pct']
                ?? $liveWeather['avg_rh_pct']
                ?? null,

            'avg_wind_speed' =>
                $liveWeather['forecast_avg_wind_speed']
                ?? $liveWeather['avg_wind_speed']
                ?? null,

            'avg_wind_direction_deg' =>
                $liveWeather['avg_wind_direction_deg']
                ?? null,

            'weather_station_count' =>
                $liveWeather['weather_station_count'] ?? 1,

            'weather_match_status' =>
                'Open-Meteo current weather and 24-hour forecast',
        ];
    }

    /**
     * Retrieve weather data while allowing the page to remain visible
     * when the external weather provider is unavailable.
     */
    private function getLiveWeatherContext(): array
    {
        try {
            $liveWeather = $this->liveWeatherService
                ->getCurrentWeather();

            return [
                'liveWeather' => $liveWeather,
                'weatherAvailable' => true,
                'weatherError' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning(
                'Could not retrieve Mandaluyong weather data.',
                [
                    'message' => $exception->getMessage(),
                    'user_id' => auth()->id(),
                ]
            );

            return [
                'liveWeather' => [],
                'weatherAvailable' => false,
                'weatherError' => $exception->getMessage(),
            ];
        }
    }
}