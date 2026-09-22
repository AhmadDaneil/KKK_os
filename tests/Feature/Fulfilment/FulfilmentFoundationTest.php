<?php

namespace Tests\Feature\Fulfilment;

use App\Services\Design\ApproveArtworkService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\StartDesignJobService;
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Fulfilment\MarkPickupCollectedService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use App\Services\Fulfilment\CompleteCourierFulfilmentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FulfilmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpackaged_order_cannot_initialize_fulfilment(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Not Packed',
        ]);

        $this->expectException(RuntimeException::class);

        app(InitializeFulfilmentJobForOrderService::class)->initialize($order);
    }

    public function test_pickup_order_creates_one_ready_fulfilment_job(): void
    {
        $order = $this->packedOrder(1, 'PICKUP', 'LELAKI');

        $job = app(InitializeFulfilmentJobForOrderService::class)->initialize($order);

        $this->assertDatabaseCount('fulfilment_jobs', 1);
        $this->assertSame('PICKUP', $job->method);
        $this->assertSame('READY', $job->status);
        $this->assertSame('PACKED', $order->fresh()->status);
    }

    public function test_courier_order_creates_one_ready_fulfilment_job(): void
    {
        $order = $this->packedOrder(2, 'COURIER');

        $job = app(InitializeFulfilmentJobForOrderService::class)->initialize($order);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('fulfilment_jobs', 1);
        $this->assertSame('COURIER', $job->method);
        $this->assertSame('READY', $job->status);
    }

    public function test_initialization_is_idempotent(): void
    {
        $order = $this->packedOrder(2, 'PICKUP');

        $first = app(InitializeFulfilmentJobForOrderService::class)->initialize($order);
        $second = app(InitializeFulfilmentJobForOrderService::class)->initialize($order->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('fulfilment_jobs', 1);
        $this->assertDatabaseCount('fulfilment_job_events', 1);
    }

    public function test_pickup_collection_completes_order(): void
    {
        $order = $this->packedOrder(1, 'PICKUP', 'PEREMPUAN');

        $job = app(InitializeFulfilmentJobForOrderService::class)->initialize($order);

        $job = app(MarkPickupCollectedService::class)->collect(
            $job,
            'PICKUP-TEST-001'
        );

        $this->assertSame('COLLECTED', $job->status);
        $this->assertNotNull($job->collected_at);
        $this->assertSame('COMPLETED', $order->fresh()->status);
    }

        public function test_courier_completion_requires_packing_proof(): void
    {
        Storage::fake('local');

        $order = $this->packedOrder(1, 'COURIER', 'LELAKI');

        $job = app(InitializeFulfilmentJobForOrderService::class)
            ->initialize($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            "Courier job {$job->id} requires packing proof before completion."
        );

        app(CompleteCourierFulfilmentService::class)->complete(
            $job,
            'POS LAJU',
            'TRACK-001'
        );
    }

    public function test_courier_completion_requires_courier_and_tracking_number(): void
    {
        Storage::fake('local');

        $order = $this->packedOrder(1, 'COURIER', 'LELAKI');

        $job = app(InitializeFulfilmentJobForOrderService::class)
            ->initialize($order);

        $proofPath = "packing-proofs/{$job->packing_job_id}/proof.jpg";

        Storage::disk('local')->put($proofPath, 'test-proof');

        $job->packingJob()->update([
            'proof_storage_path' => $proofPath,
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Courier provider and tracking number are required before courier completion.'
        );

        app(CompleteCourierFulfilmentService::class)->complete(
            $job,
            null,
            null
        );
    }

    public function test_one_package_courier_completion_completes_order(): void
    {
        Storage::fake('local');

        $order = $this->packedOrder(1, 'COURIER', 'LELAKI');

        $job = app(InitializeFulfilmentJobForOrderService::class)
            ->initialize($order);

        $proofPath = "packing-proofs/{$job->packing_job_id}/proof.jpg";

        Storage::disk('local')->put($proofPath, 'test-proof');

        $job->packingJob()->update([
            'proof_storage_path' => $proofPath,
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
        ]);

        $job = app(CompleteCourierFulfilmentService::class)->complete(
            $job,
            'POS LAJU',
            'TRACK-001'
        );

        $this->assertSame('COMPLETED', $job->status);
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame('POS LAJU', $job->courier_provider);
        $this->assertSame('TRACK-001', $job->tracking_number);
        $this->assertNotNull($job->shipped_at);
        $this->assertNull($job->delivered_at);

        $event = $job->events()
            ->where('event_type', 'COURIER_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('READY', $event->from_status);
        $this->assertSame('COMPLETED', $event->to_status);
        $this->assertSame('POS LAJU', $event->metadata['courier_provider']);
        $this->assertSame('TRACK-001', $event->metadata['tracking_number']);
        $this->assertSame(
            $proofPath,
            $event->metadata['packing_proof_storage_path']
        );
    }

    public function test_two_package_courier_completion_is_one_business_fulfilment_and_idempotent(): void
    {
        Storage::fake('local');

        $order = $this->packedOrder(2, 'COURIER');

        $this->assertSame(
            2,
            $order->packingJob->items()->count()
        );

        $job = app(InitializeFulfilmentJobForOrderService::class)
            ->initialize($order);

        $proofPath = "packing-proofs/{$job->packing_job_id}/proof.jpg";

        Storage::disk('local')->put($proofPath, 'test-proof');

        $job->packingJob()->update([
            'proof_storage_path' => $proofPath,
            'proof_original_name' => 'proof.jpg',
            'proof_mime_type' => 'image/jpeg',
        ]);

        $service = app(CompleteCourierFulfilmentService::class);

        $first = $service->complete(
            $job,
            'POS LAJU',
            'TRACK-002'
        );

        $second = $service->complete(
            $first,
            'POS LAJU',
            'TRACK-002'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame('COMPLETED', $second->status);
        $this->assertSame('COMPLETED', $order->fresh()->status);

        $this->assertDatabaseCount('fulfilment_jobs', 1);

        $this->assertSame(
            1,
            $second->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    private function packedOrder(
        int $packageCount,
        string $fulfilmentMethod,
        ?string $singleSide = null
    ) {
        $order = $this->paidOrder($packageCount, $fulfilmentMethod, $singleSide);

        $printJobs = app(InitializePrintJobsForOrderService::class)->initialize($order);

        foreach ($printJobs as $printJob) {
            $printJob = app(StartPrintingService::class)->start($printJob);
            app(MarkPrintJobPrintedService::class)->markPrinted($printJob);
        }

        app(SyncOrderPrintStatusService::class)->sync($order->fresh());

        $packingJob = app(InitializePackingJobForOrderService::class)
            ->initialize($order->fresh());

        $packingJob = app(StartPackingService::class)->start($packingJob);

        foreach ($packingJob->items()->get() as $item) {
            app(VerifyPackingItemService::class)->verify($item);
        }

        app(MarkPackingJobPackedService::class)->markPacked($packingJob->fresh());

        return $order->fresh();
    }

    private function paidOrder(
        int $packageCount,
        string $fulfilmentMethod,
        ?string $singleSide = null
    ) {
        $order = $this->approvedOrder($packageCount, $fulfilmentMethod, $singleSide);

        $payment = app(CreateBalancePaymentService::class)->create(
            $order->fresh(),
            $packageCount === 1 ? '250.00' : '500.00'
        );

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'FULFIL-REF-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'FULFIL-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(
        int $packageCount,
        string $fulfilmentMethod,
        ?string $singleSide = null
    ) {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => 'Fulfilment Test',
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

        $fulfilment = ['method' => $fulfilmentMethod];

        if ($fulfilmentMethod === 'COURIER') {
            $fulfilment += [
                'recipient_name' => 'Penerima Test',
                'recipient_phone' => '0123456789',
                'shipping_address' => 'Alamat Courier Test',
            ];
        }

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => $sides,
            'fulfilment' => $fulfilment,
        ]);

        $order = app(ConfirmOrderDetailsService::class)->confirm($order->fresh());

        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $designJobs = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh());

        foreach ($designJobs as $designJob) {
            $designJob = app(StartDesignJobService::class)->start($designJob);

            app(CreateArtworkVersionService::class)->create($designJob, [
            'storage_path' => "artworks/{$designJob->side}/v1.pdf",
            'preview_storage_path' => "artworks/{$designJob->side}/v1-preview.png",
            'original_filename' => "{$designJob->side}-v1.pdf",
            'mime_type' => 'application/pdf',
        ]);

            $designJob = app(MarkDesignReadyService::class)
                ->markReady($designJob->fresh());

            app(ApproveArtworkService::class)->approve($designJob);
        }

        $order->update(['status' => 'DESIGN_APPROVED']);

        return $order->fresh();
    }
}
