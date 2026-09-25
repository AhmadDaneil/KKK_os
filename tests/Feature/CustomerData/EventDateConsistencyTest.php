<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDateConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_day_name_is_derived_from_the_selected_event_date(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'LELAKI' => [
                    'event' => [
                        'day_name' => 'Jumaat',
                        'event_date' => '2026-10-06',
                        'hijri_date' => '24 Rabiulakhir 1448H',
                    ],
                ],
            ],
        ]);

        $event = $saved->packageSides->firstWhere('side', 'LELAKI')->event;

        $this->assertSame('Selasa', $event->day_name);
        $this->assertSame('2026-10-06', $event->event_date->format('Y-m-d'));
        $this->assertSame('24 Rabiulakhir 1448H', $event->hijri_date);
    }

    public function test_customer_dashboard_loads_the_linked_gregorian_and_hijri_pickers(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);

        $this->get(app(GenerateOrderAccessLinkService::class)->generate($order))
            ->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));

        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
            ->assertOk()
            ->assertSee('data-event-day', false)
            ->assertSee('data-event-date', false)
            ->assertSee('data-hijri-date', false)
            ->assertSee('js/customer-event-date-pickers.js', false);
    }
}
