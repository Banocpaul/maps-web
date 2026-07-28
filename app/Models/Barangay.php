<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'district',
        'latitude',
        'longitude',
        'elevation_m',
        'nearest_waterway',
        'distance_to_waterway_m',
        'drainage_index',
        'impervious_surface_ratio',
        'population_density_per_km2',
        'historical_flood_count_5y',
        'is_active',
    ];

    protected $appends = [
        'coordinates',
    ];

    protected function casts(): array
    {
        return [
            'district' => 'integer',

            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',

            'elevation_m' => 'decimal:2',
            'distance_to_waterway_m' => 'decimal:2',

            'drainage_index' => 'decimal:4',
            'impervious_surface_ratio' => 'decimal:4',

            'population_density_per_km2' => 'decimal:2',
            'historical_flood_count_5y' => 'integer',

            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByDistrict(
        Builder $query,
        int $district
    ): Builder {
        return $query->where('district', $district);
    }

    public function scopeWithFloodProfile(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull('elevation_m')
            ->whereNotNull('nearest_waterway')
            ->whereNotNull('distance_to_waterway_m')
            ->whereNotNull('drainage_index')
            ->whereNotNull('impervious_surface_ratio')
            ->whereNotNull('population_density_per_km2');
    }

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

    public function getFloodProfileAttribute(): array
    {
        return [
            'elevation_m' => $this->elevation_m !== null
                ? (float) $this->elevation_m
                : null,

            'nearest_waterway' => $this->nearest_waterway,

            'distance_to_waterway_m' =>
                $this->distance_to_waterway_m !== null
                    ? (float) $this->distance_to_waterway_m
                    : null,

            'drainage_index' => $this->drainage_index !== null
                ? (float) $this->drainage_index
                : null,

            'impervious_surface_ratio' =>
                $this->impervious_surface_ratio !== null
                    ? (float) $this->impervious_surface_ratio
                    : null,

            'population_density_per_km2' =>
                $this->population_density_per_km2 !== null
                    ? (float) $this->population_density_per_km2
                    : null,

            'historical_flood_count_5y' =>
                $this->historical_flood_count_5y !== null
                    ? (int) $this->historical_flood_count_5y
                    : null,
        ];
    }

    public function fireHydrants(): HasMany
    {
        return $this->hasMany(FireHydrant::class);
    }

    public function fireIncidents(): HasMany
    {
        return $this->hasMany(FireIncident::class);
    }

    public function floodTrainingRecords(): HasMany
    {
        return $this->hasMany(
            FloodTrainingRecord::class,
            'barangay_id'
        );
    }
}