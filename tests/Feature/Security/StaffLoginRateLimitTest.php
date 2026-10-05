<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_is_rate_limited_after_ten_failed_attempts(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->from(route('staff.login'))
                ->post(route('staff.login.store'), [
                    'email' => 'unknown@example.test',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('staff.login'));
        }

        $this->post(route('staff.login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
