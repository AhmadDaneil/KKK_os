<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_only_active_admin_can_open_admin_dashboard(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $designer = $this->staff(User::ROLE_DESIGNER);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        auth('admin')->logout();
        $this->actingAs($designer)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_exposes_actionable_admin_work_queues(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Memerlukan Perhatian')
            ->assertSee('Semakan Pembayaran')
            ->assertSee('Design Belum Assign')
            ->assertSee('Printing Belum Assign')
            ->assertSee('Packing Belum Assign');
    }

    public function test_admin_can_log_in_through_admin_portal(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN, ['password' => Hash::make('secure-password')]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'secure-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_non_admin_cannot_log_in_through_admin_portal(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER, ['password' => Hash::make('secure-password')]);

        $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $designer->email,
            'password' => 'secure-password',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Akaun ini ialah akaun staff. Sila log masuk melalui halaman Staff Login.']);

        $this->assertGuest('admin');
    }

    public function test_admin_and_staff_can_keep_separate_sessions_in_one_browser(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN, [
            'password' => Hash::make('admin-password'),
        ]);
        $designer = $this->staff(User::ROLE_DESIGNER, [
            'password' => Hash::make('staff-password'),
        ]);

        $this->post(route('staff.login.store'), [
            'email' => $designer->email,
            'password' => 'staff-password',
        ])->assertRedirect(route('staff.dashboard'));

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('staff.dashboard'))->assertOk();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
        $this->get(route('staff.dashboard'))->assertOk();
    }

    public function test_admin_can_create_and_manage_staff_account(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Designer Test',
            'email' => 'designer@kkk.local',
            'role' => User::ROLE_DESIGNER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect()->assertSessionHas('admin_success');

        $designer = User::where('email', 'designer@kkk.local')->firstOrFail();
        $this->assertTrue($designer->is_active);
        $this->assertSame(User::ROLE_DESIGNER, $designer->role);

        $this->actingAs($admin)->put(route('admin.staff.update', $designer), [
            'name' => 'Designer Updated',
            'email' => 'designer@kkk.local',
            'role' => User::ROLE_DESIGNER,
            'is_active' => '0',
        ])->assertRedirect()->assertSessionHas('admin_success');

        $this->assertFalse($designer->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $this->actingAs($admin)->from(route('admin.staff.index'))->put(route('admin.staff.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_ADMIN,
            'is_active' => '0',
        ])->assertRedirect(route('admin.staff.index'))->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_delete_another_staff_account(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $designer = $this->staff(User::ROLE_DESIGNER);

        $this->actingAs($admin)
            ->delete(route('admin.staff.destroy', $designer))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('admin_success');

        $this->assertDatabaseMissing('users', ['id' => $designer->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->from(route('admin.staff.index'))
            ->delete(route('admin.staff.destroy', $admin))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHasErrors('delete_staff');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function staff(string $role, array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
