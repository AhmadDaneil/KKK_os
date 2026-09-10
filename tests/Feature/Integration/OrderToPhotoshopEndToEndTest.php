<?php

namespace Tests\Feature\Integration;

use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderToPhotoshopEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_customer_flow_reaches_valid_photoshop_csv(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'E2E One Package',
        ]);

        $this->saveCompleteOrder($order, [
            'LELAKI' => [
                'theme' => 'SONGKET',
                'design_code' => 'CKS-209',
                'father_name' => 'Ahmad Bin Ali',
                'mother_name' => 'Siti Binti Omar',
                'venue_name' => 'Dewan Seri',
                'address' => 'Kuala Lumpur',
            ],
        ], 200);

        $order = app(ConfirmOrderDetailsService::class)->confirm($order->fresh());

        $jobs = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(1, $jobs);
        $this->assertSame('LELAKI', $jobs->first()->side);
        $this->assertSame(200, (int) data_get(
            $jobs->first()->canonical_payload,
            'source.card_quantity'
        ));

        $path = storage_path('framework/testing/e2e-one-package.csv');
        @unlink($path);

        app(GeneratePhotoshopCsvForOrderService::class)->generate(
            $order->fresh(),
            $path
        );

        $rows = $this->readCsv($path);

        $this->assertCount(2, $rows);
        $this->assertCount(28, $rows[0]);
        $this->assertSame('noinvoice', $rows[0][0]);
        $this->assertSame('qtykad', $rows[0][1]);

        $this->assertSame($order->order_id, $rows[1][0]);
        $this->assertSame('200', $rows[1][1]);
        $this->assertSame('SONGKET', $rows[1][2]);
        $this->assertSame('CKS-209', $rows[1][3]);
        $this->assertSame('LELAKI', $rows[1][5]);
    }

    public function test_two_package_customer_flow_reaches_two_independent_photoshop_rows(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'customer_name' => 'E2E Two Package',
        ]);

        $this->saveCompleteOrder($order, [
            'LELAKI' => [
                'theme' => 'SONGKET',
                'design_code' => 'CKS-209',
                'father_name' => 'Ayah Lelaki',
                'mother_name' => 'Ibu Lelaki',
                'venue_name' => 'Dewan Lelaki',
                'address' => 'Alamat Lelaki',
            ],
            'PEREMPUAN' => [
                'theme' => 'ISLAMIC',
                'design_code' => 'CKI-820',
                'father_name' => 'Ayah Perempuan',
                'mother_name' => 'Ibu Perempuan',
                'venue_name' => 'Dewan Perempuan',
                'address' => 'Alamat Perempuan',
            ],
        ], 500);

        $order = app(ConfirmOrderDetailsService::class)->confirm($order->fresh());

        $jobs = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(2, $jobs);

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        $this->assertNotNull($lelaki);
        $this->assertNotNull($perempuan);

        $this->assertSame(
            500,
            (int) data_get($lelaki->canonical_payload, 'source.card_quantity')
        );
        $this->assertSame(
            500,
            (int) data_get($perempuan->canonical_payload, 'source.card_quantity')
        );

        $this->assertNotSame(
            data_get($lelaki->canonical_payload, 'design.design_code'),
            data_get($perempuan->canonical_payload, 'design.design_code')
        );

        $path = storage_path('framework/testing/e2e-two-package.csv');
        @unlink($path);

        app(GeneratePhotoshopCsvForOrderService::class)->generate(
            $order->fresh(),
            $path
        );

        $rows = $this->readCsv($path);

        $this->assertCount(3, $rows);
        $this->assertCount(28, $rows[0]);

        $header = array_flip($rows[0]);

        $bySide = [];

        foreach (array_slice($rows, 1) as $row) {
            $bySide[$row[$header['majlis']]] = $row;
        }

        $this->assertArrayHasKey('LELAKI', $bySide);
        $this->assertArrayHasKey('PEREMPUAN', $bySide);

        $this->assertSame('500', $bySide['LELAKI'][$header['qtykad']]);
        $this->assertSame('500', $bySide['PEREMPUAN'][$header['qtykad']]);

        $this->assertSame(
            'CKS-209',
            $bySide['LELAKI'][$header['designcode']]
        );
        $this->assertSame(
            'CKI-820',
            $bySide['PEREMPUAN'][$header['designcode']]
        );

        $this->assertNotSame(
            $bySide['LELAKI'][$header['alamat']],
            $bySide['PEREMPUAN'][$header['alamat']]
        );
    }

    private function saveCompleteOrder($order, array $sideDefinitions, int $cardQuantity): void
    {
        $sides = [];

        foreach ($order->packageSides()->get() as $side) {
            $definition = $sideDefinitions[$side->side];

            $sides[$side->side] = [
                'design' => [
                    'theme' => $definition['theme'],
                    'design_code' => $definition['design_code'],
                ],
                'parents' => [
                    'father_name' => $definition['father_name'],
                    'mother_name' => $definition['mother_name'],
                ],
                'event' => [
                    'day_name' => 'Sabtu',
                    'event_date' => '2026-12-26',
                    'hijri_date' => '16 REJAB 1448H',
                    'meal_time' => '12:00',
                    'bersanding_time' => '12:00',
                    'venue_name' => $definition['venue_name'],
                    'full_address' => $definition['address'],
                    'google_maps_url' => 'https://maps.app.goo.gl/example',
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
            ];
        }

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => $cardQuantity,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'groom_abbreviation' => 'Syafiq',
                'bride_name' => 'Nur Awanis',
                'bride_abbreviation' => 'Awanis',
            ],
            'sides' => $sides,
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);
    }

    private function readCsv(string $path): array
    {
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

        return $rows;
    }
}
