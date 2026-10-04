<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FloodPredictionService
{
    private string $baseUrl;
    private int $timeoutSeconds = 120;
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
     * Single-barangay prediction is intentionally disabled.
     */
    public function predict(array $data): array
    {
        throw new RuntimeException(
            'Single-barangay prediction is not available in the deployed '
            . 'M.A.P.S. ML API. Use predictCitywide() instead.'
        );
    }

    /**
     * Run a citywide prediction for 24, 48 or 72 hours.
     */
    public function predictCitywide(array $data): array
    {
        $payload = $this->prepareCitywidePredictionPayload($data);

        return $this->sendPostRequest('/predict/citywide', $payload);
    }

    public function liveWeather(): array
    {
        return $this->sendGetRequest('/weather/live');
    }

    public function health(): array
    {
        return $this->sendGetRequest('/health');
    }

    public function isAvailable(): bool
    {
        try {
            $health = $this->health();

            $severityModelLoaded =
                ($health['flood_severity_model_loaded'] ?? false) === true;

            return ($health['status'] ?? null) === 'healthy'
                && $severityModelLoaded;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Prepare the JSON structure required by FastAPI.
     */
    private function prepareCitywidePredictionPayload(array $data): array
    {
        $barangays = $data['barangays'] ?? $data;

        if (! is_array($barangays) || $barangays === []) {
            throw new RuntimeException(
                'No barangay profiles were supplied for citywide prediction.'
            );
        }

        $forecastHours = (int) ($data['forecast_hours'] ?? 24);

        if (! in_array($forecastHours, [24, 48, 72], true)) {
            throw new RuntimeException(
                'Forecast window must be 24, 48, or 72 hours.'
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

                'waterway_type' => $this->stringFromAliases(
                    $barangay,
                    ['waterway_type'],
                    'Unknown'
                ),

                'previous_floods_30d' => $this->integerFromAliases(
                    $barangay,
                    ['previous_floods_30d'],
                    0
                ),

                'days_since_previous_flood' => $this->floatFromAliases(
                    $barangay,
                    ['days_since_previous_flood'],
                    999.0
                ),
            ];
        }

        $payload = [
            'forecast_hours' => $forecastHours,
            'barangays' => $normalizedBarangays,
        ];

        /*
         * The web application owns the daily Open-Meteo snapshot.
         * FastAPI receives that same snapshot instead of requesting
         * Open-Meteo again.
         */
        $weatherContext = $data['weather_context'] ?? null;

        if (
            is_array($weatherContext)
            && $weatherContext !== []
        ) {
            $payload['weather_context'] = $weatherContext;
        }

        $simulation = $data['simulation'] ?? null;

        if ($simulation !== null) {
            if (! is_array($simulation)) {
                throw new RuntimeException(
                    'The rainfall simulation payload is invalid.'
                );
            }

            foreach ([
                'rainfall_24h_mm',
                'rainfall_3d_mm',
                'rainfall_7d_mm',
            ] as $field) {
                if (
                    ! array_key_exists($field, $simulation)
                    || ! is_numeric($simulation[$field])
                ) {
                    throw new RuntimeException(
                        "Missing or invalid simulation value: {$field}."
                    );
                }
            }

            $payload['simulation'] = [
                'rainfall_24h_mm' =>
                    (float) $simulation['rainfall_24h_mm'],
                'rainfall_3d_mm' =>
                    (float) $simulation['rainfall_3d_mm'],
                'rainfall_7d_mm' =>
                    (float) $simulation['rainfall_7d_mm'],
            ];
        }

        return $payload;
    }

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
