<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateStaffUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_active_staff_account_without_printing_the_password(): void
    {
        $this->artisan('staff:create', [
            'name' => 'Staging Operation Manager',
            'email' => 'om.staging@example.test',
            'role' => User::ROLE_OM,
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'SafePassword123!')
            ->expectsQuestion('Confirm password', 'SafePassword123!')
            ->expectsOutput('Staff account created.')
            ->doesntExpectOutput('SafePassword123!')
            ->assertSuccessful();

        $staff = User::query()->where('email', 'om.staging@example.test')->firstOrFail();

        $this->assertSame('Staging Operation Manager', $staff->name);
        $this->assertSame(User::ROLE_OM, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertTrue(password_verify('SafePassword123!', $staff->password));
    }

    public function test_command_accepts_an_eight_character_password(): void
    {
        $this->artisan('staff:create', [
            'name' => 'Eight Character Admin',
            'email' => 'admin.eight@example.test',
            'role' => User::ROLE_ADMIN,
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'Admin8!x')
            ->expectsQuestion('Confirm password', 'Admin8!x')
            ->expectsOutput('Staff account created.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin.eight@example.test',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_command_rejects_an_unapproved_role(): void
    {
        $this->artisan('staff:create', [
            'name' => 'Invalid Packing User',
            'email' => 'packing.staging@example.test',
            'role' => 'PACKING',
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'SafePassword123!')
            ->expectsQuestion('Confirm password', 'SafePassword123!')
            ->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'packing.staging@example.test',
        ]);
    }

    public function test_command_rejects_a_password_shorter_than_eight_characters(): void
    {
        $this->artisan('staff:create', [
            'name' => 'Staging Designer',
            'email' => 'designer.staging@example.test',
            'role' => User::ROLE_DESIGNER,
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'Admin8!')
            ->expectsQuestion('Confirm password', 'Admin8!')
            ->expectsOutput('Password mestilah sekurang-kurangnya 8 aksara.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => 'designer.staging@example.test',
        ]);
    }

    public function test_command_can_create_an_inactive_account(): void
    {
        $this->artisan('staff:create', [
            'name' => 'Inactive Staging Admin',
            'email' => 'inactive.admin@example.test',
            'role' => User::ROLE_ADMIN,
            '--inactive' => true,
        ])
            ->expectsQuestion('Password (minimum 8 characters)', 'SafePassword123!')
            ->expectsQuestion('Confirm password', 'SafePassword123!')
            ->assertSuccessful();

        $staff = User::query()->where('email', 'inactive.admin@example.test')->firstOrFail();

        $this->assertFalse($staff->is_active);
    }
}
