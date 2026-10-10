<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone_number',
        'position',
        'office_or_barangay',
        'barangay_id',
        'receive_flood_alerts',
        'receive_fire_alerts',
        'receive_general_alerts',
        'is_active',
        'created_by',
        'updated_by',
        'user_id',
    ];

    protected $casts = [
        'barangay_id' => 'integer',
        'receive_flood_alerts' => 'boolean',
        'receive_fire_alerts' => 'boolean',
        'receive_general_alerts' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEligibleForAlerts(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(function (Builder $query): void {
            $query->whereNull('user_id')->orWhereHas('user', function (Builder $user): void {
                $user->where('is_active', true)->whereHas('role', fn (Builder $role) =>
                    $role->where('slug', 'public-resident')->where('is_active', true));
            });
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'sms_recipient_id');
    }
}
