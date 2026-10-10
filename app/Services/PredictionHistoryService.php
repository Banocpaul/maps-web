<?php

namespace App\Services;

use App\Models\PredictionExecution;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PredictionHistoryService
{
    public function start(User $user, int $hours, string $kind = 'Forecast'): PredictionExecution
    {
        return PredictionExecution::create([
            'requested_by_user_id' => $user->id,
            'requested_by_name' => $user->full_name ?: ($user->name ?: 'Staff #'.$user->id),
            'kind' => $kind, 'forecast_hours' => $hours,
            'status' => 'Running', 'requested_at' => now(),
        ]);
    }

    public function captureInput(PredictionExecution $execution, array $input): void
    {
        $execution->update(['input_snapshot' => $input]);
    }

    public function complete(PredictionExecution $execution, array $result): void
    {
        if (! is_array($result['predictions'] ?? null) || $result['predictions'] === []) {
            throw new RuntimeException('The ML API returned no prediction results.');
        }
        foreach ($result['predictions'] as $prediction) {
            if (! is_array($prediction) || ! is_string($prediction['barangay'] ?? null) || trim($prediction['barangay']) === '') {
                throw new RuntimeException('The ML API returned an invalid barangay result.');
            }
        }

        $execution->update([
            'result_snapshot' => $result, 'status' => 'Completed', 'completed_at' => now(),
        ]);
    }

    public function fail(?PredictionExecution $execution, Throwable $error): void
    {
        if (! $execution || $execution->status !== 'Running') {
            return;
        }

        try {
            $execution->update([
                'status' => 'Failed', 'completed_at' => now(),
                'error_message' => mb_substr($error->getMessage(), 0, 2000),
            ]);
        } catch (Throwable $storageError) {
            Log::error('Could not record failed prediction attempt.', [
                'execution_id' => $execution->id, 'message' => $storageError->getMessage(),
            ]);
        }
    }
}
