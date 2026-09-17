<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('staff.login'));
    }

    public function test_active_staff_can_log_in(): void
    {
        $staff = User::factory()->create([
            'email' => 'designer@kkk.test',
            'password' => Hash::make('secret-password'),
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->post(route('staff.login.store'), [
            'email' => $staff->email,
            'password' => 'secret-password',
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_inactive_or_non_staff_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@kkk.test',
            'password' => Hash::make('secret-password'),
            'role' => User::ROLE_DESIGNER,
            'is_active' => false,
        ]);

        $this->from(route('staff.login'))
            ->post(route('staff.login.store'), [
                'email' => $user->email,
                'password' => 'secret-password',
            ])
            ->assertRedirect(route('staff.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_can_log_out(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_PRINTING,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.logout'))
            ->assertRedirect(route('staff.login'));

        $this->assertGuest();
    }
}
