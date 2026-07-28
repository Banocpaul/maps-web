<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FloodTrainingRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'observed_at',
        'barangay',
        'data_source',
        'remarks',

        'month',
        'is_weekend',
        'wet_season',
        'storm_signal',

        'nearest_waterway',
        'elevation_m',
        'distance_to_waterway_m',
        'drainage_index',
        'impervious_surface_ratio',
        'population_density_per_km2',
        'historical_flood_count_5y',

        'rainfall_24h_mm',
        'rainfall_3d_mm',
        'rainfall_7d_mm',
        'temperature_c',
        'humidity_pct',
        'wind_speed_kph',
        'tide_level_m',

        'risk_level',
        'flood_depth_mm',
        'duration_hours',

        'include_in_training',
        'exclusion_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',

            'month' => 'integer',
            'is_weekend' => 'boolean',
            'wet_season' => 'boolean',
            'storm_signal' => 'integer',

            'elevation_m' => 'float',
            'distance_to_waterway_m' => 'float',
            'drainage_index' => 'float',
            'impervious_surface_ratio' => 'float',
            'population_density_per_km2' => 'float',
            'historical_flood_count_5y' => 'integer',

            'rainfall_24h_mm' => 'float',
            'rainfall_3d_mm' => 'float',
            'rainfall_7d_mm' => 'float',
            'temperature_c' => 'float',
            'humidity_pct' => 'float',
            'wind_speed_kph' => 'float',
            'tide_level_m' => 'float',

            'flood_depth_mm' => 'float',
            'duration_hours' => 'float',

            'include_in_training' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function scopeIncludedInTraining(
        Builder $query
    ): Builder {
        return $query->where(
            'include_in_training',
            true
        );
    }

    public function scopeRiskLevel(
        Builder $query,
        ?string $riskLevel
    ): Builder {
        if (
            $riskLevel === null ||
            $riskLevel === '' ||
            $riskLevel === 'all'
        ) {
            return $query;
        }

        return $query->where(
            'risk_level',
            $riskLevel
        );
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $search = trim($search);

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('barangay', 'like', "%{$search}%")
                ->orWhere('nearest_waterway', 'like', "%{$search}%")
                ->orWhere('data_source', 'like', "%{$search}%")
                ->orWhere('remarks', 'like', "%{$search}%");
        });
    }
}