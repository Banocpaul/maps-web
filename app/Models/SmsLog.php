<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sms_recipient_id',
        'automation_rule_id',
        'sent_by',
        'recipient_name',
        'phone_number',
        'message',
        'source',
        'status',
        'condition_data',
        'http_status',
        'gateway_response',
        'failure_reason',
        'sent_at',
    ];

    protected $casts = [
        'condition_data' => 'array',
        'http_status' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            SmsRecipient::class,
            'sms_recipient_id'
        );
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(
            SmsAutomationRule::class,
            'automation_rule_id'
        );
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}