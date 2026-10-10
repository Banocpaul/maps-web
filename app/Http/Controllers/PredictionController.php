<?php

namespace App\Http\Controllers;

use App\Services\FloodPredictionService;
use App\Services\LiveWeatherService;
use App\Services\PredictionStorageService;
use App\Services\PredictionHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PredictionController extends Controller
{
    private const ALLOWED_FORECAST_HOURS = [24, 48, 72];

    public function __construct(
        private readonly FloodPredictionService $floodPredictionService,
        private readonly LiveWeatherService $liveWeatherService,
        private readonly PredictionStorageService $predictionStorageService,
        private readonly PredictionHistoryService $predictionHistoryService
    ) {
    }

    /**
     * Display current weather and the citywide prediction page.
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
                    'selectedForecastHours' => 24,
                ],
                $weatherContext
            )
        );
    }

    /**
     * Redirect the old manual prediction route.
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
     * Run predictions for all Mandaluyong barangays.
     */
    public function citywide(Request $request): View|RedirectResponse
    {
        $forecastHours = $this->validatedForecastHours($request);
        $execution = null;

        try {
            $execution = $this->predictionHistoryService->start($request->user(), $forecastHours);
            $liveWeather = $this->liveWeatherService
                ->getCurrentWeather();

            $barangays = $this->getBarangayProfiles();

            $predictionData = array_merge(
                $this->prepareWeatherStorageData($liveWeather),
                [
                    'forecast_hours' => $forecastHours,
                    'barangays' => $barangays->all(),
                ]
            );

            Log::info('First barangay payload', [
                'forecast_hours' => $forecastHours,
                'barangay' => $predictionData['barangays'][0] ?? null,
            ]);

            /*
             * Reuse Laravel's daily Open-Meteo snapshot for ML inference.
             * This prevents FastAPI from making a second Open-Meteo call.
             * Keep the full weather context out of prediction storage to
             * avoid duplicating a large weather payload for every barangay.
             */
            $mlPredictionData = $predictionData;
            $mlPredictionData['weather_context'] = $liveWeather;
            $this->predictionHistoryService->captureInput($execution, $mlPredictionData);

            $citywideResult = $this->floodPredictionService
                ->predictCitywide($mlPredictionData);
            $this->predictionHistoryService->complete($execution, $citywideResult);

            /*
             * Store the selected forecast summary in input_data_json.
             * Existing database columns remain unchanged.
             */
            $predictionData['selected_forecast'] =
                $citywideResult['selected_forecast'] ?? null;

            // Preserve the existing per-barangay records alongside the complete snapshot.
            // A legacy schema/format error must not discard a successfully saved forecast.
            $storedPredictionRuns = collect();
            try {
                $storedPredictionRuns = $this->predictionStorageService->saveCitywide(
                    input: $predictionData,
                    citywideResult: $citywideResult,
                    userId: auth()->id()
                );
            } catch (Throwable $storageError) {
                Log::warning('Legacy prediction records could not be saved; full history is retained.', [
                    'execution_id' => $execution->id, 'message' => $storageError->getMessage(),
                ]);
            }

            Log::info('Automated citywide flood prediction saved.', [
                'barangay_profile_count' => $barangays->count(),
                'saved_prediction_count' =>
                    $storedPredictionRuns->count(),
                'forecast_hours' => $forecastHours,
                'forecast_start' =>
                    $citywideResult['selected_forecast']['start']
                    ?? $liveWeather['forecast_start']
                    ?? null,
                'forecast_end' =>
                    $citywideResult['selected_forecast']['end']
                    ?? $liveWeather['forecast_end']
                    ?? null,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('prediction.history.show', $execution)
                ->with('success', 'Prediction results saved.');
        } catch (RuntimeException $exception) {
            $this->predictionHistoryService->fail($execution, $exception);
            Log::error('Automated citywide prediction failed.', [
                'message' => $exception->getMessage(),
                'forecast_hours' => $forecastHours,
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('prediction.index')
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        } catch (Throwable $exception) {
            $this->predictionHistoryService->fail($execution, $exception);
            Log::error(
                'Unexpected automated citywide prediction error.',
                [
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'forecast_hours' => $forecastHours,
                    'user_id' => auth()->id(),
                ]
            );

            return redirect()
                ->route('prediction.index')
                ->withInput()
                ->with(
                    'error',
                    'The citywide prediction could not be completed: '
                    . $exception->getMessage()
                );
        }
    }

    /**
     * Validate the requested forecast window.
     */
    private function validatedForecastHours(Request $request): int
    {
        $validated = $request->validate([
            'forecast_hours' => [
                'required',
                'integer',
                'in:24,48,72',
            ],
        ]);

        $hours = (int) $validated['forecast_hours'];

        if (! in_array(
            $hours,
            self::ALLOWED_FORECAST_HOURS,
            true
        )) {
            throw new RuntimeException(
                'Unsupported forecast window.'
            );
        }

        return $hours;
    }

    /**
     * Load and normalize barangay profiles from the database.
     */
    private function getBarangayProfiles(): Collection
    {
        if (! DB::getSchemaBuilder()->hasTable('barangays')) {
            throw new RuntimeException(
                'The barangays database table was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DATABASE CONNECTION DIAGNOSTICS
        |--------------------------------------------------------------------------
        */

        Log::info('Active database connection', [
            'database' =>
                DB::connection()->getDatabaseName(),
            'configured_host' =>
                config('database.connections.mysql.host'),
            'configured_port' =>
                config('database.connections.mysql.port'),
            'configured_database' =>
                config('database.connections.mysql.database'),
            'configured_username' =>
                config('database.connections.mysql.username'),
            'database_url_present' =>
                ! empty(config('database.connections.mysql.url')),
        ]);

        /*
         * Directly verify Addition Hills.
         */
        $directRow = DB::selectOne(
            '
            SELECT
                id,
                name,
                elevation_m,
                nearest_waterway,
                distance_to_waterway_m,
                drainage_index,
                impervious_surface_ratio,
                population_density_per_km2,
                historical_flood_count_5y
            FROM barangays
            WHERE id = 1
            LIMIT 1
            '
        );

        Log::info(
            'Direct SQL Result',
            $directRow !== null
                ? (array) $directRow
                : ['row' => null]
        );

        /*
         * Retrieve all active barangays.
         */
        $rows = DB::table('barangays')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        Log::info('Raw first barangay from database', [
            'row' => $rows->isNotEmpty()
                ? (array) $rows->first()
                : null,
        ]);

        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'No active barangay records were found.'
            );
        }

        $profiles = $rows->map(
            function (object $row): array {
                $data = (array) $row;

                return [
                    'barangay_id' =>
                        $this->requiredIntegerFromAliases(
                            $data,
                            [
                                'barangay_id',
                                'id',
                            ]
                        ),

                    'barangay' =>
                        $this->requiredStringFromAliases(
                            $data,
                            [
                                'barangay',
                                'name',
                                'barangay_name',
                            ]
                        ),

                    'nearest_waterway' =>
                        $this->stringFromAliases(
                            $data,
                            [
                                'nearest_waterway',
                                'waterway',
                            ],
                            'Unknown'
                        ),

                    'elevation_m' =>
                        $this->floatFromAliases(
                            $data,
                            [
                                'elevation_m',
                                'elevation',
                            ],
                            0.0
                        ),

                    'distance_to_waterway_m' =>
                        $this->floatFromAliases(
                            $data,
                            [
                                'distance_to_waterway_m',
                                'distance_to_waterway',
                                'waterway_distance_m',
                            ],
                            0.0
                        ),

                    'drainage_index' =>
                        $this->floatFromAliases(
                            $data,
                            [
                                'drainage_index',
                            ],
                            0.0
                        ),

                    'impervious_surface_ratio' =>
                        $this->floatFromAliases(
                            $data,
                            [
                                'impervious_surface_ratio',
                                'impervious_ratio',
                            ],
                            0.0
                        ),

                    'population_density_per_km2' =>
                        $this->floatFromAliases(
                            $data,
                            [
                                'population_density_per_km2',
                                'population_density',
                            ],
                            0.0
                        ),

                    'historical_flood_count_5y' =>
                        $this->integerFromAliases(
                            $data,
                            [
                                'historical_flood_count_5y',
                                'historical_flood_count',
                                'flood_count_5y',
                            ],
                            0
                        ),

                    /*
                     * V2 fields remain optional in FastAPI.
                     * If these columns exist later, aliases can be added here.
                     */
                    'waterway_type' =>
                        $this->stringFromAliases(
                            $data,
                            ['waterway_type'],
                            'Unknown'
                        ),

                    'previous_floods_30d' =>
                        $this->integerFromAliases(
                            $data,
                            ['previous_floods_30d'],
                            0
                        ),

                    'days_since_previous_flood' =>
                        $this->floatFromAliases(
                            $data,
                            ['days_since_previous_flood'],
                            999.0
                        ),
                ];
            }
        );

        if ($profiles->count() !== 27) {
            Log::warning(
                'Unexpected barangay profile count.',
                [
                    'expected' => 27,
                    'actual' => $profiles->count(),
                ]
            );
        }

        return $profiles;
    }

    /**
     * Prepare weather values used for prediction storage.
     *
     * The legacy database field avg_rainfall_24h_mm keeps its original
     * 24-hour meaning. The selected 24/48/72 horizon is stored separately
     * in input_data_json through forecast_hours and selected_forecast.
     */
    private function prepareWeatherStorageData(
        array $liveWeather
    ): array {
        return [
            'date' =>
                $liveWeather['date']
                ?? now('Asia/Manila')->format('Y-m-d'),

            'time' =>
                $liveWeather['time']
                ?? now('Asia/Manila')->format('H:i:s'),

            'cause' =>
                $liveWeather['suggested_cause']
                ?? 'Forecast Weather Conditions',

            'avg_rainfall_24h_mm' =>
                $liveWeather['forecast_rainfall_24h_mm']
                ?? 0,

            'rainfall_3d_mm' =>
                $liveWeather['rainfall_3d_mm']
                ?? 0,

            'rainfall_7d_mm' =>
                $liveWeather['rainfall_7d_mm']
                ?? 0,

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
                $liveWeather['weather_station_count']
                ?? 1,

            'weather_match_status' =>
                'Open-Meteo current weather and selected forecast window',
        ];
    }

    /**
     * Retrieve live weather without breaking the page during failures.
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

    /**
     * Get a required integer value from possible column names.
     */
    private function requiredIntegerFromAliases(
        array $data,
        array $aliases
    ): int {
        foreach ($aliases as $alias) {
            if (
                array_key_exists($alias, $data)
                && is_numeric($data[$alias])
            ) {
                return (int) $data[$alias];
            }
        }

        throw new RuntimeException(
            'A barangay record is missing its numeric ID.'
        );
    }

    /**
     * Get a required string value from possible column names.
     */
    private function requiredStringFromAliases(
        array $data,
        array $aliases
    ): string {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $data)) {
                continue;
            }

            if ($data[$alias] === null) {
                continue;
            }

            $value = trim((string) $data[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        throw new RuntimeException(
            'A barangay record is missing its barangay name.'
        );
    }

    /**
     * Get an optional string value.
     */
    private function stringFromAliases(
        array $data,
        array $aliases,
        string $default
    ): string {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $data)) {
                continue;
            }

            if ($data[$alias] === null) {
                continue;
            }

            $value = trim((string) $data[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get an optional float value.
     */
    private function floatFromAliases(
        array $data,
        array $aliases,
        float $default
    ): float {
        foreach ($aliases as $alias) {
            if (
                array_key_exists($alias, $data)
                && $data[$alias] !== null
                && $data[$alias] !== ''
                && is_numeric($data[$alias])
            ) {
                return (float) $data[$alias];
            }
        }

        return $default;
    }

    /**
     * Get an optional integer value.
     */
    private function integerFromAliases(
        array $data,
        array $aliases,
        int $default
    ): int {
        foreach ($aliases as $alias) {
            if (
                array_key_exists($alias, $data)
                && $data[$alias] !== null
                && $data[$alias] !== ''
                && is_numeric($data[$alias])
            ) {
                return (int) $data[$alias];
            }
        }

        return $default;
    }
}
