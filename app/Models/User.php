<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'username',
        'email',
        'role',
        'password',
        'pos_pin',
        'phone',
        'avatar_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pos_pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_pos_login_at' => 'datetime',
            'password' => 'hashed',
            'pos_pin' => 'hashed',
            'is_active' => 'boolean',
            'role' => Role::class,
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** The shift this operator currently has open, if any. */
    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role instanceof Role && $this->role->can($permission);
    }

    public function isRole(Role|string $role): bool
    {
        $value = $role instanceof Role ? $role->value : $role;

        return $this->role?->value === $value;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isSupervisor(): bool
    {
        return $this->role === Role::Supervisor;
    }

    public function isKasir(): bool
    {
        return $this->role === Role::Kasir;
    }

    public function canAccessDashboard(): bool
    {
        return (bool) $this->role?->canAccessDashboard();
    }

    /** Initials used by the avatar chip when no image is uploaded. */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path)
            : null;
    }
}
