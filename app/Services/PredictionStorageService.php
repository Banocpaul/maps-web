<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\FloodPrediction;
use App\Models\PredictionRun;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PredictionStorageService
{
    /**
     * Save one barangay prediction.
     */
    public function saveSingle(
        array $input,
        array $prediction,
        ?int $userId = null
    ): PredictionRun {
        return DB::transaction(function () use (
            $input,
            $prediction,
            $userId
        ): PredictionRun {
            $barangayName = $prediction['barangay']
                ?? $input['barangay']
                ?? null;

            if (
                ! is_string($barangayName)
                || trim($barangayName) === ''
            ) {
                throw new RuntimeException(
                    'The prediction response does not contain a barangay name.'
                );
            }

            $barangay = $this->findBarangay($barangayName);

            $predictionRun = $this->createPredictionRun(
                barangay: $barangay,
                input: $input,
                userId: $userId
            );

            $this->createFloodPrediction(
                predictionRun: $predictionRun,
                prediction: $prediction,
                fallbackPredictionDateTime:
                    $this->createRequestedAt($input)
            );

            return $predictionRun->load([
                'barangay',
                'floodPrediction',
            ]);
        });
    }

    /**
     * Save all barangay results from a citywide prediction.
     *
     * One PredictionRun is stored for each barangay because the current
     * database schema requires barangay_id on prediction_runs.
     */
    public function saveCitywide(
        array $input,
        array $citywideResult,
        ?int $userId = null
    ): Collection {
        $predictions = $citywideResult['predictions'] ?? null;

        if (! is_array($predictions) || $predictions === []) {
            throw new RuntimeException(
                'The citywide response does not contain predictions.'
            );
        }

        return DB::transaction(function () use (
            $input,
            $citywideResult,
            $predictions,
            $userId
        ): Collection {
            $storedRuns = collect();

            /*
             * Support both the old and deployed FastAPI response formats.
             */
            $commonPredictionDateTime =
                $this->parseDateTime(
                    $citywideResult['prediction_datetime']
                    ?? $citywideResult['generated_at']
                    ?? null
                )
                ?? $this->createRequestedAt($input);

            foreach ($predictions as $prediction) {
                if (! is_array($prediction)) {
                    throw new RuntimeException(
                        'A citywide prediction item has an invalid format.'
                    );
                }

                $barangayName = $prediction['barangay'] ?? null;

                if (
                    ! is_string($barangayName)
                    || trim($barangayName) === ''
                ) {
                    throw new RuntimeException(
                        'A citywide prediction is missing its barangay name.'
                    );
                }

                $barangay = $this->findBarangay($barangayName);

                $barangayInput = array_merge(
                    $input,
                    ['barangay' => $barangayName]
                );

                $predictionRun = $this->createPredictionRun(
                    barangay: $barangay,
                    input: $barangayInput,
                    userId: $userId
                );

                $this->createFloodPrediction(
                    predictionRun: $predictionRun,
                    prediction: $prediction,
                    fallbackPredictionDateTime:
                        $commonPredictionDateTime
                );

                $storedRuns->push(
                    $predictionRun->load([
                        'barangay',
                        'floodPrediction',
                    ])
                );
            }

            return $storedRuns;
        });
    }

    /**
     * Create the parent prediction_runs record.
     */
    private function createPredictionRun(
        Barangay $barangay,
        array $input,
        ?int $userId
    ): PredictionRun {
        return PredictionRun::query()->create([
            'barangay_id' => $barangay->id,
            'requested_by_user_id' => $userId,
            'weather_observation_id' => null,
            'requested_at' => $this->createRequestedAt($input),
            'input_data_json' => $input,
           'source' => 'API',
            'status' => 'Completed',
            'error_message' => null,
        ]);
    }

    /**
     * Create the child flood_predictions record.
     *
     * Supports both response formats:
     *
     * Old API:
     * - predicted_risk_level
     * - risk_probabilities_percent
     * - predicted_flood_depth_mm
     * - prediction_datetime
     *
     * Current deployed API:
     * - risk_level
     * - probabilities
     * - predicted_depth_mm
     * - generated_at on the citywide response
     */
    private function createFloodPrediction(
        PredictionRun $predictionRun,
        array $prediction,
        Carbon $fallbackPredictionDateTime
    ): FloodPrediction {
        $riskLevel = $this->normalizeRiskLevel(
            $prediction['predicted_risk_level']
            ?? $prediction['risk_level']
            ?? null
        );

        $probabilities =
            $prediction['risk_probabilities_percent']
            ?? $prediction['probabilities']
            ?? [];

        if (! is_array($probabilities)) {
            $probabilities = [];
        }

        $predictedAt =
            $this->parseDateTime(
                $prediction['prediction_datetime']
                ?? $prediction['generated_at']
                ?? null
            )
            ?? $fallbackPredictionDateTime;

        return FloodPrediction::query()->create([
            'prediction_run_id' => $predictionRun->id,

            /*
             * Model registry IDs remain nullable until ml_models records
             * are created and linked.
             */
            'classification_model_id' => null,
            'depth_model_id' => null,
            'duration_model_id' => null,

            'predicted_risk_level' => $riskLevel,

            /*
             * The current FastAPI returns decimal probabilities such as
             * 0.7245. Older API versions returned percentages such as 72.45.
             * normalizeProbability() accepts both formats.
             */
            'low_probability' =>
                $this->normalizeProbability(
                    $probabilities['Low']
                    ?? $probabilities['low']
                    ?? null
                ),

            'medium_probability' =>
                $this->normalizeProbability(
                    $probabilities['Medium']
                    ?? $probabilities['medium']
                    ?? null
                ),

            'high_probability' =>
                $this->normalizeProbability(
                    $probabilities['High']
                    ?? $probabilities['high']
                    ?? null
                ),

            'predicted_depth_mm' =>
                $this->nullableFloat(
                    $prediction['predicted_flood_depth_mm']
                    ?? $prediction['predicted_depth_mm']
                    ?? null
                ),

            'predicted_duration_hours' =>
                $this->nullableFloat(
                    $prediction['predicted_duration_hours']
                    ?? null
                ),

            'predicted_at' => $predictedAt,
            'is_alert_triggered' => false,
        ]);
    }

    /**
     * Normalize and validate a risk level.
     */
    private function normalizeRiskLevel(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException(
                'The prediction contains an unsupported risk level.'
            );
        }

        $normalized = Str::lower(trim($value));

        $riskLevels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];

        if (! array_key_exists($normalized, $riskLevels)) {
            throw new RuntimeException(
                "The prediction contains an unsupported risk level: {$value}."
            );
        }

        return $riskLevels[$normalized];
    }

    /**
     * Match FastAPI barangay names with database barangay names.
     */
    private function findBarangay(string $barangayName): Barangay
    {
        $wantedName = $this->normalizeBarangayName($barangayName);

        $barangays = Barangay::query()
            ->where('is_active', true)
            ->get();

        $barangay = $barangays->first(
            fn (Barangay $candidate): bool =>
                $this->normalizeBarangayName($candidate->name)
                === $wantedName
        );

        if (! $barangay) {
            throw new RuntimeException(
                "Barangay '{$barangayName}' was returned by the ML API "
                . 'but could not be found in the barangays table.'
            );
        }

        return $barangay;
    }

    /**
     * Normalize barangay spelling and punctuation differences.
     */
    private function normalizeBarangayName(string $name): string
    {
        $normalized = Str::ascii(
            mb_strtolower(trim($name))
        );

        $normalized = preg_replace(
            '/[^a-z0-9]+/',
            '',
            $normalized
        ) ?? '';

        $aliases = [
            'hagdanbatoitaas' =>
                'hagdangbatoitaas',

            'hagdanbatolibis' =>
                'hagdangbatolibis',

            'wackwackgreenhillseast' =>
                'wackwackgreenhills',

            'wackwackgreenhills' =>
                'wackwackgreenhills',

            'harapinangbukas' =>
                'harapinangbukas',

            'newzaniga' =>
                'newzaniga',

            'oldzaniga' =>
                'oldzaniga',

            'pagasa' =>
                'pagasa',

            'mabinijrizal' =>
                'mabinijrizal',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * Build the requested prediction date and time.
     */
    private function createRequestedAt(array $input): Carbon
    {
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));

        if ($date === '' || $time === '') {
            return now(config('app.timezone'));
        }

        try {
            return Carbon::parse(
                $date . ' ' . $time,
                config('app.timezone')
            );
        } catch (Throwable) {
            return now(config('app.timezone'));
        }
    }

    /**
     * Parse an API datetime safely.
     */
    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(
                $value,
                config('app.timezone')
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Convert either a decimal probability or a percentage into
     * a decimal value between 0 and 1.
     */
    private function normalizeProbability(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numericValue = (float) $value;

        /*
         * Values above 1 are treated as percentages.
         * Examples:
         * 0.7245 -> 0.724500
         * 72.45  -> 0.724500
         */
        if ($numericValue > 1) {
            $numericValue /= 100;
        }

        $numericValue = max(
            0,
            min(1, $numericValue)
        );

        return round($numericValue, 6);
    }

    /**
     * Convert a numeric value to float or null.
     */
    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? (float) $value
            : null;
    }
}