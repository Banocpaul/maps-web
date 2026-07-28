<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeatherObservation extends Model
{
    use HasFactory;

    protected $table = 'weather_observations';

    const UPDATED_AT = null;

    protected $fillable = [
        'barangay_id',
        'station_name',
        'source',
        'observed_at',
        'rainfall_1h_mm',
        'rainfall_24h_mm',
        'rainfall_3d_mm',
        'rainfall_7d_mm',
        'temperature_c',
        'relative_humidity_pct',
        'wind_speed_kph',
        'wind_direction_deg',
        'weather_condition',
    ];

    protected function casts(): array
    {
        return [
            'barangay_id' => 'integer',
            'observed_at' => 'datetime',

            'rainfall_1h_mm' => 'float',
            'rainfall_24h_mm' => 'float',
            'rainfall_3d_mm' => 'float',
            'rainfall_7d_mm' => 'float',

            'temperature_c' => 'float',
            'relative_humidity_pct' => 'float',
            'wind_speed_kph' => 'float',
            'wind_direction_deg' => 'float',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(
            Barangay::class,
            'barangay_id'
        );
    }

    public function predictionRuns(): HasMany
    {
        return $this->hasMany(
            PredictionRun::class,
            'weather_observation_id'
        );
    }
}