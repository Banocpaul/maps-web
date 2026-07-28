<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'contact_number',
        'password',
        'is_active',
        'last_login_at',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    if (! $role || ! $role->is_active) {
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