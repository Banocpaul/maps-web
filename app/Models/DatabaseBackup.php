<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'uuid',
        'type',
        'status',
        'disk',
        'path',
        'filename',
        'size_bytes',
        'sha256',
        'database_name',
        'created_by',
        'started_at',
        'completed_at',
        'verified_at',
        'verified_by',
        'restored_at',
        'restored_by',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'restored_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2)
            .' '.$units[$power];
    }
}
