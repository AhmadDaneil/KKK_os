<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffGuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_opens_without_login_in_read_only_overview_mode(): void
    {
        $this->assertGuest();

        $this->get('/staff')
            ->assertOk()
            ->assertSee('Staff Dashboard')
            ->assertSee('Staff Operations Overview')
            ->assertSee('Operation Management')
            ->assertSee('Design')
            ->assertSee('Production');

        $this->assertGuest();
    }

    public function test_login_and_logout_endpoints_no_longer_exist(): void
    {
        $this->get('/staff/login')->assertNotFound();
        $this->post('/staff/login')->assertNotFound();
        $this->post('/staff/logout')->assertNotFound();
    }

    public function test_staff_dashboard_does_not_require_an_admin_account(): void
    {
        User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => false,
        ]);

        $this->get('/staff')->assertOk();
    }

    public function test_unknown_staff_action_resource_is_not_exposed(): void
    {
        $this->post('/staff/design-jobs/999/start')->assertNotFound();
    }

    public function test_existing_active_staff_context_is_preserved_for_role_specific_views(): void
    {
        $designer = User::factory()->create([
            'name' => 'Designer Context',
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($designer)
            ->get('/staff')
            ->assertOk()
            ->assertSee('Designer Context')
            ->assertSee('Design Queue')
            ->assertDontSee('Printing Queue')
            ->assertDontSee('Packing Queue');
    }
}
