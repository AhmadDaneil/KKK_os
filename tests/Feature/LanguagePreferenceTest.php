<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguagePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_can_be_switched_to_english_and_persists_in_session(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.dashboard'))
            ->post(route('language.update'), ['locale' => 'en'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Every operation in one clear view.')
            ->assertSee('Staff &amp; Access', false)
            ->assertSee('Bahasa Melayu')
            ->assertDontSee('Staff Dashboard');
    }

    public function test_invalid_language_is_rejected(): void
    {
        $this->post(route('language.update'), ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');
    }

    public function test_landing_page_displays_the_language_switcher(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('action="'.route('language.update').'"', false)
            ->assertSee('English')
            ->assertDontSee('Kad kahwin 4 × 6');

        $this->withSession(['locale' => 'en'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Bahasa Melayu');
    }
}
