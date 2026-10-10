<?php

namespace App\Services;

use App\Models\DailyWeatherSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WeatherObservationRecorder
{
    public function record(DailyWeatherSnapshot $snapshot): void
    {
        $weather = $snapshot->weather_data;
        if ($snapshot->source !== 'Open-Meteo' || ! is_array($weather)
            || empty($weather['observed_at']) || ! Schema::hasColumn('weather_observations', 'daily_weather_snapshot_id')) {
            return;
        }
        // Only measured/current values belong here; forecast values remain in the snapshot.
        $row = [
            'station_name' => $weather['location'] ?? 'Mandaluyong City',
            'source' => 'Open-Meteo',
            'observed_at' => Carbon::parse($weather['observed_at'], 'Asia/Manila')->utc()->format('Y-m-d H:i:s'),
            'rainfall_24h_mm' => $weather['rainfall_24h_mm'] ?? null,
            'rainfall_3d_mm' => $weather['rainfall_3d_mm'] ?? null,
            'rainfall_7d_mm' => $weather['rainfall_7d_mm'] ?? null,
            'temperature_c' => $weather['current_temperature_c'] ?? $weather['avg_temp_mean_c'] ?? null,
            'relative_humidity_pct' => $weather['avg_rh_pct'] ?? null,
            // LiveWeatherService requests wind in m/s; records use km/h.
            'wind_speed_kph' => isset($weather['avg_wind_speed']) ? round((float) $weather['avg_wind_speed'] * 3.6, 2) : null,
            'wind_direction_deg' => $weather['avg_wind_direction_deg'] ?? null,
            'weather_condition' => $weather['weather_description'] ?? null,
            'created_at' => $snapshot->fetched_at ?? now(),
        ];
        $row = array_intersect_key($row, array_flip(Schema::getColumnListing('weather_observations')));
        DB::table('weather_observations')->updateOrInsert(['daily_weather_snapshot_id' => $snapshot->id], $row);
    }
}
