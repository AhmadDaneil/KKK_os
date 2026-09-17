<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_DESIGNER = 'DESIGNER';
    public const ROLE_PRINTING = 'PRINTING';
    public const ROLE_PACKING = 'PACKING';

    public const STAFF_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_DESIGNER,
        self::ROLE_PRINTING,
        self::ROLE_PACKING,
    ];

    public function isActiveStaff(): bool
    {
        return $this->is_active
            && in_array($this->role, self::STAFF_ROLES, true);
    }

    public function isAdmin(): bool
    {
        return $this->isActiveStaff()
            && $this->role === self::ROLE_ADMIN;
    }

    public function hasStaffRole(string $role): bool
    {
        return $this->isActiveStaff()
            && $this->role === $role;
    }

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
}
