<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'role_name',
        'action',
        'module',
        'description',
        'route_name',
        'http_method',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'response_status',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'response_status' => 'integer',
        ];
    }

    public function scopeAdministrative(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('module', [
            'authentication', 'user-management', 'users', 'roles', 'permissions', 'role-permission',
            'administration', 'admin', 'backup-recovery', 'database-backups',
            'activity-logs', 'settings', 'profile', 'profile-photos', 'password',
            'password-change-requests', 'system-analytics',
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
