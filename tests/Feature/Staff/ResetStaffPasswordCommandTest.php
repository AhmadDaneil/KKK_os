<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetStaffPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_resets_a_staff_password_without_printing_it(): void
    {
        $staff = User::factory()->create([
            'email' => 'admin@kkk.local',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->artisan('staff:password', ['email' => 'admin@kkk.local'])
            ->expectsQuestion('New password (at least 8 characters)', 'Reset8!x')
            ->expectsQuestion('Confirm new password', 'Reset8!x')
            ->expectsOutput('Password staff berjaya ditetapkan semula.')
            ->doesntExpectOutput('Reset8!x')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Reset8!x', $staff->fresh()->password));

        $this->post(route('admin.login.store'), [
            'email' => 'admin@kkk.local',
            'password' => 'Reset8!x',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff, 'admin');
    }

    public function test_command_rejects_an_unknown_staff_email_without_prompting_for_a_password(): void
    {
        $this->artisan('staff:password', ['email' => 'missing@kkk.local'])
            ->expectsOutput('No staff account was found for that email address.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_rejects_a_short_password_with_a_clear_message(): void
    {
        $staff = User::factory()->create([
            'email' => 'om@kkk.local',
            'role' => User::ROLE_OM,
            'is_active' => true,
        ]);
        $originalPassword = $staff->password;

        $this->artisan('staff:password', ['email' => 'om@kkk.local'])
            ->expectsQuestion('New password (at least 8 characters)', 'Admin8!')
            ->expectsQuestion('Confirm new password', 'Admin8!')
            ->expectsOutput('The new password must be at least 8 characters.')
            ->assertFailed();

        $this->assertSame($originalPassword, $staff->fresh()->password);
    }

    public function test_command_rejects_a_password_confirmation_mismatch(): void
    {
        $staff = User::factory()->create([
            'email' => 'designer@kkk.local',
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);
        $originalPassword = $staff->password;

        $this->artisan('staff:password', ['email' => 'designer@kkk.local'])
            ->expectsQuestion('New password (at least 8 characters)', 'NewSafePassword123!')
            ->expectsQuestion('Confirm new password', 'DifferentPassword123!')
            ->expectsOutput('The new password confirmation does not match.')
            ->assertFailed();

        $this->assertSame($originalPassword, $staff->fresh()->password);
    }
}
