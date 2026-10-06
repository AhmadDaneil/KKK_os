<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->post(route('admin.password.email'), ['email' => $admin->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($admin, ResetPassword::class);
        $this->assertDatabaseHas('staff_audit_logs', [
            'event_type' => 'ADMIN_PASSWORD_RESET_LINK_SENT',
            'target_user_id' => $admin->id,
        ]);
    }

    public function test_password_reset_link_is_not_sent_to_non_admin_account(): void
    {
        Notification::fake();
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->post(route('admin.password.email'), ['email' => $staff->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_admin_can_reset_their_password_using_a_valid_token(): void
    {
        $admin = $this->admin();
        $token = Password::broker('users')->createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'NewAdminPassword123!',
            'password_confirmation' => 'NewAdminPassword123!',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Kata laluan admin berjaya ditetapkan semula. Sila log masuk.');

        $this->assertTrue(Hash::check('NewAdminPassword123!', $admin->fresh()->password));
        $this->assertDatabaseHas('staff_audit_logs', [
            'event_type' => 'ADMIN_PASSWORD_RESET',
            'target_user_id' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_use_an_admin_password_reset_token(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);
        $token = Password::broker('users')->createToken($staff);
        $originalPassword = $staff->password;

        $this->from(route('admin.password.reset', ['token' => $token, 'email' => $staff->email]))
            ->post(route('admin.password.update'), [
                'token' => $token,
                'email' => $staff->email,
                'password' => 'NewStaffPassword123!',
                'password_confirmation' => 'NewStaffPassword123!',
            ])->assertRedirect(route('admin.password.reset', ['token' => $token, 'email' => $staff->email]))
            ->assertSessionHasErrors('email');

        $this->assertSame($originalPassword, $staff->fresh()->password);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
