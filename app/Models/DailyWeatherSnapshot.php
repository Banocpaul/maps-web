<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyWeatherSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'source',
        'weather_data',
        'fetched_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'weather_data' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
