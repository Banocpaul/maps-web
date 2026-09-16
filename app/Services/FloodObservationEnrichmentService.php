<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
use App\Models\FloodTrainingRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

class FloodObservationEnrichmentService
{
    public function enrich(FloodTrainingRecord $record): FloodTrainingRecord
    {
        if ($record->review_status === 'Approved') {
            return $record;
        }

        try {
            $barangay = Barangay::query()
                ->active()
                ->where('name', $record->barangay)
                ->first();

            if ($barangay === null) {
                return $this->markPending($record, 'Barangay predictor profile was not found.');
            }

            foreach ([
                'nearest_waterway',
                'elevation_m',
                'distance_to_waterway_m',
                'drainage_index',
                'impervious_surface_ratio',
                'population_density_per_km2',
                'historical_flood_count_5y',
            ] as $profileField) {
                if ($barangay->{$profileField} === null) {
                    return $this->markPending($record, "Barangay predictor {$profileField} is unavailable.");
                }
            }

            $snapshot = DailyWeatherSnapshot::query()
                ->whereDate('snapshot_date', $record->observed_at->timezone('Asia/Manila')->toDateString())
                ->first();

            $weather = $snapshot?->weather_data;

            if (! is_array($weather)) {
                return $this->markPending($record, 'Weather snapshot for the observation date is unavailable.');
            }

            $requiredWeather = [
                'rainfall_24h_mm',
                'rainfall_3d_mm',
                'rainfall_7d_mm',
                'avg_temp_mean_c',
                'avg_rh_pct',
                'avg_wind_speed',
            ];

            foreach ($requiredWeather as $key) {
                if (! array_key_exists($key, $weather) || ! is_numeric($weather[$key])) {
                    return $this->markPending($record, "Weather predictor {$key} is unavailable.");
                }
            }

            $record->update([
                'nearest_waterway' => $barangay->nearest_waterway,
                'elevation_m' => $barangay->elevation_m ?? 0,
                'distance_to_waterway_m' => $barangay->distance_to_waterway_m ?? 0,
                'drainage_index' => $barangay->drainage_index ?? 0,
                'impervious_surface_ratio' => $barangay->impervious_surface_ratio ?? 0,
                'population_density_per_km2' => $barangay->population_density_per_km2 ?? 0,
                'historical_flood_count_5y' => $barangay->historical_flood_count_5y ?? 0,
                'rainfall_24h_mm' => $weather['rainfall_24h_mm'],
                'rainfall_3d_mm' => $weather['rainfall_3d_mm'],
                'rainfall_7d_mm' => $weather['rainfall_7d_mm'],
                'temperature_c' => $weather['avg_temp_mean_c'],
                'humidity_pct' => $weather['avg_rh_pct'],
                'wind_speed_kph' => round((float) $weather['avg_wind_speed'] * 3.6, 2),
                'data_source' => 'Field observation + '.$snapshot->source,
                'enrichment_status' => 'Enriched',
                'enriched_at' => now(),
                'review_status' => $record->flood_status === 'Subsided' && $record->duration_hours > 0
                    ? 'Ready for Review'
                    : 'Pending',
                'include_in_training' => false,
                'exclusion_reason' => $record->flood_status === 'Subsided' && $record->duration_hours > 0
                    ? 'Awaiting authorized training review.'
                    : 'Active flood event; duration is not final.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('Flood observation enrichment failed.', [
                'record_id' => $record->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->markPending($record, 'Automatic predictor enrichment failed and must be retried.');
        }

        return $record->fresh();
    }

    private function markPending(FloodTrainingRecord $record, string $reason): FloodTrainingRecord
    {
        $record->update([
            'enrichment_status' => 'Pending Enrichment',
            'enriched_at' => null,
            'review_status' => 'Pending',
            'include_in_training' => false,
            'exclusion_reason' => $reason,
        ]);

        return $record->fresh();
    }
}
