<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FireIncident extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'barangay_id',
        'incident_number',
        'incident_type',
        'location',
        'street',
        'corner',
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

        'record_classification', 'source_origin', 'source_barangay', 'coordinate_accuracy',
        'cause', 'alarm_reference', 'cause_reference', 'source_record',
        'record_status', 'finalized_at', 'finalized_by',
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
            'source_record' => 'array',
            'finalized_at' => 'datetime',
            'finalized_by' => 'integer',
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
        static::addGlobalScope('operational_records', fn ($query) =>
            $query->whereIn('fire_incidents.record_classification', ['Reported', 'Dataset']));

        static::saving(function (FireIncident $incident): void {
            if ($incident->record_status !== 'Finalized') {
                $incident->record_status = $incident->status === 'Resolved' ? 'For Assessment' : 'Open';
            }
            if ($incident->isDirty('fire_out_at') && $incident->fire_out_at === null) {
                $incident->duration_minutes = null;
            }
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

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->withoutGlobalScope('operational_records');
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function canUpdateResponse(): bool
    {
        return $this->status !== 'Resolved' && $this->record_status !== 'Finalized'
            && ! in_array($this->record_classification, ['Example', 'Superseded'], true);
    }

    public function canFinalize(): bool
    {
        return $this->status === 'Resolved' && $this->fire_out_at !== null
            && $this->record_status === 'For Assessment'
            && ! in_array($this->record_classification, ['Example', 'Superseded'], true);
    }

    public function canRecordFireOut(): bool
    {
        return $this->canUpdateResponse() || ($this->status === 'Resolved' && $this->fire_out_at === null
            && $this->record_status === 'For Assessment'
            && ! in_array($this->record_classification, ['Example', 'Superseded'], true));
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'fire_incident_id');
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
        $start = \Carbon\Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Manila');
        return $query->where('occurred_at', '>=', $start->copy()->utc())
            ->where('occurred_at', '<', $start->copy()->addYear()->utc());
    }

    public function scopeForMonth($query, int $month)
    {
        $expression = $query->getConnection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', occurred_at, '+8 hours') AS INTEGER)"
            : 'MONTH(DATE_ADD(occurred_at, INTERVAL 8 HOUR))';
        return $query->whereRaw($expression.' = ?', [$month]);
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
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->year;
    }

    public function getIncidentMonthAttribute(): ?int
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->month;
    }

    public function getIncidentMonthNameAttribute(): ?string
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->format('F');
    }

    public function getIncidentDayAttribute(): ?int
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->day;
    }

    public function getIncidentDayOfWeekAttribute(): ?string
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->format('l');
    }

    public function getIncidentHourAttribute(): ?int
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->hour;
    }

    public function getTimeOfDayAttribute(): ?string
    {
        if ($this->occurred_at === null) {
            return null;
        }

        $hour = $this->occurred_at->copy()->timezone('Asia/Manila')->hour;

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Morning',
            $hour >= 12 && $hour < 17 => 'Afternoon',
            $hour >= 17 && $hour < 21 => 'Evening',
            default => 'Night',
        };
    }

    public function getIsWeekendAttribute(): ?bool
    {
        return $this->occurred_at?->copy()->timezone('Asia/Manila')->isWeekend();
    }

    public function getDamageSeverityAttribute(): string
    {
        if ($this->houses_destroyed === null && $this->individuals_affected === null) return 'Unspecified';

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
