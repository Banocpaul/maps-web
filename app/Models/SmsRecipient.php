<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
