<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_hijri_picker_uses_the_approved_malay_month_names(): void
    {
        $script = file_get_contents(public_path('js/customer-event-date-pickers.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "'Muharam', 'Safar', 'Rabiulawal', 'Rabiulakhir', 'Jamadilawal', 'Jamadilakhir'",
            $script,
        );
        $this->assertStringContainsString(
            "'Rejab', 'Syaaban', 'Ramadan', 'Syawal', 'Zulkaedah', 'Zulhijjah'",
            $script,
        );
        $this->assertStringNotContainsString('Dhu al-Hijjah', $script);
    }

    public function test_legacy_english_hijri_month_names_are_displayed_and_saved_in_malay(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);
        $event = $order->packageSides()->firstOrFail()->event;
        $event->update(['hijri_date' => '4 Dhu al-Hijjah 1446H']);

        $this->assertSame('4 Zulhijjah 1446H', $event->getRawOriginal('hijri_date'));
        $this->assertSame('4 Zulhijjah 1446H', $event->hijri_date);

        DB::table('order_events')->where('id', $event->id)->update([
            'hijri_date' => '24 Rabi al-Thani 1448H',
        ]);

        $this->assertSame('24 Rabiulakhir 1448H', $event->fresh()->hijri_date);
    }

    public function test_two_package_order_keeps_malay_hijri_month_names_for_both_sides(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'package_format' => 'SEPARATE',
            'first_event_side' => 'LELAKI',
        ]);

        $saved = app(SaveOrderDraftService::class)->save($order, [
            'sides' => [
                'LELAKI' => ['event' => ['hijri_date' => '1 Muharram 1448H']],
                'PEREMPUAN' => ['event' => ['hijri_date' => '30 Dhu al-Qadah 1448H']],
            ],
        ]);

        $this->assertSame(
            '1 Muharam 1448H',
            $saved->packageSides->firstWhere('side', 'LELAKI')->event->hijri_date,
        );
        $this->assertSame(
            '30 Zulkaedah 1448H',
            $saved->packageSides->firstWhere('side', 'PEREMPUAN')->event->hijri_date,
        );
    }
}
