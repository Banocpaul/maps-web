<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicAdvisory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'advisory_date',
        'type',
        'subject',
        'message',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'advisory_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Newest public advisories first.
     *
     * Advisory date is the primary public-facing date. Created time
     * breaks ties when multiple advisories are issued on the same day.
     */
    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('advisory_date')
            ->orderByDesc('created_at');
    }
}
