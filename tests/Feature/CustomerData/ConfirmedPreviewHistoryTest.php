<?php

namespace Tests\Feature\CustomerData;

use App\Services\Orders\BuildFinalReviewService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Database\Seeders\KkkPackagePriceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmedPreviewHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_live_preview_is_preserved_as_reference_not_production_artwork(): void
    {
        $this->seed(KkkPackagePriceCatalogSeeder::class);
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'package_type' => 'PUTERI_MANIS',
            'side' => 'LELAKI',
            'customer_name' => 'Preview History',
        ]);
        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => ['groom_name' => 'Adam Hakim', 'bride_name' => 'Nur Aisyah'],
            'sides' => ['LELAKI' => [
                'card_quantity' => 200,
                'design' => ['theme' => 'CARTOON', 'design_code' => 'CKC-001'],
                'parents' => ['father_name' => 'Bapa Adam', 'mother_name' => 'Ibu Adam'],
                'event' => [
                    'event_date' => '2026-12-20', 'meal_time' => '12:00',
                    'venue_name' => 'Dewan KKK', 'full_address' => 'Kuala Lumpur',
                    'contacts' => [
                        1 => ['contact_name' => 'A', 'contact_phone' => '0111111111'],
                        2 => ['contact_name' => 'B', 'contact_phone' => '0122222222'],
                        3 => ['contact_name' => 'C', 'contact_phone' => '0133333333'],
                    ],
                ],
            ]],
            'fulfilment' => ['method' => 'PICKUP'],
        ]);
        $preview = app(BuildFinalReviewService::class)->build($order->fresh());

        $confirmed = app(ConfirmOrderDetailsService::class)->confirm($order->fresh(), $preview);
        $snapshot = $confirmed->confirmation->confirmed_snapshot;

        $this->assertSame('LIVE_PREVIEW_REFERENCE', $snapshot['snapshot_type']);
        $this->assertFalse($snapshot['is_production_artwork']);
        $this->assertSame('CKC-001', $snapshot['live_preview']['package_sides'][0]['design']['design_code']);
        $this->assertSame('Adam Hakim', $snapshot['live_preview']['couple']['groom_name']);
    }
}
