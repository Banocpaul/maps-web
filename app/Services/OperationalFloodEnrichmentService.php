<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\DailyWeatherSnapshot;
use App\Models\FloodIncidentRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationalFloodEnrichmentService
{
    public function enrich(FloodIncidentRecord $record): FloodIncidentRecord
    {
        $profile = Barangay::active()->where('name', $record->barangay)->first();
        $fields = ['nearest_waterway', 'elevation_m', 'distance_to_waterway_m', 'drainage_index', 'impervious_surface_ratio', 'population_density_per_km2', 'historical_flood_count_5y'];
        $attributes = [];
        $missing = [];
        foreach ($fields as $field) {
            $attributes[$field] = $profile?->{$field};
            if ($attributes[$field] === null) $missing[] = $field;
        }
        // Reuse the single daily fetch policy. Never attach a stale day's weather.
        $weather = [];
        try {
            if ($record->event_date === now('Asia/Manila')->toDateString()) {
                $response = app(LiveWeatherService::class)->getCurrentWeather();
                if (($response['daily_snapshot_date'] ?? $response['date'] ?? null) === $record->event_date
                    && ! ($response['weather_is_stale'] ?? false)) $weather = $response;
            } else {
                $weather = DailyWeatherSnapshot::where('snapshot_date', $record->event_date)
                    ->where('source', 'Open-Meteo')->first()?->weather_data ?? [];
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        $weatherFields = [
            'rainfall_24h_mm' => 'rainfall_24h_mm', 'rainfall_3d_mm' => 'rainfall_3d_mm',
            'rainfall_7d_mm' => 'rainfall_7d_mm', 'temperature_c' => 'avg_temp_mean_c',
            'temp_max_c' => 'observed_temp_max_c', 'temp_min_c' => 'observed_temp_min_c',
            'humidity_pct' => 'avg_rh_pct', 'wind_speed_kph' => 'avg_wind_speed',
            'wind_direction_deg' => 'avg_wind_direction_deg',
        ];
        foreach ($weatherFields as $field => $key) {
            // Keep existing captured values if a later retry cannot provide them.
            $value = $weather[$key] ?? null;
            if (is_numeric($value)) {
                $attributes[$field] = $field === 'wind_speed_kph' ? round((float) $value * 3.6, 2) : (float) $value;
            } elseif ($record->{$field} === null) {
                $missing[] = $field;
            }
        }
        if (! empty($weather['observed_at'])) {
            $attributes['weather_source'] = $weather['source'] ?? 'Open-Meteo';
            $attributes['weather_observed_at'] = Carbon::parse($weather['observed_at'])->timezone('Asia/Manila')->format('Y-m-d H:i:s');
        }
        // Open-Meteo does not supply PAGASA storm signals. Leave unknown signals empty.
        $attributes['enrichment_status'] = $missing ? 'Pending data' : 'Complete';
        $attributes['enrichment_note'] = $missing ? 'Unavailable: '.implode(', ', $missing).'. Retry when today’s data is available.' : null;
        DB::transaction(function () use ($record, $attributes): void {
            $current = FloodIncidentRecord::lockForUpdate()->find($record->id);
            if ($current && $current->barangay === $record->barangay && $current->event_date === $record->event_date) {
                $current->update($attributes);
            }
        });
        return $record->fresh();
    }
}
