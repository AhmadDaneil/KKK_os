<?php

namespace Tests\Feature\Merge;

use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardQuantityOptionATest extends TestCase
{
    use RefreshDatabase;

    public function test_two_package_order_uses_one_order_quantity_for_both_merge_jobs_and_csv_rows(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'Quantity Option A',
        ]);

        $order->forceFill(['card_quantity' => 500])->save();

        $sides = [];

        foreach ($order->packageSides()->get() as $side) {
            $isLelaki = $side->side === 'LELAKI';

            $sides[$side->side] = [
                'design' => [
                    'theme' => $isLelaki ? 'SONGKET' : 'ISLAMIC',
                    'design_code' => $isLelaki ? 'CKS-209' : 'CKI-820',
                ],
                'parents' => [
                    'father_name' => $isLelaki ? 'Bapa Lelaki' : 'Bapa Perempuan',
                    'mother_name' => $isLelaki ? 'Ibu Lelaki' : 'Ibu Perempuan',
                ],
                'event' => [
                    'day_name' => 'Sabtu',
                    'event_date' => '2026-12-26',
                    'hijri_date' => '16 REJAB 1448H',
                    'meal_time' => '12:00',
                    'bersanding_time' => '12:00',
                    'venue_name' => $isLelaki ? 'Dewan Lelaki' : 'Dewan Perempuan',
                    'full_address' => $isLelaki ? 'Alamat Lelaki' : 'Alamat Perempuan',
                    'google_maps_url' => 'https://maps.app.goo.gl/example',
                    'contacts' => [
                        1 => ['contact_name' => 'Contact 1', 'contact_phone' => '0111111111'],
                        2 => ['contact_name' => 'Contact 2', 'contact_phone' => '0122222222'],
                        3 => ['contact_name' => 'Contact 3', 'contact_phone' => '0133333333'],
                    ],
                ],
            ];
        }

        app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'groom_abbreviation' => 'Syafiq',
                'bride_name' => 'Nur Awanis',
                'bride_abbreviation' => 'Awanis',
            ],
            'sides' => $sides,
            'fulfilment' => ['method' => 'PICKUP'],
        ]);

        // Save Draft must not destroy the already supplied order-level quantity.
        $order = $order->fresh();
        $this->assertSame(500, (int) $order->card_quantity);

        $order = app(ConfirmOrderDetailsService::class)->confirm($order);

        $jobs = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(2, $jobs);
        $this->assertSame(
            500,
            (int) data_get($jobs->firstWhere('side', 'LELAKI')->canonical_payload, 'source.card_quantity')
        );
        $this->assertSame(
            500,
            (int) data_get($jobs->firstWhere('side', 'PEREMPUAN')->canonical_payload, 'source.card_quantity')
        );

        $path = storage_path('framework/testing/photoshop-option-a.csv');
        @unlink($path);

        app(GeneratePhotoshopCsvForOrderService::class)->generate($order->fresh(), $path);

        $handle = fopen($path, 'rb');
        $bom = fread($handle, 3);

        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        $this->assertCount(3, $rows);
        $this->assertSame('500', $rows[1][1]);
        $this->assertSame('500', $rows[2][1]);
    }
}
