<?php

namespace Tests\Feature\Merge;

use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GenerateMergeJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfirmed_order_cannot_generate_merge_jobs(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Not Confirmed',
        ]);

        $this->expectException(RuntimeException::class);

        app(GenerateMergeJobsForOrderService::class)->generate($order);
    }

    public function test_one_package_generates_exactly_one_merge_job(): void
    {
        $order = $this->createConfirmedOrder(1, 'LELAKI');

        $jobs = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(1, $jobs);
        $this->assertDatabaseCount('merge_jobs', 1);

        $job = $jobs->first();

        $this->assertSame($order->order_id . '-L', $job->job_id);
        $this->assertSame('LELAKI', $job->side);
        $this->assertSame('L101', data_get($job->canonical_payload, 'generic.design_code'));
        $this->assertSame('Bapa Lelaki', data_get($job->canonical_payload, 'generic.namabapa'));
        $this->assertSame('Ibu Lelaki', data_get($job->canonical_payload, 'generic.namaibu'));
    }

    public function test_two_packages_generate_two_independent_merge_jobs(): void
    {
        $order = $this->createConfirmedOrder(2);

        $jobs = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(2, $jobs);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('merge_jobs', 2);

        $this->assertDatabaseHas('merge_jobs', [
            'job_id' => $order->order_id . '-L',
            'side' => 'LELAKI',
        ]);

        $this->assertDatabaseHas('merge_jobs', [
            'job_id' => $order->order_id . '-P',
            'side' => 'PEREMPUAN',
        ]);

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        $this->assertSame('L101', data_get($lelaki->canonical_payload, 'generic.design_code'));
        $this->assertSame('P202', data_get($perempuan->canonical_payload, 'generic.design_code'));

        $this->assertSame('Bapa Lelaki', data_get($lelaki->canonical_payload, 'generic.namabapa'));
        $this->assertSame('Bapa Perempuan', data_get($perempuan->canonical_payload, 'generic.namabapa'));
    }

    public function test_generation_is_idempotent_and_does_not_duplicate_jobs(): void
    {
        $order = $this->createConfirmedOrder(2);

        $first = app(GenerateMergeJobsForOrderService::class)->generate($order);
        $second = app(GenerateMergeJobsForOrderService::class)->generate($order);

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
        $this->assertDatabaseCount('merge_jobs', 2);
    }

    private function createConfirmedOrder(int $packageCount, ?string $singleSide = null)
    {
        $createData = [
            'package_count' => $packageCount,
            'customer_name' => 'Merge Test',
        ];

        if ($packageCount === 1) {
            $createData['side'] = $singleSide;
        }

        $order = app(CreateOrderService::class)->create($createData);

        $sides = [];

        foreach ($order->packageSides()->get() as $packageSide) {
            if ($packageSide->side === 'LELAKI') {
                $sides['LELAKI'] = $this->sidePayload(
                    'L101',
                    'Bapa Lelaki',
                    'Ibu Lelaki',
                    'Dewan Lelaki'
                );
            } else {
                $sides['PEREMPUAN'] = $this->sidePayload(
                    'P202',
                    'Bapa Perempuan',
                    'Ibu Perempuan',
                    'Dewan Perempuan'
                );
            }
        }

        app(SaveOrderDraftService::class)->save($order, [
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => $sides,
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }

    private function sidePayload(
        string $designCode,
        string $father,
        string $mother,
        string $venue,
    ): array {
        return [
            'design' => [
                'design_code' => $designCode,
            ],
            'parents' => [
                'father_name' => $father,
                'mother_name' => $mother,
            ],
            'event' => [
                'event_date' => '2026-12-20',
                'meal_time' => '12:00',
                'venue_name' => $venue,
                'full_address' => 'Alamat ' . $venue,
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
}
