<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictionExecution extends Model
{
    protected $fillable = [
        'requested_by_user_id', 'requested_by_name', 'kind', 'forecast_hours',
        'status', 'requested_at', 'completed_at', 'input_snapshot', 'result_snapshot', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'forecast_hours' => 'integer', 'requested_at' => 'datetime', 'completed_at' => 'datetime',
            'input_snapshot' => 'array', 'result_snapshot' => 'array',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(PredictionRemark::class);
    }
}
