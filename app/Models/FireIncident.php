<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FireIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay_id',
        'incident_number',
        'incident_type',
        'location',
        'latitude',
        'longitude',
        'severity',
        'status',

        // Operational timestamps
        'reported_at',
        'responded_at',
        'resolved_at',

        // Historical fire BI fields
        'occurred_at',
        'fire_out_at',
        'duration_minutes',
        'individuals_affected',
        'houses_destroyed',
        'alarm_level',
        'data_source',

        'remarks',
    ];

    protected $appends = [
        'coordinates',
        'incident_year',
        'incident_month',
        'incident_month_name',
        'incident_day',
        'incident_day_of_week',
        'incident_hour',
        'time_of_day',
        'is_weekend',
        'damage_severity',
    ];

    protected function casts(): array
    {
        return [
            'barangay_id' => 'integer',

            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',

            'reported_at' => 'datetime',
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',

            'occurred_at' => 'datetime',
            'fire_out_at' => 'datetime',

            'duration_minutes' => 'integer',
            'individuals_affected' => 'integer',
            'houses_destroyed' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FireIncident $incident): void {
            if (
                $incident->occurred_at !== null &&
                $incident->fire_out_at !== null
            ) {
                if ($incident->fire_out_at->greaterThanOrEqualTo($incident->occurred_at)) {
                    $incident->duration_minutes = $incident->occurred_at
                        ->diffInMinutes($incident->fire_out_at);
                } else {
                    $incident->duration_minutes = null;
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeReported($query)
    {
        return $query->where('status', 'Reported');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'Reported',
            'Responding',
            'Controlled',
        ]);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'Resolved');
    }

    public function scopeHistorical($query)
    {
        return $query->whereNotNull('occurred_at');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('occurred_at', $year);
    }

    public function scopeForMonth($query, int $month)
    {
        return $query->whereMonth('occurred_at', $month);
    }

    public function scopeForBarangay($query, int $barangayId)
    {
        return $query->where('barangay_id', $barangayId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors for GIS and Business Intelligence
    |--------------------------------------------------------------------------
    */

    public function getCoordinatesAttribute(): array
    {
        return [
            'latitude' => $this->latitude !== null
                ? (float) $this->latitude
                : null,

            'longitude' => $this->longitude !== null
                ? (float) $this->longitude
                : null,
        ];
    }

    public function getIncidentYearAttribute(): ?int
    {
        return $this->occurred_at?->year;
    }

    public function getIncidentMonthAttribute(): ?int
    {
        return $this->occurred_at?->month;
    }

    public function getIncidentMonthNameAttribute(): ?string
    {
        return $this->occurred_at?->format('F');
    }

    public function getIncidentDayAttribute(): ?int
    {
        return $this->occurred_at?->day;
    }

    public function getIncidentDayOfWeekAttribute(): ?string
    {
        return $this->occurred_at?->format('l');
    }

    public function getIncidentHourAttribute(): ?int
    {
        return $this->occurred_at?->hour;
    }

    public function getTimeOfDayAttribute(): ?string
    {
        if ($this->occurred_at === null) {
            return null;
        }

        $hour = $this->occurred_at->hour;

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Morning',
            $hour >= 12 && $hour < 17 => 'Afternoon',
            $hour >= 17 && $hour < 21 => 'Evening',
            default => 'Night',
        };
    }

    public function getIsWeekendAttribute(): ?bool
    {
        return $this->occurred_at?->isWeekend();
    }

    public function getDamageSeverityAttribute(): string
    {
        $housesDestroyed = $this->houses_destroyed ?? 0;
        $individualsAffected = $this->individuals_affected ?? 0;

        if ($housesDestroyed >= 10 || $individualsAffected >= 50) {
            return 'Severe';
        }

        if ($housesDestroyed >= 3 || $individualsAffected >= 15) {
            return 'Moderate';
        }

        if ($housesDestroyed > 0 || $individualsAffected > 0) {
            return 'Minor';
        }

        return 'No Recorded Damage';
    }
}
