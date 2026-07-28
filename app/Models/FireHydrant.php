<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FireHydrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_id',
        'hydrant_code',
        'location',
        'latitude',
        'longitude',
        'status',
        'installation_date',
        'last_inspection_date',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'barangay_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'installation_date' => 'date',
            'last_inspection_date' => 'date',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
        ];
    }
}