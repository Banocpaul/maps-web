<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PredictionRun extends Model
{
    use HasFactory;

    protected $table = 'prediction_runs';

    protected $fillable = [
        'barangay_id',
        'requested_by_user_id',
        'weather_observation_id',
        'requested_at',
        'input_data_json',
        'source',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'barangay_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'weather_observation_id' => 'integer',
            'requested_at' => 'datetime',
            'input_data_json' => 'array',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(
            Barangay::class,
            'barangay_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    public function weatherObservation(): BelongsTo
    {
        return $this->belongsTo(
            WeatherObservation::class,
            'weather_observation_id'
        );
    }

    public function floodPrediction(): HasOne
    {
        return $this->hasOne(
            FloodPrediction::class,
            'prediction_run_id'
        );
    }
}