<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloodPrediction extends Model
{
    use HasFactory;

    protected $table = 'flood_predictions';

    public $timestamps = false;

    protected $fillable = [
        'prediction_run_id',
        'classification_model_id',
        'depth_model_id',
        'duration_model_id',
        'predicted_risk_level',
        'low_probability',
        'medium_probability',
        'high_probability',
        'predicted_depth_mm',
        'predicted_duration_hours',
        'predicted_at',
        'is_alert_triggered',
    ];

    protected function casts(): array
    {
        return [
            'prediction_run_id' => 'integer',
            'classification_model_id' => 'integer',
            'depth_model_id' => 'integer',
            'duration_model_id' => 'integer',

            'low_probability' => 'float',
            'medium_probability' => 'float',
            'high_probability' => 'float',

            'predicted_depth_mm' => 'float',
            'predicted_duration_hours' => 'float',

            'predicted_at' => 'datetime',
            'is_alert_triggered' => 'boolean',
        ];
    }

    public function predictionRun(): BelongsTo
    {
        return $this->belongsTo(
            PredictionRun::class,
            'prediction_run_id'
        );
    }
}