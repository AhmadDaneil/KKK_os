<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class WabotBookingIntentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('services.wabot.token', 'wabot-test-token');
    }

    public function test_returns_401_without_wabot_token(): void
    {
        $this->postJson(route('api.wabot.booking-intent.store'), ['message' => 'nak tempah'])
            ->assertUnauthorized();
    }

    public function test_returns_422_without_message(): void
    {
        $this->postJson(route('api.wabot.booking-intent.store'), [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_common_booking_phrases_prompt_wabot_to_ask_for_billplz_method(): void
    {
        foreach (['nak tempah', 'BOOKING', 'nak order', 'pay deposit'] as $message) {
            $this->postJson(route('api.wabot.booking-intent.store'), ['message' => $message], $this->headers())
                ->assertOk()
                ->assertJsonPath('booking_requested', true)
                ->assertJsonPath('next_action', 'ASK_PAYMENT_METHOD')
                ->assertJsonPath('payment_method_prompt', 'Anda ingin membayar deposit tempahan melalui Perbankan Dalam Talian atau DuitNow QR?')
                ->assertJsonPath('payment_options.0', 'ONLINE_BANKING')
                ->assertJsonPath('payment_options.1', 'DUITNOW_QR');
        }
    }

    public function test_general_questions_and_partial_words_do_not_start_booking_payment(): void
    {
        foreach (['How much does this package cost?', 'Is the bookcase included?'] as $message) {
            $this->postJson(route('api.wabot.booking-intent.store'), ['message' => $message], $this->headers())
                ->assertOk()
                ->assertJsonPath('booking_requested', false)
                ->assertJsonPath('next_action', null);
        }
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer wabot-test-token'];
    }
}
