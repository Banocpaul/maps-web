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
     * Base URL of the FastAPI machine-learning service.
     */
    private string $baseUrl;

    /**
     * Maximum number of seconds Laravel will wait for the ML API.
     */
    private int $timeoutSeconds = 60;

    /**
     * Maximum number of seconds allowed when establishing a connection.
     */
    private int $connectTimeoutSeconds = 10;

    public function __construct()
    {
        $configuredUrl = config(
            'services.maps_ml.base_url',
            'http://127.0.0.1:8000'
        );

        $this->baseUrl = rtrim((string) $configuredUrl, '/');
    }

    /**
     * Run a flood prediction for one barangay.
     *
     * FastAPI endpoint:
     * POST /predict
     */
    public function predict(array $data): array
    {
        $payload = $this->prepareSinglePredictionPayload($data);

        return $this->sendPostRequest('/predict', $payload);
    }

    /**
     * Run flood predictions for all 27 Mandaluyong barangays.
     *
     * FastAPI endpoint:
     * POST /predict/citywide
     */
    public function predictCitywide(array $data): array
    {
        $payload = $this->prepareCitywidePredictionPayload($data);

        return $this->sendPostRequest('/predict/citywide', $payload);
    }

    /**
     * Check whether the FastAPI service and models are available.
     *
     * FastAPI endpoint:
     * GET /health
     */
    public function health(): array
    {
        return $this->sendGetRequest('/health');
    }

    /**
     * Retrieve the model features, barangays, and model metadata.
     *
     * FastAPI endpoint:
     * GET /model-info
     */
    public function modelInfo(): array
    {
        return $this->sendGetRequest('/model-info');
    }

    /**
     * Determine whether all required machine-learning models are loaded.
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
     * Prepare the exact JSON structure required by FloodPredictionRequest.
     */
    private function prepareSinglePredictionPayload(array $data): array
    {
        return [
            'barangay' => $this->requiredString(
                $data,
                'barangay'
            ),

            'date' => $this->requiredString(
                $data,
                'date'
            ),

            'time' => $this->normalizeTime(
                $this->requiredString($data, 'time')
            ),

            'cause' => $this->nullableString(
                $data['cause'] ?? null,
                'Unknown'
            ),

            'avg_rainfall_24h_mm' => $this->nullableFloat(
                $data['avg_rainfall_24h_mm'] ?? null
            ),

            'rainfall_3d_mm' => $this->nullableFloat(
                $data['rainfall_3d_mm'] ?? null
            ),

            'rainfall_7d_mm' => $this->nullableFloat(
                $data['rainfall_7d_mm'] ?? null
            ),

            'avg_tmax_c' => $this->nullableFloat(
                $data['avg_tmax_c'] ?? null
            ),

            'avg_tmin_c' => $this->nullableFloat(
                $data['avg_tmin_c'] ?? null
            ),

            'avg_temp_mean_c' => $this->nullableFloat(
                $data['avg_temp_mean_c'] ?? null
            ),

            'avg_rh_pct' => $this->nullableFloat(
                $data['avg_rh_pct'] ?? null
            ),

            'avg_wind_speed' => $this->nullableFloat(
                $data['avg_wind_speed'] ?? null
            ),

            'avg_wind_direction_deg' => $this->nullableFloat(
                $data['avg_wind_direction_deg'] ?? null
            ),

            'weather_station_count' => $this->integerValue(
                $data['weather_station_count'] ?? 1,
                1
            ),

            'weather_match_status' => $this->nullableString(
                $data['weather_match_status'] ?? null,
                'Live weather input'
            ),
        ];
    }

    /**
     * Prepare the exact JSON structure required by
     * CitywidePredictionRequest.
     *
     * The citywide request intentionally does not contain a barangay,
     * because FastAPI automatically predicts all 27 barangays.
     */
    private function prepareCitywidePredictionPayload(array $data): array
    {
        return [
            'date' => $this->requiredString(
                $data,
                'date'
            ),

            'time' => $this->normalizeTime(
                $this->requiredString($data, 'time')
            ),

            'cause' => $this->nullableString(
                $data['cause'] ?? null,
                'Unknown'
            ),

            'avg_rainfall_24h_mm' => $this->nullableFloat(
                $data['avg_rainfall_24h_mm'] ?? null
            ),

            'rainfall_3d_mm' => $this->nullableFloat(
                $data['rainfall_3d_mm'] ?? null
            ),

            'rainfall_7d_mm' => $this->nullableFloat(
                $data['rainfall_7d_mm'] ?? null
            ),

            'avg_tmax_c' => $this->nullableFloat(
                $data['avg_tmax_c'] ?? null
            ),

            'avg_tmin_c' => $this->nullableFloat(
                $data['avg_tmin_c'] ?? null
            ),

            'avg_temp_mean_c' => $this->nullableFloat(
                $data['avg_temp_mean_c'] ?? null
            ),

            'avg_rh_pct' => $this->nullableFloat(
                $data['avg_rh_pct'] ?? null
            ),

            'avg_wind_speed' => $this->nullableFloat(
                $data['avg_wind_speed'] ?? null
            ),

            'avg_wind_direction_deg' => $this->nullableFloat(
                $data['avg_wind_direction_deg'] ?? null
            ),

            'weather_station_count' => $this->integerValue(
                $data['weather_station_count'] ?? 1,
                1
            ),

            'weather_match_status' => $this->nullableString(
                $data['weather_match_status'] ?? null,
                'Live weather input'
            ),
        ];
    }

    /**
     * Send a POST request to the FastAPI service.
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
                ->post(
                    $this->baseUrl . $endpoint,
                    $payload
                );
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to the M.A.P.S. ML API at '
                . $this->baseUrl
                . '. Make sure FastAPI is running using: '
                . 'python -m uvicorn main:app --reload '
                . '--host 127.0.0.1 --port 8000',
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
     * Send a GET request to the FastAPI service.
     */
    private function sendGetRequest(string $endpoint): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->get($this->baseUrl . $endpoint);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Cannot connect to the M.A.P.S. ML API at '
                . $this->baseUrl
                . '. Make sure the FastAPI server is running.',
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
     * Validate and return the JSON response from FastAPI.
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

        $message = $this->extractApiErrorMessage(
            $responseData,
            $response->status()
        );

        throw new RuntimeException($message);
    }

    /**
     * Extract a useful message from FastAPI errors.
     *
     * This supports:
     * - 422 Pydantic validation errors
     * - 500 prediction errors
     * - ordinary HTTP errors
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

                $errorMessage = $error['msg']
                    ?? 'Invalid value.';

                $validationMessages[] = $field
                    . ': '
                    . $errorMessage;
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
     * Retrieve a required non-empty string.
     */
    private function requiredString(
        array $data,
        string $key
    ): string {
        $value = $data[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            throw new RuntimeException(
                "The required prediction field '{$key}' is missing."
            );
        }

        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw new RuntimeException(
                "The required prediction field '{$key}' is empty."
            );
        }

        return $normalizedValue;
    }

    /**
     * Convert a blank string to the supplied default value.
     */
    private function nullableString(
        mixed $value,
        string $default
    ): string {
        if ($value === null) {
            return $default;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue !== ''
            ? $normalizedValue
            : $default;
    }

    /**
     * Convert a numeric input into a float.
     *
     * Empty inputs are returned as null because the FastAPI schema permits
     * nullable weather measurements.
     */
    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Convert a numeric value into an integer.
     */
    private function integerValue(
        mixed $value,
        int $default
    ): int {
        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Convert HTML time inputs such as 20:30 into the format accepted by
     * FastAPI, such as 20:30:00.
     */
    private function normalizeTime(string $time): string
    {
        $normalizedTime = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $normalizedTime) === 1) {
            return $normalizedTime . ':00';
        }

        return $normalizedTime;
    }
}