<?php

namespace Tests\Feature\Merge;

use App\Contracts\Photoshop\CardQuantityProviderContract;
use App\Models\MergeJob;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Photoshop\ExportPhotoshopAutoMergeCsvService;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PhotoshopProductionExportWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolved_quantity_provider_blocks_production_export(): void
    {
        $order = $this->confirmedOrder(1, 'LELAKI');

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no valid approved card quantity');

        app(GeneratePhotoshopCsvForOrderService::class)->generate(
            $order->fresh(),
            storage_path('framework/testing/photoshop-blocked.csv'),
        );
    }

    public function test_one_package_exports_exactly_one_data_row_when_quantity_provider_is_available(): void
    {
        $order = $this->confirmedOrder(1, 'PEREMPUAN');

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $service = $this->serviceWithQuantity(200);

        $path = storage_path('framework/testing/photoshop-1-package.csv');
        @unlink($path);

        $service->generate($order->fresh(), $path);

        $rows = $this->readCsv($path);

        $this->assertCount(2, $rows); // header + 1 data row
        $this->assertCount(28, $rows[0]);
        $this->assertSame('200', $rows[1][1]);
        $this->assertSame('PEREMPUAN', $rows[1][5]);
    }

    public function test_two_package_exports_two_independent_rows_without_overwrite(): void
    {
        $order = $this->confirmedOrder(2);

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $service = $this->serviceWithQuantityResolver(
            fn (MergeJob $job) => $job->side === 'LELAKI' ? 300 : 500
        );

        $path = storage_path('framework/testing/photoshop-2-package.csv');
        @unlink($path);

        $service->generate($order->fresh(), $path);

        $rows = $this->readCsv($path);

        $this->assertCount(3, $rows); // header + L + P

        $data = collect(array_slice($rows, 1))
            ->mapWithKeys(fn ($row) => [$row[5] => $row]);

        $this->assertSame(['LELAKI', 'PEREMPUAN'], $data->keys()->sort()->values()->all());
        $this->assertSame('300', $data['LELAKI'][1]);
        $this->assertSame('500', $data['PEREMPUAN'][1]);

        $this->assertNotSame(
            $data['LELAKI'][3],
            $data['PEREMPUAN'][3],
            'Two package sides should preserve independent design codes in the CSV.'
        );
    }

    public function test_export_rejects_order_when_merge_job_count_does_not_match_package_count(): void
    {
        $order = $this->confirmedOrder(2);

        // Deliberately do not generate merge jobs.

        $service = $this->serviceWithQuantity(200);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected 2 merge job(s), but 0 exist');

        $service->generate(
            $order,
            storage_path('framework/testing/photoshop-invalid-count.csv'),
        );
    }

    private function serviceWithQuantity(int $quantity): GeneratePhotoshopCsvForOrderService
    {
        return $this->serviceWithQuantityResolver(
            fn (MergeJob $job) => $quantity
        );
    }

    private function serviceWithQuantityResolver(callable $resolver): GeneratePhotoshopCsvForOrderService
    {
        $provider = new class($resolver) implements CardQuantityProviderContract {
            public function __construct(private $resolver)
            {
            }

            public function quantityFor(MergeJob $mergeJob): int
            {
                return (int) ($this->resolver)($mergeJob);
            }
        };

        return new GeneratePhotoshopCsvForOrderService(
            app(ExportPhotoshopAutoMergeCsvService::class),
            $provider,
        );
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

    private function confirmedOrder(int $packageCount, ?string $singleSide = null)
    {
        $create = [
            'package_count' => $packageCount,
            'customer_name' => 'Photoshop Stage 3C',
        ];

        if ($packageCount === 1) {
            $create['side'] = $singleSide;
        }

        $order = app(CreateOrderService::class)->create($create);

        $sides = [];

        foreach ($order->packageSides()->get() as $side) {
            $isLelaki = $side->side === 'LELAKI';

            $sides[$side->side] = [
                'design' => [
                    'theme' => $isLelaki ? 'SONGKET' : 'ISLAMIC',
                    'design_code' => $isLelaki ? 'CKS-209' : 'CKI-820',
                ],
                'parents' => [
                    'father_name' => $isLelaki ? 'Ali Bin Abu' : 'Zamri Bin Sulong',
                    'mother_name' => $isLelaki ? 'Aminah Binti Minah' : 'Neszlipah Binti Mohamed',
                ],
                'event' => [
                    'day_name' => 'Sabtu',
                    'event_date' => '2026-12-26',
                    'hijri_date' => '16 REJAB 1448H',
                    'meal_time' => '12.00 PM - 4.00 PM',
                    'bersanding_time' => '12.00 PM',
                    'venue_name' => 'Dewan Test',
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

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }
}
