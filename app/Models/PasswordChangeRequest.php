<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordChangeRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'password_version' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
