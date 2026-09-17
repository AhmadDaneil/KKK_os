<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_staff_login_page(): void
    {
        $this->get('/staff/login')
            ->assertOk()
            ->assertSee('KKK OS Staff');
    }

    public function test_unauthenticated_user_is_redirected_to_staff_login(): void
    {
        $this->get('/staff')
            ->assertRedirect(route('staff.login'));
    }

    public function test_active_staff_can_login_and_session_is_authenticated(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
            'password' => Hash::make('staff-password'),
        ]);

        $response = $this->post('/staff/login', [
            'email' => $user->email,
            'password' => 'staff-password',
        ]);

        $response->assertRedirect(route('staff.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get('/staff')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee(User::ROLE_DESIGNER);
    }

    public function test_invalid_password_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'password' => Hash::make('correct-password'),
        ]);

        $this->from('/staff/login')
            ->post('/staff/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_staff_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => false,
            'password' => Hash::make('staff-password'),
        ]);

        $this->from('/staff/login')
            ->post('/staff/login', [
                'email' => $user->email,
                'password' => 'staff-password',
            ])
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_staff_role_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => null,
            'is_active' => true,
            'password' => Hash::make('staff-password'),
        ]);

        $this->from('/staff/login')
            ->post('/staff/login', [
                'email' => $user->email,
                'password' => 'staff-password',
            ])
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_role_cannot_login(): void
    {
        $user = User::factory()->create([
            'role' => 'UNKNOWN',
            'is_active' => true,
            'password' => Hash::make('staff-password'),
        ]);

        $this->from('/staff/login')
            ->post('/staff/login', [
                'email' => $user->email,
                'password' => 'staff-password',
            ])
            ->assertRedirect('/staff/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_but_inactive_user_is_forbidden_from_staff_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/staff')
            ->assertForbidden();
    }

    public function test_authenticated_user_without_staff_role_is_forbidden_from_staff_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/staff')
            ->assertForbidden();
    }

    public function test_authenticated_staff_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/staff/login')
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_staff_can_logout(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_PACKING,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post('/staff/logout')
            ->assertRedirect(route('staff.login'));

        $this->assertGuest();
    }
}