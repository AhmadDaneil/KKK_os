<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\BuildFinalReviewService;
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
            'card_quantity' => 200,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => 'A101',
                    ],
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
                            1 => [
                                'contact_name' => 'Ahmad',
                                'contact_phone' => '0123456789',
                            ],
                            2 => [
                                'contact_name' => 'Ali',
                                'contact_phone' => '0133456789',
                            ],
                            3 => [
                                'contact_name' => 'Abu',
                                'contact_phone' => '0143456789',
                            ],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        $validation = app(ValidateOrderCompletionService::class)
            ->validate($order->fresh());

        $this->assertTrue($validation['complete']);
        $this->assertSame([], $validation['missing']);

        $confirmed = app(ConfirmOrderDetailsService::class)
            ->confirm($order->fresh());

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

        $validation = app(ValidateOrderCompletionService::class)
            ->validate($order);

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

        $validation = app(ValidateOrderCompletionService::class)
            ->validate($order->fresh());

        $missingText = implode(' | ', $validation['missing']);

        $this->assertStringContainsString(
            'Courier: Nama penerima',
            $missingText
        );

        $this->assertStringContainsString(
            'Courier: Telefon penerima',
            $missingText
        );

        $this->assertStringContainsString(
            'Courier: Alamat penghantaran',
            $missingText
        );
    }

    public function test_confirmed_order_cannot_be_confirmed_again(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Confirmation Guard',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => 'A101',
                    ],
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
                            1 => [
                                'contact_name' => 'Ahmad',
                                'contact_phone' => '0123456789',
                            ],
                            2 => [
                                'contact_name' => 'Ali',
                                'contact_phone' => '0133456789',
                            ],
                            3 => [
                                'contact_name' => 'Abu',
                                'contact_phone' => '0143456789',
                            ],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        $confirmed = app(ConfirmOrderDetailsService::class)
            ->confirm($order->fresh());

        $originalConfirmedAt = $confirmed->details_confirmed_at;
        $originalConfirmationAt = $confirmed->confirmation->confirmed_at;

        try {
            app(ConfirmOrderDetailsService::class)
                ->confirm($confirmed->fresh());

            $this->fail(
                'Confirmed order was allowed to be confirmed again.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'order',
                $exception->errors()
            );
        }

        $confirmed->refresh();
        $confirmed->load('confirmation');

        $this->assertSame(
            'DETAILS_CONFIRMED',
            $confirmed->status
        );

        $this->assertTrue(
            $originalConfirmedAt->equalTo(
                $confirmed->details_confirmed_at
            )
        );

        $this->assertTrue(
            $originalConfirmationAt->equalTo(
                $confirmed->confirmation->confirmed_at
            )
        );
    }

    public function test_final_review_contract_includes_complete_customer_review_data(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'Final Review Contract',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 500,

            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'groom_abbreviation' => 'Syafiq',
                'bride_name' => 'Nur Awanis',
                'bride_abbreviation' => 'Awanis',
            ],

            'second_couple' => [
                'groom_name' => 'Ahmad Firdaus',
                'groom_abbreviation' => 'Firdaus',
                'bride_name' => 'Siti Hajar',
                'bride_abbreviation' => 'Hajar',
            ],

            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'theme' => 'MINIMALIST',
                        'design_code' => 'L100',
                        'card_title' => 'Walimatul Urus',
                    ],
                    'parents' => [
                        'father_name' => 'Abdullah bin Ali',
                        'mother_name' => 'Fatimah binti Omar',
                    ],
                    'event' => [
                        'day_name' => 'Ahad',
                        'event_date' => '2026-12-20',
                        'hijri_date' => '10 Rejab 1448H',
                        'meal_time' => '12:00',
                        'bersanding_time' => '13:30',
                        'venue_name' => 'Dewan Lelaki',
                        'full_address' => 'Kuala Lumpur',
                        'google_maps_url' => 'https://maps.google.com/?q=lelaki',
                        'contacts' => [
                            1 => [
                                'contact_name' => 'Ahmad',
                                'contact_phone' => '0123456789',
                            ],
                            2 => [
                                'contact_name' => 'Ali',
                                'contact_phone' => '0133456789',
                            ],
                            3 => [
                                'contact_name' => 'Abu',
                                'contact_phone' => '0143456789',
                            ],
                        ],
                    ],
                ],

                'PEREMPUAN' => [
                    'design' => [
                        'theme' => 'SONGKET',
                        'design_code' => 'P200',
                        'card_title' => 'Majlis Perkahwinan',
                    ],
                    'parents' => [
                        'father_name' => 'Rahman bin Musa',
                        'mother_name' => 'Zainab binti Karim',
                    ],
                    'event' => [
                        'day_name' => 'Sabtu',
                        'event_date' => '2026-12-26',
                        'hijri_date' => '16 Rejab 1448H',
                        'meal_time' => '11:30',
                        'bersanding_time' => '13:00',
                        'venue_name' => 'Dewan Perempuan',
                        'full_address' => 'Melaka',
                        'google_maps_url' => 'https://maps.google.com/?q=perempuan',
                        'contacts' => [
                            1 => [
                                'contact_name' => 'Aisyah',
                                'contact_phone' => '0111111111',
                            ],
                            2 => [
                                'contact_name' => 'Mariam',
                                'contact_phone' => '0122222222',
                            ],
                            3 => [
                                'contact_name' => 'Salmah',
                                'contact_phone' => '0133333333',
                            ],
                        ],
                    ],
                ],
            ],

            'fulfilment' => [
                'method' => 'COURIER',
                'recipient_name' => 'Muhammad Syafiq',
                'recipient_phone' => '0129999999',
                'shipping_address' => 'No. 1, Jalan KKK, Melaka',
            ],
        ]);

        $review = app(BuildFinalReviewService::class)
            ->build($order->fresh());

        $this->assertSame(
            $order->order_id,
            $review['order_id']
        );

        $this->assertSame(
            'Final Review Contract',
            $review['customer_name']
        );

        $this->assertSame(
            2,
            $review['package_count']
        );

        $this->assertSame(
            500,
            $review['card_quantity']
        );

        $this->assertSame(
            'Muhammad Syafiq',
            $review['couple']['groom_name']
        );

        $this->assertSame(
            'Syafiq',
            $review['couple']['groom_abbreviation']
        );

        $this->assertSame(
            'Nur Awanis',
            $review['couple']['bride_name']
        );

        $this->assertSame(
            'Awanis',
            $review['couple']['bride_abbreviation']
        );

        $this->assertSame(
            'Ahmad Firdaus',
            $review['second_couple']['groom_name']
        );

        $this->assertSame(
            'Hajar',
            $review['second_couple']['bride_abbreviation']
        );

        $lelaki = collect($review['package_sides'])
            ->firstWhere('side', 'LELAKI');

        $perempuan = collect($review['package_sides'])
            ->firstWhere('side', 'PEREMPUAN');

        $this->assertNotNull($lelaki);
        $this->assertNotNull($perempuan);

        $this->assertSame(
            'MINIMALIST',
            $lelaki['design']['theme']
        );

        $this->assertSame(
            'L100',
            $lelaki['design']['design_code']
        );

        $this->assertSame(
            'Walimatul Urus',
            $lelaki['design']['card_title']
        );

        $this->assertFalse(
            $lelaki['design']['has_card_image']
        );

        $this->assertSame(
            'Ahad',
            $lelaki['event']['day_name']
        );

        $this->assertSame(
            '2026-12-20',
            $lelaki['event']['event_date']
        );

        $this->assertSame(
            '10 Rejab 1448H',
            $lelaki['event']['hijri_date']
        );

        $this->assertSame(
            '13:30',
            $lelaki['event']['bersanding_time']
        );

        $this->assertCount(
            3,
            $lelaki['event']['contacts']
        );

        $this->assertSame(
            'SONGKET',
            $perempuan['design']['theme']
        );

        $this->assertSame(
            'P200',
            $perempuan['design']['design_code']
        );

        $this->assertSame(
            'Majlis Perkahwinan',
            $perempuan['design']['card_title']
        );

        $this->assertSame(
            'Sabtu',
            $perempuan['event']['day_name']
        );

        $this->assertCount(
            3,
            $perempuan['event']['contacts']
        );

        $this->assertSame(
            'COURIER',
            $review['fulfilment']['method']
        );

        $this->assertSame(
            'Muhammad Syafiq',
            $review['fulfilment']['recipient_name']
        );

        $this->assertArrayNotHasKey(
            'card_image_path',
            $lelaki['design']
        );
    }

    public function test_order_without_card_quantity_cannot_be_confirmed(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
            'customer_name' => 'Missing Quantity',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => 'Adam Hakim',
                'bride_name' => 'Xena',
            ],
            'sides' => [
                'PEREMPUAN' => [
                    'design' => [
                        'design_code' => 'CKS-218',
                    ],
                    'parents' => [
                        'father_name' => 'Bapa Perempuan',
                        'mother_name' => 'Ibu Perempuan',
                    ],
                    'event' => [
                        'event_date' => '2026-10-04',
                        'meal_time' => '12:00',
                        'venue_name' => 'Dewan Seri Kuching',
                        'full_address' => 'Kuching, Sarawak',
                        'contacts' => [
                            1 => [
                                'contact_name' => 'Contact 1',
                                'contact_phone' => '0111111111',
                            ],
                            2 => [
                                'contact_name' => 'Contact 2',
                                'contact_phone' => '0122222222',
                            ],
                            3 => [
                                'contact_name' => 'Contact 3',
                                'contact_phone' => '0133333333',
                            ],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        $validation = app(ValidateOrderCompletionService::class)
            ->validate($order->fresh());

        $this->assertFalse($validation['complete']);
        $this->assertContains(
            'Kuantiti kad',
            $validation['missing']
        );

        try {
            app(ConfirmOrderDetailsService::class)
                ->confirm($order->fresh());

            $this->fail(
                'Order without card quantity was allowed to be confirmed.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'order',
                $exception->errors()
            );
        }

        $order->refresh();

        $this->assertSame(
            'DETAILS_INCOMPLETE',
            $order->status
        );

        $this->assertNull(
            $order->details_confirmed_at
        );
    }
}
