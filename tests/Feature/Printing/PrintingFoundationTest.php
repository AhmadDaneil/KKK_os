<?php

namespace Tests\Feature\Printing;

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
use App\Services\Printing\AssignPrintJobService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PrintingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_order_cannot_initialize_print_jobs(): void
    {
        $order = $this->approvedOrder(1, 'LELAKI');

        $this->expectException(RuntimeException::class);

        app(InitializePrintJobsForOrderService::class)->initialize($order);
    }

    public function test_one_package_paid_order_creates_one_print_job(): void
    {
        $order = $this->paidOrder(1, 'LELAKI');

        $jobs = app(InitializePrintJobsForOrderService::class)->initialize($order);

        $this->assertCount(1, $jobs);
        $this->assertDatabaseCount('print_jobs', 1);
        $this->assertSame('LELAKI', $jobs->first()->side);
        $this->assertSame('READY_FOR_PRINT', $jobs->first()->status);
    }

    public function test_two_package_paid_order_creates_two_independent_print_jobs(): void
    {
        $order = $this->paidOrder(2);

        $jobs = app(InitializePrintJobsForOrderService::class)->initialize($order);

        $this->assertCount(2, $jobs);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('print_jobs', 2);

        $this->assertNotNull($jobs->firstWhere('side', 'LELAKI'));
        $this->assertNotNull($jobs->firstWhere('side', 'PEREMPUAN'));
    }

    public function test_print_job_initialization_is_idempotent(): void
    {
        $order = $this->paidOrder(2);

        app(InitializePrintJobsForOrderService::class)->initialize($order);
        app(InitializePrintJobsForOrderService::class)->initialize($order->fresh());

        $this->assertDatabaseCount('print_jobs', 2);
        $this->assertDatabaseCount('print_job_events', 2);
    }

    public function test_print_job_can_be_assigned_started_and_marked_printed(): void
    {
        $order = $this->paidOrder(1, 'PEREMPUAN');

        $job = app(InitializePrintJobsForOrderService::class)
            ->initialize($order)
            ->first();

        $staff = User::factory()->create();

        $job = app(AssignPrintJobService::class)->assign($job, $staff);

        $this->assertSame($staff->id, $job->assigned_user_id);
        $this->assertNotNull($job->assigned_at);

        $job = app(StartPrintingService::class)->start($job, $staff);

        $this->assertSame('PRINTING', $job->status);
        $this->assertNotNull($job->started_at);

        $job = app(MarkPrintJobPrintedService::class)->markPrinted($job, $staff);

        $this->assertSame('PRINTED', $job->status);
        $this->assertNotNull($job->printed_at);
    }

    public function test_two_package_order_only_becomes_printed_after_both_jobs_complete(): void
    {
        $order = $this->paidOrder(2);

        $jobs = app(InitializePrintJobsForOrderService::class)->initialize($order);

        foreach ($jobs as $job) {
            app(StartPrintingService::class)->start($job);
        }

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        app(MarkPrintJobPrintedService::class)->markPrinted($lelaki->fresh());

        $partial = app(SyncOrderPrintStatusService::class)->sync($order->fresh());

        $this->assertSame('PRINTING', $partial->status);

        app(MarkPrintJobPrintedService::class)->markPrinted($perempuan->fresh());

        $complete = app(SyncOrderPrintStatusService::class)->sync($order->fresh());

        $this->assertSame('PRINTED', $complete->status);
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
            'provider_reference' => 'PRINT-REF-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'PRINT-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(int $packageCount, ?string $singleSide = null)
    {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => 'Printing Test',
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
