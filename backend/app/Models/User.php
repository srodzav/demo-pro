<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roleRecord()?->name === $role;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->roleRecord()?->permissions()
            ->where('permissions.name', $permission)
            ->exists() ?? false;
    }

    public function permissionNames(): array
    {
        $role = $this->roleRecord();

        if (!$role) {
            return [];
        }

        if ($role->relationLoaded('permissions')) {
            return $role->permissions->pluck('name')->values()->all();
        }

        return $role->permissions()->pluck('permissions.name')->values()->all();
    }

    public function roleRecord(): ?Role
    {
        $role = $this->relations['role'] ?? null;

        if ($role instanceof Role) {
            return $role;
        }

        if (!$this->role_id) {
            return null;
        }

        return Role::with('permissions')->find($this->role_id);
    }
}
