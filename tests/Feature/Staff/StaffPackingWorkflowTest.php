<?php

namespace Tests\Feature\Staff;

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
use App\Services\Packing\AssignPackingJobService;
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffPackingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_packing_staff_can_start_packing(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $job)
        );

        $response->assertRedirect();

        $job->refresh();

        $this->assertSame('PACKING', $job->status);
        $this->assertNotNull($job->started_at);

        $event = $job->events()
            ->where('event_type', 'PACKING_STARTED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame('READY_FOR_PACKING', $event->from_status);
        $this->assertSame('PACKING', $event->to_status);
    }

    public function test_operation_management_can_start_any_packing_job(): void
    {
        $admin = $this->admin();
        $operationManagement = $this->staff(User::ROLE_OM);
        $assignedPackingStaff = $this->staff(User::ROLE_PACKING);

        [, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $assignedPackingStaff, $admin);

        $this->actingAs($operationManagement)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $job->refresh();

        $this->assertSame('PACKING', $job->status);
        $this->assertSame(
            $operationManagement->id,
            $job->events()
                ->where('event_type', 'PACKING_STARTED')
                ->latest('id')
                ->firstOrFail()
                ->actor_user_id
        );
    }

    public function test_assigned_packing_staff_can_verify_item(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $job)
        );

        $item = $job->items()->firstOrFail();

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.items.verify', [
                'packingJob' => $job,
                'packingItem' => $item,
            ])
        );

        $response->assertRedirect();

        $item->refresh();

        $this->assertTrue($item->verified_present);
        $this->assertNotNull($item->verified_at);

        $event = $job->events()
            ->where('event_type', 'PACKING_ITEM_VERIFIED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame(
            $item->id,
            $event->metadata['packing_job_item_id']
        );
        $this->assertSame(
            $item->print_job_id,
            $event->metadata['print_job_id']
        );
    }

    public function test_packing_cannot_complete_before_all_items_are_verified(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(2);

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $job)
        );

        $firstItem = $job->items()
            ->orderBy('id')
            ->firstOrFail();

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.items.verify', [
                'packingJob' => $job,
                'packingItem' => $firstItem,
            ])
        );

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.mark-packed', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('packing.jpg'),
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('packing_job');

        $this->assertSame('PACKING', $job->fresh()->status);
        $this->assertSame('PRINTED', $order->fresh()->status);
        $this->assertNull($job->fresh()->packed_at);
    }

    public function test_one_package_order_becomes_packed_after_item_verified(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $job)
        );

        $item = $job->items()->firstOrFail();

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.items.verify', [
                'packingJob' => $job,
                'packingItem' => $item,
            ])
        );

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.mark-packed', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('packing.jpg'),
            ]
        );

        $response->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertNotNull($job->packed_at);
        $this->assertNotNull($job->proof_storage_path);
        $this->assertSame('packing.jpg', $job->proof_original_name);
        $this->assertSame('PACKED', $order->status);

        $this->assertDatabaseHas('fulfilment_jobs', [
            'order_id' => $order->id,
            'method' => 'PICKUP',
            'status' => 'READY',
        ]);

        $event = $job->events()
            ->where('event_type', 'PACKING_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame('PACKING', $event->from_status);
        $this->assertSame('PACKED', $event->to_status);
        $this->assertSame(
            1,
            $event->metadata['verified_item_count']
        );
    }

    public function test_two_package_order_becomes_packed_only_after_both_items_verified(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(2);

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $job)
        );

        $items = $job->items()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $items);

        $this->assertEqualsCanonicalizing(
            ['LELAKI', 'PEREMPUAN'],
            $items->pluck('side')->all()
        );

        foreach ($items as $item) {
            $this->actingAs($packing)->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            );
        }

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.mark-packed', $job),
                [
                    'packing_proof' => UploadedFile::fake()->image('packing.jpg'),
                ]
            )
            ->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);

        $this->assertTrue(
            $job->items()->get()->every(
                fn ($item) => $item->verified_present
            )
        );

        $event = $job->events()
            ->where('event_type', 'PACKING_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            2,
            $event->metadata['verified_item_count']
        );
    }

    public function test_one_package_courier_order_can_be_completed_by_assigned_packing_staff(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            1,
            'Courier One Package Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        /*
        * COURIER:
        * Packing completion itself does not require the final
        * courier proof/tracking information.
        */
        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.mark-packed', $job)
            )
            ->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertNull($job->proof_storage_path);

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('COURIER', $fulfilmentJob->method);
        $this->assertSame('READY', $fulfilmentJob->status);
        $this->assertNull($fulfilmentJob->tracking_number);
        $this->assertNull($fulfilmentJob->shipped_at);
        $this->assertNull($fulfilmentJob->delivered_at);

        /*
        * Courier has arrived and parcel is handed over.
        * Staff records final parcel/label proof + tracking,
        * then explicitly marks COMPLETE.
        */
        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('parcel-with-label.jpg'),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'PL123456789MY',
                'complete' => '1',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('COMPLETED', $fulfilmentJob->status);
        $this->assertSame('COMPLETED', $order->status);

        $this->assertSame(
            'Pos Laju',
            $fulfilmentJob->courier_provider
        );

        $this->assertSame(
            'PL123456789MY',
            $fulfilmentJob->tracking_number
        );

        $this->assertNotNull($fulfilmentJob->shipped_at);
        $this->assertNull($fulfilmentJob->delivered_at);

        $this->assertNotNull($job->proof_storage_path);

        Storage::disk('local')->assertExists(
            $job->proof_storage_path
        );

        $this->assertSame(
            'parcel-with-label.jpg',
            $job->proof_original_name
        );

        $event = $fulfilmentJob->events()
            ->where('event_type', 'COURIER_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame('READY', $event->from_status);
        $this->assertSame('COMPLETED', $event->to_status);

        $this->assertSame(
            'Pos Laju',
            $event->metadata['courier_provider']
        );

        $this->assertSame(
            'PL123456789MY',
            $event->metadata['tracking_number']
        );

        $this->assertSame(
            $job->proof_storage_path,
            $event->metadata['packing_proof_storage_path']
        );

        /*
        * One business order must still have exactly
        * one fulfilment job.
        */
        $this->assertSame(
            1,
            $order->fulfilmentJob()->count()
        );
    }

    public function test_other_packing_staff_cannot_operate_assigned_job(): void
    {
        $admin = $this->admin();
        $assigned = $this->staff(User::ROLE_PACKING);
        $other = $this->staff(User::ROLE_PACKING);

        [, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $assigned, $admin);

        $this->actingAs($other)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertNotFound();

        $this->assertSame(
            'READY_FOR_PACKING',
            $job->fresh()->status
        );
    }

    public function test_admin_cannot_operate_job_assigned_to_packing_staff(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($admin)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertNotFound();

        $this->assertSame(
            'READY_FOR_PACKING',
            $job->fresh()->status
        );
    }

    public function test_item_from_another_packing_job_cannot_be_verified_through_wrong_job(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [, $firstJob] = $this->packingJob(
            1,
            'Packing Cross Job A'
        );

        [, $secondJob] = $this->packingJob(
            1,
            'Packing Cross Job B'
        );

        app(AssignPackingJobService::class)
            ->assign($firstJob, $packing, $admin);

        app(AssignPackingJobService::class)
            ->assign($secondJob, $packing, $admin);

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $firstJob)
        );

        $this->actingAs($packing)->post(
            route('staff.packing-jobs.start', $secondJob)
        );

        $foreignItem = $secondJob->items()
            ->firstOrFail();

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $firstJob,
                    'packingItem' => $foreignItem,
                ])
            )
            ->assertNotFound();

        $this->assertFalse(
            $foreignItem->fresh()->verified_present
        );
    }

    public function test_item_cannot_be_verified_before_packing_has_started(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [, $job] = $this->packingJob();

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $item = $job->items()->firstOrFail();

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.items.verify', [
                'packingJob' => $job,
                'packingItem' => $item,
            ])
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('packing_job');

        $this->assertFalse(
            $item->fresh()->verified_present
        );

        $this->assertSame(
            'READY_FOR_PACKING',
            $job->fresh()->status
        );
    }

    private function admin(): User
    {
        return $this->staff(User::ROLE_ADMIN);
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function packingJob(
        int $packageCount = 1,
        string $customerName = 'Staff Packing Workflow Test',
        string $fulfilmentMethod = 'PICKUP'
    ): array {
        $order = $this->printedOrder(
            $packageCount,
            $customerName,
            $fulfilmentMethod
        );

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        return [$order->fresh(), $job];
    }

    private function printedOrder(
        int $packageCount = 1,
        string $customerName = 'Staff Packing Workflow Test',
        string $fulfilmentMethod = 'PICKUP'
    ) {
        $order = $this->paidOrder(
            $packageCount,
            $customerName,
            $fulfilmentMethod
        );

        $printJobs = app(InitializePrintJobsForOrderService::class)
            ->initialize($order);

        foreach ($printJobs as $printJob) {
            $printJob = app(StartPrintingService::class)
                ->start($printJob);

            app(MarkPrintJobPrintedService::class)
                ->markPrinted($printJob);
        }

        app(SyncOrderPrintStatusService::class)
            ->sync($order->fresh());

        return $order->fresh();
    }

    private function paidOrder(
        int $packageCount,
        string $customerName,
        string $fulfilmentMethod = 'PICKUP'
    ) {
        $order = $this->approvedOrder(
            $packageCount,
            $customerName,
            $fulfilmentMethod
        );

        $payment = app(CreateBalancePaymentService::class)
            ->create(
                $order->fresh(),
                $packageCount === 1 ? '250.00' : '500.00'
            );

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'STAFF-PACK-'.$payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'STAFF-PACK-EVENT-'.$payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(
        int $packageCount,
        string $customerName,
        string $fulfilmentMethod = 'PICKUP'
    ) {
        $order = $this->confirmedOrder(
            $packageCount,
            $customerName,
            $fulfilmentMethod
        );

        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        $designJobs =
            app(InitializeDesignJobsForOrderService::class)
                ->initialize($order->fresh());

        foreach ($designJobs as $designJob) {
            $designJob = app(StartDesignJobService::class)
                ->start($designJob);

            app(CreateArtworkVersionService::class)->create(
                $designJob,
                [
                    'storage_path' => "artworks/{$designJob->side}/v1.pdf",
                    'preview_storage_path' => "artworks/{$designJob->side}/v1-preview.png",
                    'original_filename' => "{$designJob->side}-v1.pdf",
                    'mime_type' => 'application/pdf',
                ]
            );

            $designJob = app(MarkDesignReadyService::class)
                ->markReady($designJob->fresh());

            app(ApproveArtworkService::class)
                ->approve($designJob);
        }

        $order->update([
            'status' => 'DESIGN_APPROVED',
        ]);

        return $order->fresh();
    }

    private function confirmedOrder(
        int $packageCount,
        string $customerName,
        string $fulfilmentMethod = 'PICKUP'
    ) {
        $order = app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'customer_name' => $customerName,
        ]);

        $sides = [
            'LELAKI' => $this->sidePayload(
                'L101',
                'Bapa Lelaki',
                'Ibu Lelaki',
                'Dewan Lelaki'
            ),
        ];

        if ($packageCount === 2) {
            $sides['PEREMPUAN'] = $this->sidePayload(
                'P101',
                'Bapa Perempuan',
                'Ibu Perempuan',
                'Dewan Perempuan'
            );
        }

        $fulfilment = [
            'method' => $fulfilmentMethod,
        ];

        if ($fulfilmentMethod === 'COURIER') {
            $fulfilment += [
                'recipient_name' => $customerName,
                'recipient_phone' => '0123456789',
                'shipping_address' => 'No. 1, Jalan Test, 70000 Seremban, Negeri Sembilan',
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

        return app(ConfirmOrderDetailsService::class)
            ->confirm($order->fresh());
    }

    public function test_two_package_courier_order_uses_one_business_fulfilment_and_can_be_completed(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            2,
            'Courier Two Package Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $items = $job->items()
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $items);

        $this->assertEqualsCanonicalizing(
            ['LELAKI', 'PEREMPUAN'],
            $items->pluck('side')->all()
        );

        foreach ($items as $item) {
            $this->actingAs($packing)
                ->post(
                    route('staff.packing-jobs.items.verify', [
                        'packingJob' => $job,
                        'packingItem' => $item,
                    ])
                )
                ->assertRedirect();
        }

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertNull($job->proof_storage_path);

        $this->assertTrue(
            $job->items()->get()->every(
                fn ($item) => $item->verified_present
            )
        );

        /*
        * 2-package is still one business order,
        * one packing job and one courier fulfilment.
        */
        $this->assertSame(
            1,
            $order->fulfilmentJob()->count()
        );

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('COURIER', $fulfilmentJob->method);
        $this->assertSame('READY', $fulfilmentJob->status);
        $this->assertNull($fulfilmentJob->tracking_number);

        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image(
                    'two-package-parcel-with-label.jpg'
                ),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'PL987654321MY',
                'complete' => '1',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('COMPLETED', $fulfilmentJob->status);
        $this->assertSame('COMPLETED', $order->status);

        $this->assertSame(
            'Pos Laju',
            $fulfilmentJob->courier_provider
        );

        $this->assertSame(
            'PL987654321MY',
            $fulfilmentJob->tracking_number
        );

        $this->assertNotNull($fulfilmentJob->shipped_at);
        $this->assertNull($fulfilmentJob->delivered_at);

        $this->assertNotNull($job->proof_storage_path);

        Storage::disk('local')->assertExists(
            $job->proof_storage_path
        );

        $this->assertSame(
            'two-package-parcel-with-label.jpg',
            $job->proof_original_name
        );

        /*
        * Completion must not create another fulfilment job.
        */
        $this->assertSame(
            1,
            $order->fulfilmentJob()->count()
        );

        $event = $fulfilmentJob->events()
            ->where('event_type', 'COURIER_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame('READY', $event->from_status);
        $this->assertSame('COMPLETED', $event->to_status);

        $this->assertSame(
            'Pos Laju',
            $event->metadata['courier_provider']
        );

        $this->assertSame(
            'PL987654321MY',
            $event->metadata['tracking_number']
        );

        $this->assertSame(
            $job->proof_storage_path,
            $event->metadata['packing_proof_storage_path']
        );

        $this->assertSame(
            1,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    public function test_courier_completion_requires_proof_courier_tracking_and_explicit_complete(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            1,
            'Courier Validation Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertNull($job->proof_storage_path);

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('COURIER', $fulfilmentJob->method);
        $this->assertSame('READY', $fulfilmentJob->status);

        /*
        * No proof, courier provider, tracking number,
        * or explicit COMPLETE confirmation.
        */
        $response = $this->actingAs($packing)->post(
            route('staff.packing-jobs.complete-courier', $job),
            []
        );

        $response->assertRedirect();

        $response->assertSessionHasErrors([
            'packing_proof',
            'courier_provider',
            'tracking_number',
            'complete',
        ]);

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        /*
        * Failed validation must not change operational state.
        */
        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertSame('READY', $fulfilmentJob->status);

        $this->assertNull($job->proof_storage_path);
        $this->assertNull($fulfilmentJob->courier_provider);
        $this->assertNull($fulfilmentJob->tracking_number);
        $this->assertNull($fulfilmentJob->shipped_at);
        $this->assertNull($fulfilmentJob->delivered_at);

        $this->assertSame(
            0,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    public function test_other_packing_staff_cannot_complete_courier_job_assigned_to_another_packing_staff(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $assignedPacking = $this->staff(User::ROLE_PACKING);
        $otherPacking = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            1,
            'Courier Other Packing Authorization Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $assignedPacking, $admin);

        $this->actingAs($assignedPacking)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($assignedPacking)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        $this->actingAs($assignedPacking)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('READY', $fulfilmentJob->status);

        $response = $this->actingAs($otherPacking)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('unauthorized-proof.jpg'),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'UNAUTHORIZED123',
                'complete' => '1',
            ]
        );

        $response->assertNotFound();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertSame('READY', $fulfilmentJob->status);

        $this->assertNull($job->proof_storage_path);
        $this->assertNull($fulfilmentJob->courier_provider);
        $this->assertNull($fulfilmentJob->tracking_number);
        $this->assertNull($fulfilmentJob->shipped_at);

        $this->assertSame(
            0,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    public function test_operation_management_cannot_complete_courier_job_assigned_to_packing_staff(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $operationManagement = $this->staff(User::ROLE_OM);
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            1,
            'Courier Operation Management Authorization Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('READY', $fulfilmentJob->status);

        /*
        * Clear the previous PACKING authentication before
        * testing OPERATION_MANAGEMENT as the actual actor.
        */
        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($operationManagement)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('om-proof.jpg'),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'OM123',
                'complete' => '1',
            ]
        );

        $response->assertNotFound();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertSame('READY', $fulfilmentJob->status);

        $this->assertNull($job->proof_storage_path);
        $this->assertNull($fulfilmentJob->courier_provider);
        $this->assertNull($fulfilmentJob->tracking_number);
        $this->assertNull($fulfilmentJob->shipped_at);

        $this->assertSame(
            0,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    public function test_operation_management_can_complete_courier_job_assigned_to_them(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $operationManagement = $this->staff(User::ROLE_OM);

        [$order, $job] = $this->packingJob(
            1,
            'Courier OM Own Assignment Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $operationManagement, $admin);

        $this->actingAs($operationManagement)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($operationManagement)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        $this->actingAs($operationManagement)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('READY', $fulfilmentJob->status);

        $response = $this->actingAs($operationManagement)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('om-own-proof.jpg'),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'OMOWN123',
                'complete' => '1',
            ]
        );

        $response->assertRedirect();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('COMPLETED', $order->status);
        $this->assertSame('COMPLETED', $fulfilmentJob->status);

        $this->assertSame(
            'Pos Laju',
            $fulfilmentJob->courier_provider
        );

        $this->assertSame(
            'OMOWN123',
            $fulfilmentJob->tracking_number
        );

        $this->assertNotNull($fulfilmentJob->shipped_at);
        $this->assertNull($fulfilmentJob->delivered_at);

        $this->assertNotNull($job->proof_storage_path);

        $event = $fulfilmentJob->events()
            ->where('event_type', 'COURIER_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $operationManagement->id,
            $event->actor_user_id
        );

        $this->assertSame('READY', $event->from_status);
        $this->assertSame('COMPLETED', $event->to_status);

        $this->assertSame(
            'Pos Laju',
            $event->metadata['courier_provider']
        );

        $this->assertSame(
            'OMOWN123',
            $event->metadata['tracking_number']
        );

        $this->assertSame(
            $job->proof_storage_path,
            $event->metadata['packing_proof_storage_path']
        );

        $this->assertSame(
            1,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );

        $this->assertSame(
            1,
            $order->fulfilmentJob()->count()
        );
    }

    public function test_admin_cannot_complete_courier_job_assigned_to_packing_staff(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        [$order, $job] = $this->packingJob(
            1,
            'Courier Admin Authorization Test',
            'COURIER'
        );

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.start', $job))
            ->assertRedirect();

        $item = $job->items()->firstOrFail();

        $this->actingAs($packing)
            ->post(
                route('staff.packing-jobs.items.verify', [
                    'packingJob' => $job,
                    'packingItem' => $item,
                ])
            )
            ->assertRedirect();

        $this->actingAs($packing)
            ->post(route('staff.packing-jobs.mark-packed', $job))
            ->assertRedirect();

        $fulfilmentJob = $order->fulfilmentJob()
            ->firstOrFail();

        $this->assertSame('READY', $fulfilmentJob->status);

        $this->app['auth']->forgetGuards();

        $response = $this->actingAs($admin)->post(
            route('staff.packing-jobs.complete-courier', $job),
            [
                'packing_proof' => UploadedFile::fake()->image('admin-proof.jpg'),
                'courier_provider' => 'Pos Laju',
                'tracking_number' => 'ADMIN123',
                'complete' => '1',
            ]
        );

        $response->assertNotFound();

        $job->refresh();
        $order->refresh();
        $fulfilmentJob->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertSame('PACKED', $order->status);
        $this->assertSame('READY', $fulfilmentJob->status);

        $this->assertNull($job->proof_storage_path);
        $this->assertNull($fulfilmentJob->courier_provider);
        $this->assertNull($fulfilmentJob->tracking_number);
        $this->assertNull($fulfilmentJob->shipped_at);

        $this->assertSame(
            0,
            $fulfilmentJob->events()
                ->where('event_type', 'COURIER_COMPLETED')
                ->count()
        );
    }

    public function test_packing_saves_courier_details_without_marking_parcel_shipped(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);
        [$order, $job] = $this->packingJob(1, 'Tracking During Packing', 'COURIER');
        app(AssignPackingJobService::class)->assign($job, $packing, $admin);
        $this->actingAs($packing)->post(route('staff.packing-jobs.start', $job))->assertRedirect();
        $this->post(route('staff.packing-jobs.items.verify', [
            'packingJob' => $job,
            'packingItem' => $job->items()->firstOrFail(),
        ]))->assertRedirect();

        $this->post(route('staff.packing-jobs.mark-packed', $job), [
            'courier_provider' => 'Pos Laju',
        ])->assertSessionHasErrors('tracking_number');
        $this->assertSame('PACKING', $job->fresh()->status);

        $this->post(route('staff.packing-jobs.mark-packed', $job), [
            'courier_provider' => 'Pos Laju',
            'tracking_number' => 'PL001234567MY',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $fulfilment = $order->fulfilmentJob()->firstOrFail();
        $this->assertSame('Pos Laju', $fulfilment->courier_provider);
        $this->assertSame('PL001234567MY', $fulfilment->tracking_number);
        $this->assertSame('READY', $fulfilment->status);
        $this->assertNull($fulfilment->shipped_at);
        $this->get(route('staff.orders.show', $order->order_id))
            ->assertSee('Pos Laju')->assertSee('PL001234567MY');
        $this->post(route('public.orders.progress.lookup'), ['order_id' => $order->order_id])
            ->assertSee('Pos Laju')->assertSee('PL001234567MY');
    }

    private function sidePayload(
        string $designCode,
        string $father,
        string $mother,
        string $venue
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
                'full_address' => 'Alamat Test',
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
