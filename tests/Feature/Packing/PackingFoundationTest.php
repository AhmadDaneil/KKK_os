<?php

namespace Tests\Feature\Packing;

use App\Models\User;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\StartDesignJobService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use App\Services\Packing\AssignPackingJobService;
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PackingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unprinted_order_cannot_initialize_packing(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Unprinted',
        ]);

        $this->expectException(RuntimeException::class);

        app(InitializePackingJobForOrderService::class)->initialize($order);
    }

    public function test_one_package_printed_order_creates_one_packing_job_with_one_item(): void
    {
        $order = $this->printedOrder(1, 'LELAKI');

        $job = app(InitializePackingJobForOrderService::class)->initialize($order);

        $this->assertDatabaseCount('packing_jobs', 1);
        $this->assertDatabaseCount('packing_job_items', 1);
        $this->assertSame('READY_FOR_PACKING', $job->status);
        $this->assertSame('LELAKI', $job->items->first()->side);
    }

    public function test_two_package_printed_order_creates_one_packing_job_with_two_items(): void
    {
        $order = $this->printedOrder(2);

        $job = app(InitializePackingJobForOrderService::class)->initialize($order);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('packing_jobs', 1);
        $this->assertDatabaseCount('packing_job_items', 2);

        $this->assertNotNull($job->items->firstWhere('side', 'LELAKI'));
        $this->assertNotNull($job->items->firstWhere('side', 'PEREMPUAN'));
    }

    public function test_packing_initialization_is_idempotent(): void
    {
        $order = $this->printedOrder(2);

        app(InitializePackingJobForOrderService::class)->initialize($order);
        app(InitializePackingJobForOrderService::class)->initialize($order->fresh());

        $this->assertDatabaseCount('packing_jobs', 1);
        $this->assertDatabaseCount('packing_job_items', 2);
        $this->assertDatabaseCount('packing_job_events', 1);
    }

    public function test_packing_cannot_complete_until_all_items_are_verified(): void
    {
        $order = $this->printedOrder(2);

        $job = app(InitializePackingJobForOrderService::class)->initialize($order);
        $job = app(StartPackingService::class)->start($job);

        app(VerifyPackingItemService::class)->verify(
            $job->items()->where('side', 'LELAKI')->first()
        );

        $this->expectException(RuntimeException::class);

        app(MarkPackingJobPackedService::class)->markPacked($job->fresh());
    }

    public function test_two_package_packing_completes_after_both_items_verified(): void
    {
        $order = $this->printedOrder(2);

        $job = app(InitializePackingJobForOrderService::class)->initialize($order);

        $staff = User::factory()->create();

        $job = app(AssignPackingJobService::class)->assign($job, $staff);
        $job = app(StartPackingService::class)->start($job, $staff);

        foreach ($job->items()->get() as $item) {
            app(VerifyPackingItemService::class)->verify($item, $staff);
        }

        $packed = app(MarkPackingJobPackedService::class)->markPacked(
            $job->fresh(),
            $staff
        );

        $this->assertSame('PACKED', $packed->status);
        $this->assertNotNull($packed->packed_at);
        $this->assertSame('PACKED', $order->fresh()->status);
        $this->assertTrue(
            $packed->items->every(fn ($item) => $item->verified_present)
        );
    }

    private function printedOrder(int $packageCount, ?string $singleSide = null)
    {
        $order = $this->paidOrder($packageCount, $singleSide);

        $printJobs = app(InitializePrintJobsForOrderService::class)->initialize($order);

        foreach ($printJobs as $printJob) {
            $printJob = app(StartPrintingService::class)->start($printJob);
            app(MarkPrintJobPrintedService::class)->markPrinted($printJob);
        }

        app(SyncOrderPrintStatusService::class)->sync($order->fresh());

        return $order->fresh();
    }

    private function paidOrder(int $packageCount, ?string $singleSide = null)
    {
        $order = $this->approvedOrder($packageCount, $singleSide);

        $payment = app(CreateBalancePaymentService::class)->create(
            $order->fresh(),
            $packageCount === 1 ? '250.00' : '500.00'
        );

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'PACK-REF-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'PACK-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(int $packageCount, ?string $singleSide = null)
    {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => 'Packing Test',
        ];

        if ($packageCount === 1) {
            $data['side'] = $singleSide;
        }

        $order = app(CreateOrderService::class)->create($data);

        $sides = [];

        foreach ($order->packageSides()->get() as $side) {
            $sides[$side->side] = [
                'design' => [
                    'design_code' => $side->side === 'LELAKI' ? 'L101' : 'P202',
                ],
                'parents' => [
                    'father_name' => 'Bapa ' . $side->side,
                    'mother_name' => 'Ibu ' . $side->side,
                ],
                'event' => [
                    'event_date' => '2026-12-20',
                    'meal_time' => '12:00',
                    'venue_name' => 'Dewan ' . $side->side,
                    'full_address' => 'Alamat ' . $side->side,
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
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => $sides,
            'fulfilment' => ['method' => 'PICKUP'],
        ]);

        $order = app(ConfirmOrderDetailsService::class)->confirm($order->fresh());

        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $designJobs = app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        foreach ($designJobs as $designJob) {
            $designJob = app(StartDesignJobService::class)->start($designJob);

            app(CreateArtworkVersionService::class)->create($designJob, [
                'storage_path' => "artworks/{$designJob->side}/v1.pdf",
                'original_filename' => "{$designJob->side}-v1.pdf",
                'mime_type' => 'application/pdf',
            ]);

            $designJob = app(MarkDesignReadyService::class)->markReady($designJob->fresh());
            app(ApproveArtworkService::class)->approve($designJob);
        }

        $order->update(['status' => 'DESIGN_APPROVED']);

        return $order->fresh();
    }
}
