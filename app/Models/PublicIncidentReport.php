<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicIncidentReport extends Model
{
    protected $fillable = [
        'reference', 'submission_token', 'incident_type', 'latitude', 'longitude',
        'status', 'validated_by', 'validated_at', 'published_by', 'published_at',
        'rejected_by', 'rejected_at', 'validation_notes', 'rejection_reason',
        'fire_incident_id', 'flood_training_record_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float', 'longitude' => 'float',
            'validated_at' => 'datetime', 'published_at' => 'datetime', 'rejected_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(PublicIncidentReportEvent::class)->orderBy('id');
    }

    public function fireIncident(): BelongsTo
    {
        return $this->belongsTo(FireIncident::class);
    }

    public function floodTrainingRecord(): BelongsTo
    {
        return $this->belongsTo(FloodTrainingRecord::class);
    }
}
