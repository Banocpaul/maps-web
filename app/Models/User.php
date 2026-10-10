<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $attributes = [
        'must_change_password' => false,
        'password_version' => 0,
    ];

    protected $fillable = [
        'role_id',
        'role_changed_at',
        'name',
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'password',
        'is_active',
        'last_login_at',
        'last_seen_at',
        'approved_at',
        'barangay_id',
        'receive_flood_alerts',
        'receive_fire_alerts',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role_changed_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_version' => 'integer',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
            'receive_flood_alerts' => 'boolean',
            'receive_fire_alerts' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function profilePhoto(): HasOne
    {
        return $this->hasOne(ProfilePhoto::class);
    }

    public function smsRecipient(): HasOne
    {
        return $this->hasOne(SmsRecipient::class);
    }

    public function isPublicResident(): bool
    {
        return $this->hasRole('public-resident');
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->role()
            ->where('slug', $roleSlug)
            ->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->role()
            ->whereIn('slug', $roleSlugs)
            ->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $role = $this->role()->first();

        if (! $role || ! $role->is_active || $role->slug === 'public-resident') {
            return false;
        }

        if ($role->slug === 'administrator') {
            return true;
        }

        return $role->permissions()
            ->where('slug', $permissionSlug)
            ->where('is_active', true)
            ->exists();
    }

    public function hasAnyPermission(array $permissionSlugs): bool
    {
        foreach ($permissionSlugs as $permissionSlug) {
            if ($this->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
