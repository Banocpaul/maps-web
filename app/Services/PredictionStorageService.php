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

            if (! is_string($barangayName) || trim($barangayName) === '') {
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

            $commonPredictionDateTime =
                $this->parseDateTime(
                    $citywideResult['prediction_datetime'] ?? null
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
            'source' => 'Manual',
            'status' => 'Completed',
            'error_message' => null,
        ]);
    }

    /**
     * Create the child flood_predictions record.
     */
    private function createFloodPrediction(
        PredictionRun $predictionRun,
        array $prediction,
        Carbon $fallbackPredictionDateTime
    ): FloodPrediction {
        $riskLevel = $prediction['predicted_risk_level'] ?? null;

        if (! in_array($riskLevel, ['Low', 'Medium', 'High'], true)) {
            throw new RuntimeException(
                'The prediction contains an unsupported risk level.'
            );
        }

        $probabilities =
            $prediction['risk_probabilities_percent'] ?? [];

        if (! is_array($probabilities)) {
            $probabilities = [];
        }

        $predictedAt =
            $this->parseDateTime(
                $prediction['prediction_datetime'] ?? null
            )
            ?? $fallbackPredictionDateTime;

        return FloodPrediction::query()->create([
            'prediction_run_id' => $predictionRun->id,

            /*
             * ml_models is currently empty, and these database fields
             * are nullable. They will be populated after the model
             * registry records are added.
             */
            'classification_model_id' => null,
            'depth_model_id' => null,
            'duration_model_id' => null,

            'predicted_risk_level' => $riskLevel,

            /*
             * FastAPI returns percentages such as 72.45.
             * The database stores decimal probabilities such as 0.724500.
             */
            'low_probability' =>
                $this->percentageToProbability(
                    $probabilities['Low'] ?? null
                ),

            'medium_probability' =>
                $this->percentageToProbability(
                    $probabilities['Medium'] ?? null
                ),

            'high_probability' =>
                $this->percentageToProbability(
                    $probabilities['High'] ?? null
                ),

            'predicted_depth_mm' =>
                $this->nullableFloat(
                    $prediction['predicted_flood_depth_mm'] ?? null
                ),

            'predicted_duration_hours' =>
                $this->nullableFloat(
                    $prediction['predicted_duration_hours'] ?? null
                ),

            'predicted_at' => $predictedAt,
            'is_alert_triggered' => false,
        ]);
    }

    /**
     * Match FastAPI barangay names with database barangay names.
     *
     * This handles differences such as:
     * - Zañiga versus Zaniga
     * - Pag-Asa versus Pag Asa
     * - Mabini-J. Rizal punctuation
     * - Wack-Wack Greenhills East versus Wack-Wack Greenhills
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
    // ML API: Hagdan Bato
    // Database: Hagdang Bato
    'hagdanbatoitaas' =>
        'hagdangbatoitaas',

    'hagdanbatolibis' =>
        'hagdangbatolibis',

    // Handles either version of the Wack-Wack name.
    'wackwackgreenhillseast' =>
        'wackwackgreenhills',

    'wackwackgreenhills' =>
        'wackwackgreenhills',

    // Capitalization is already handled by normalization.
    'harapinangbukas' =>
        'harapinangbukas',
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
            return now();
        }

        try {
            return Carbon::parse(
                $date . ' ' . $time,
                config('app.timezone')
            );
        } catch (\Throwable) {
            return now();
        }
    }

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
        } catch (\Throwable) {
            return null;
        }
    }

    private function percentageToProbability(
        mixed $value
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $percentage = max(
            0,
            min(100, (float) $value)
        );

        return round($percentage / 100, 6);
    }

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