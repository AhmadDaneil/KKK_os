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
            ->expectsQuestion('Password baharu (minimum 12 aksara)', 'NewSafePassword123!')
            ->expectsQuestion('Sahkan password baharu', 'NewSafePassword123!')
            ->expectsOutput('Password staff berjaya ditetapkan semula.')
            ->doesntExpectOutput('NewSafePassword123!')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('NewSafePassword123!', $staff->fresh()->password));

        $this->post(route('admin.login.store'), [
            'email' => 'admin@kkk.local',
            'password' => 'NewSafePassword123!',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff, 'admin');
    }

    public function test_command_rejects_an_unknown_staff_email_without_prompting_for_a_password(): void
    {
        $this->artisan('staff:password', ['email' => 'missing@kkk.local'])
            ->expectsOutput('Akaun staff dengan e-mel tersebut tidak ditemui.')
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
            ->expectsQuestion('Password baharu (minimum 12 aksara)', 'short')
            ->expectsQuestion('Sahkan password baharu', 'short')
            ->expectsOutput('Password baharu mestilah sekurang-kurangnya 12 aksara.')
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
            ->expectsQuestion('Password baharu (minimum 12 aksara)', 'NewSafePassword123!')
            ->expectsQuestion('Sahkan password baharu', 'DifferentPassword123!')
            ->expectsOutput('Pengesahan password baharu tidak sepadan.')
            ->assertFailed();

        $this->assertSame($originalPassword, $staff->fresh()->password);
    }
}
