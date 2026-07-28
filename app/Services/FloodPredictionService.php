<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FloodPredictionService
{
    /**
     * Base URL of the deployed FastAPI machine-learning service.
     */
    private string $baseUrl;

    /**
     * Maximum number of seconds Laravel will wait for the ML API.
     */
    private int $timeoutSeconds = 120;

    /**
     * Maximum number of seconds allowed when establishing a connection.
     */
    private int $connectTimeoutSeconds = 15;

    public function __construct()
    {
        $configuredUrl = config(
            'services.maps_ml.base_url',
            'http://127.0.0.1:8000'
        );

        $this->baseUrl = rtrim((string) $configuredUrl, '/');
    }

    /**
     * The currently deployed FastAPI version does not expose POST /predict.
     *
     * This method is retained only to prevent an undefined-method error.
     */
    public function predict(array $data): array
    {
        throw new RuntimeException(
            'Single-barangay prediction is not available in the deployed '
            . 'M.A.P.S. ML API. Use predictCitywide() instead.'
        );
    }

    /**
     * Run flood predictions for the supplied Mandaluyong barangays.
     *
     * Expected input:
     *
     * [
     *     'barangays' => [
     *         [
     *             'barangay_id' => 1,
     *             'barangay' => 'Addition Hills',
     *             'nearest_waterway' => 'Maytunas Creek',
     *             'elevation_m' => 25,
     *             'distance_to_waterway_m' => 300,
     *             'drainage_index' => 0.70,
     *             'impervious_surface_ratio' => 0.80,
     *             'population_density_per_km2' => 25000,
     *             'historical_flood_count_5y' => 10,
     *         ],
     *     ],
     * ]
     */
    public function predictCitywide(array $data): array
    {
        $payload = $this->prepareCitywidePredictionPayload($data);

        return $this->sendPostRequest('/predict/citywide', $payload);
    }

    /**
     * Get live weather directly from FastAPI.
     */
    public function liveWeather(): array
    {
        return $this->sendGetRequest('/weather/live');
    }

    /**
     * Check whether the FastAPI service and required models are available.
     */
    public function health(): array
    {
        return $this->sendGetRequest('/health');
    }

    /**
     * Determine whether the deployed ML API is ready.
     */
    public function isAvailable(): bool
    {
        try {
            $health = $this->health();

            return ($health['status'] ?? null) === 'healthy'
                && ($health['risk_model_loaded'] ?? false) === true
                && ($health['depth_model_loaded'] ?? false) === true
                && ($health['duration_model_loaded'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Prepare the exact JSON structure required by the deployed
     * FastAPI CitywidePredictionRequest schema.
     */
    private function prepareCitywidePredictionPayload(array $data): array
    {
        $barangays = $data['barangays'] ?? $data;

        if (! is_array($barangays) || $barangays === []) {
            throw new RuntimeException(
                'No barangay profiles were supplied for citywide prediction.'
            );
        }

        $normalizedBarangays = [];

        foreach ($barangays as $index => $barangay) {
            if (is_object($barangay)) {
                if (method_exists($barangay, 'toArray')) {
                    $barangay = $barangay->toArray();
                } else {
                    $barangay = (array) $barangay;
                }
            }

            if (! is_array($barangay)) {
                throw new RuntimeException(
                    'Invalid barangay profile at index ' . $index . '.'
                );
            }

            $normalizedBarangays[] = [
                'barangay_id' => $this->requiredInteger(
                    $barangay,
                    ['barangay_id', 'id']
                ),

                'barangay' => $this->requiredStringFromAliases(
                    $barangay,
                    ['barangay', 'name', 'barangay_name']
                ),

                'nearest_waterway' => $this->stringFromAliases(
                    $barangay,
                    ['nearest_waterway', 'waterway'],
                    'Unknown'
                ),

                'elevation_m' => $this->floatFromAliases(
                    $barangay,
                    ['elevation_m', 'elevation'],
                    0.0
                ),

                'distance_to_waterway_m' => $this->floatFromAliases(
                    $barangay,
                    [
                        'distance_to_waterway_m',
                        'distance_to_waterway',
                        'waterway_distance_m',
                    ],
                    0.0
                ),

                'drainage_index' => $this->floatFromAliases(
                    $barangay,
                    ['drainage_index'],
                    0.0
                ),

                'impervious_surface_ratio' => $this->floatFromAliases(
                    $barangay,
                    [
                        'impervious_surface_ratio',
                        'impervious_ratio',
                    ],
                    0.0
                ),

                'population_density_per_km2' => $this->floatFromAliases(
                    $barangay,
                    [
                        'population_density_per_km2',
                        'population_density',
                    ],
                    0.0
                ),

                'historical_flood_count_5y' => $this->integerFromAliases(
                    $barangay,
                    [
                        'historical_flood_count_5y',
                        'historical_flood_count',
                        'flood_count_5y',
                    ],
                    0
                ),
            ];
        }

        return [
            'barangays' => $normalizedBarangays,
        ];
    }

    /**
     * Send a POST request to FastAPI.
     */
    private function sendPostRequest(
        string $endpoint,
        array $payload
    ): array {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(
                    2,
                    1000,
                    throw: false
                )
                ->post(
                    $this->baseUrl . $endpoint,
                    $payload
                );
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to the M.A.P.S. ML API at '
                . $this->baseUrl
                . '. The Render service may still be waking up.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'An unexpected error occurred while contacting the '
                . 'M.A.P.S. ML API: '
                . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->processResponse($response);
    }

    /**
     * Send a GET request to FastAPI.
     */
    private function sendGetRequest(string $endpoint): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(
                    2,
                    1000,
                    throw: false
                )
                ->get($this->baseUrl . $endpoint);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to the M.A.P.S. ML API at '
                . $this->baseUrl
                . '. The Render service may still be waking up.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'An unexpected error occurred while contacting the '
                . 'M.A.P.S. ML API: '
                . $exception->getMessage(),
                previous: $exception
            );
        }

        return $this->processResponse($response);
    }

    /**
     * Validate and return a JSON response from FastAPI.
     */
    private function processResponse(Response $response): array
    {
        $responseData = $response->json();

        if ($response->successful()) {
            if (! is_array($responseData)) {
                throw new RuntimeException(
                    'The M.A.P.S. ML API returned an invalid JSON response.'
                );
            }

            return $responseData;
        }

        throw new RuntimeException(
            $this->extractApiErrorMessage(
                $responseData,
                $response->status()
            )
        );
    }

    /**
     * Extract a readable FastAPI error.
     */
    private function extractApiErrorMessage(
        mixed $responseData,
        int $statusCode
    ): string {
        if (! is_array($responseData)) {
            return 'The M.A.P.S. ML API request failed with HTTP status '
                . $statusCode
                . '.';
        }

        $detail = $responseData['detail'] ?? null;

        if (is_string($detail) && $detail !== '') {
            return 'M.A.P.S. ML API error: ' . $detail;
        }

        if (is_array($detail)) {
            $validationMessages = [];

            foreach ($detail as $error) {
                if (! is_array($error)) {
                    continue;
                }

                $location = $error['loc'] ?? [];
                $field = is_array($location)
                    ? implode('.', $location)
                    : 'request';

                $errorMessage = $error['msg'] ?? 'Invalid value.';

                $validationMessages[] = $field . ': ' . $errorMessage;
            }

            if ($validationMessages !== []) {
                return 'The ML API rejected the submitted information: '
                    . implode(' | ', $validationMessages);
            }
        }

        return 'The M.A.P.S. ML API request failed with HTTP status '
            . $statusCode
            . '.';
    }

    /**
     * Get a required non-empty string using possible column aliases.
     */
    private function requiredStringFromAliases(
        array $data,
        array $aliases
    ): string {
        foreach ($aliases as $alias) {
            if (! array_key_exists($alias, $data)) {
                continue;
            }

            $value = trim((string) $data[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        throw new RuntimeException(
            'Missing required barangay field: '
            . implode(' or ', $aliases)
            . '.'
        );
    }

    /**
     * Get a string using possible column aliases.
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

            $value = trim((string) $data[$alias]);

            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Get a required integer using possible column aliases.
     */
    private function requiredInteger(
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
            'Missing required barangay ID field: '
            . implode(' or ', $aliases)
            . '.'
        );
    }

    /**
     * Get a float using possible column aliases.
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
     * Get an integer using possible column aliases.
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