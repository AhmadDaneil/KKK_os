<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Orders\ValidateOrderCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalReviewAndConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_order_cannot_be_confirmed(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Incomplete',
        ]);

        $this->expectException(ValidationException::class);

        app(ConfirmOrderDetailsService::class)->confirm($order);
    }

    public function test_complete_one_package_order_can_be_confirmed(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Complete',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => ['design_code' => 'A101'],
                    'parents' => [
                        'father_name' => 'Abdullah bin Ali',
                        'mother_name' => 'Fatimah binti Omar',
                    ],
                    'event' => [
                        'event_date' => '2026-12-20',
                        'meal_time' => '12:00',
                        'venue_name' => 'Dewan KKK',
                        'full_address' => 'Kuala Lumpur',
                        'contacts' => [
                            1 => ['contact_name' => 'Ahmad', 'contact_phone' => '0123456789'],
                            2 => ['contact_name' => 'Ali', 'contact_phone' => '0133456789'],
                            3 => ['contact_name' => 'Abu', 'contact_phone' => '0143456789'],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        $validation = app(ValidateOrderCompletionService::class)->validate($order->fresh());

        $this->assertTrue($validation['complete']);
        $this->assertSame([], $validation['missing']);

        $confirmed = app(ConfirmOrderDetailsService::class)->confirm($order->fresh());

        $this->assertSame('DETAILS_CONFIRMED', $confirmed->status);
        $this->assertNotNull($confirmed->details_confirmed_at);
        $this->assertDatabaseHas('order_confirmations', [
            'order_id' => $confirmed->id,
            'confirmation_version' => 'v1',
        ]);
    }

    public function test_two_package_order_requires_both_sides_complete(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'Two Package',
        ]);

        $validation = app(ValidateOrderCompletionService::class)->validate($order);

        $this->assertFalse($validation['complete']);

        $missingText = implode(' | ', $validation['missing']);

        $this->assertStringContainsString('Pakej Lelaki', $missingText);
        $this->assertStringContainsString('Pakej Perempuan', $missingText);
    }

    public function test_courier_requires_shipping_information(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
            'customer_name' => 'Courier Test',
        ]);

        $order->fulfilment()->update([
            'method' => 'COURIER',
        ]);

        $validation = app(ValidateOrderCompletionService::class)->validate($order->fresh());

        $missingText = implode(' | ', $validation['missing']);

        $this->assertStringContainsString('Courier: Nama penerima', $missingText);
        $this->assertStringContainsString('Courier: Telefon penerima', $missingText);
        $this->assertStringContainsString('Courier: Alamat penghantaran', $missingText);
    }
}
