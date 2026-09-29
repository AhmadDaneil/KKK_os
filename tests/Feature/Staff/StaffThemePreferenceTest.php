<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_save_a_personal_dashboard_theme(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Tema paparan')
            ->assertSee('Modern Blue')
            ->assertSee('Amber / Gold')
            ->assertSee('Charcoal / Lime');

        $this->actingAs($staff)
            ->put(route('staff.theme.update'), ['staff_theme' => 'modern_blue'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'staff_theme' => 'modern_blue',
        ]);
    }

    public function test_staff_cannot_save_an_unknown_theme(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->put(route('staff.theme.update'), ['staff_theme' => 'not-a-theme'])
            ->assertSessionHasErrors('staff_theme');
    }
}
