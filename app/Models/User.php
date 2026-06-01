<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'phone',
        'balance',
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
            'balance' => 'decimal:2',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function bondRequestsCreated(): HasMany
    {
        return $this->hasMany(BondRequest::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function permissionSlugs(): array
    {
        if (! $this->relationLoaded('role')) {
            $this->load('role.permissions');
        }

        if ($this->hasRole(RoleSlug::SuperAdmin)) {
            return Permission::pluck('slug')->all();
        }

        return $this->role?->permissions?->pluck('slug')->all() ?? [];
    }

    public function hasRole(string|RoleSlug $slug): bool
    {
        $slug = $slug instanceof RoleSlug ? $slug->value : $slug;

        return $this->role?->slug === $slug;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole(RoleSlug::SuperAdmin)) {
            return true;
        }

        if (! $this->is_active) {
            return false;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
