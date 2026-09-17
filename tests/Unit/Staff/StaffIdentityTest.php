<?php

namespace Tests\Unit\Staff;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class StaffIdentityTest extends TestCase
{
    public function test_active_admin_is_recognized_as_staff_and_admin(): void
    {
        $user = new User([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->assertTrue($user->isActiveStaff());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasStaffRole(User::ROLE_ADMIN));
    }

    public function test_inactive_admin_is_not_active_staff(): void
    {
        $user = new User([
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ]);

        $this->assertFalse($user->isActiveStaff());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->hasStaffRole(User::ROLE_ADMIN));
    }

    public function test_active_user_without_staff_role_is_not_active_staff(): void
    {
        $user = new User([
            'role' => null,
            'is_active' => true,
        ]);

        $this->assertFalse($user->isActiveStaff());
    }

    public function test_unknown_role_is_not_active_staff(): void
    {
        $user = new User([
            'role' => 'UNKNOWN',
            'is_active' => true,
        ]);

        $this->assertFalse($user->isActiveStaff());
    }

    public function test_supported_operational_roles_are_active_staff_when_enabled(): void
    {
        foreach ([
            User::ROLE_DESIGNER,
            User::ROLE_PRINTING,
            User::ROLE_PACKING,
        ] as $role) {
            $user = new User([
                'role' => $role,
                'is_active' => true,
            ]);

            $this->assertTrue($user->isActiveStaff());
            $this->assertTrue($user->hasStaffRole($role));
            $this->assertFalse($user->isAdmin());
        }
    }
}