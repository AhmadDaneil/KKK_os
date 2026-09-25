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

    public const ROLE_OM = 'OPERATION_MANAGEMENT';

    public const ROLE_CUSTOMER_SERVICE = 'CUSTOMER_SERVICE';

    public const ROLE_DESIGNER = 'DESIGNER';

    public const ROLE_PRODUCTION = 'PRODUCTION';

    /** @deprecated Use ROLE_PRODUCTION. */
    public const ROLE_PRINTING = self::ROLE_PRODUCTION;

    /** @deprecated Packing responsibilities are now part of ROLE_OM. */
    public const ROLE_PACKING = self::ROLE_OM;

    public const STAFF_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OM,
        self::ROLE_CUSTOMER_SERVICE,
        self::ROLE_DESIGNER,
        self::ROLE_PRODUCTION,
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

    public function isOperationManagement(): bool
    {
        return $this->isActiveStaff() && in_array($this->role, [self::ROLE_ADMIN, self::ROLE_OM], true);
    }

    public function isCustomerService(): bool
    {
        return $this->isActiveStaff()
            && $this->role === self::ROLE_CUSTOMER_SERVICE;
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
