<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_has_shared_mode_toggle_without_personalisation_picker(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('operations-theme.css')
            ->assertSee('Switch to dark mode')
            ->assertDontSee('Personalisation')
            ->assertDontSee('staff-sidebar-theme');
    }

    public function test_old_personalisation_endpoint_is_unavailable(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->put('/staff/theme', ['staff_theme' => 'modern_blue'])
            ->assertNotFound();

        $this->assertSame('default', $staff->fresh()->staff_theme);
    }
}
