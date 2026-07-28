<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'hazard_type',
        'condition_field',
        'condition_operator',
        'condition_value',
        'barangay',
        'message_template',
        'recipient_scope',
        'cooldown_minutes',
        'last_triggered_at',
        'is_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'cooldown_minutes' => 'integer',
        'last_triggered_at' => 'datetime',
        'is_enabled' => 'boolean',
    ];

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
        return $this->hasMany(SmsLog::class, 'automation_rule_id');
    }
}