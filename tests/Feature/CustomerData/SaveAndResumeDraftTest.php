<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaveAndResumeDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_draft_saves_normalized_data_and_keeps_order_incomplete(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Draft Test',
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => '  Muhammad   Syafiq  ',
                'bride_name' => ' Nur   Awanis ',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => ' a101 ',
                    ],
                    'parents' => [
                        'father_name' => '  Abdullah   bin Ali ',
                        'mother_name' => ' Aminah ',
                    ],
                    'event' => [
                        'venue_name' => ' Dewan   Seri ',
                        'full_address' => " No. 1   Jalan A \n Kuala Lumpur ",
                        'contacts' => [
                            1 => ['contact_name' => ' Ahmad ', 'contact_phone' => '012-345 6789'],
                        ],
                    ],
                ],
            ],
            'fulfilment' => [
                'method' => 'COURIER',
                'recipient_name' => ' Ali ',
                'recipient_phone' => '019-111 2222',
                'shipping_address' => " Jalan   Satu\n Kuala Lumpur ",
            ],
        ]);

        $this->assertSame('DETAILS_INCOMPLETE', $saved->status);
        $this->assertNull($saved->details_confirmed_at);
        $this->assertSame('Muhammad Syafiq', $saved->couples->first()->groom_name);
        $this->assertSame('Nur Awanis', $saved->couples->first()->bride_name);
        $this->assertSame('A101', $saved->packageSides->first()->design->design_code);
        $this->assertSame('0123456789', $saved->packageSides->first()->event->contacts->firstWhere('contact_number', 1)->contact_phone);
        $this->assertSame('0191112222', $saved->fulfilment->recipient_phone);
    }

    public function test_one_package_cannot_write_to_a_side_not_owned_by_the_order(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);

        $this->expectException(ValidationException::class);

        app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'PEREMPUAN' => [
                    'design' => ['design_code' => 'P100'],
                ],
            ],
        ]);
    }

    public function test_two_package_draft_keeps_lelaki_and_perempuan_data_separate(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'LELAKI' => ['design' => ['design_code' => 'l100']],
                'PEREMPUAN' => ['design' => ['design_code' => 'p200']],
            ],
        ]);

        $this->assertSame('L100', $saved->packageSides->firstWhere('side', 'LELAKI')->design->design_code);
        $this->assertSame('P200', $saved->packageSides->firstWhere('side', 'PEREMPUAN')->design->design_code);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_dashboard_form_can_save_and_resume_existing_values(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
        ]);

        $url = app(GenerateOrderAccessLinkService::class)->generate($order);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = $query['token'];

        $response = $this->post(route('orders.draft.update', [
            'orderId' => $order->order_id,
            'token' => $token,
        ]), [
            'couple' => [
                'groom_name' => 'Hakim',
                'bride_name' => 'Sarah',
            ],
            'sides' => [
                'PEREMPUAN' => [
                    'design' => ['design_code' => 'p500'],
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->get(route('orders.dashboard', [
            'orderId' => $order->order_id,
            'token' => $token,
        ]))
            ->assertOk()
            ->assertSee('Hakim')
            ->assertSee('Sarah')
            ->assertSee('P500');
    }
}
