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
            route('staff.packing-jobs.mark-packed', $job)
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
            route('staff.packing-jobs.mark-packed', $job)
        );

        $response->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PACKED', $job->status);
        $this->assertNotNull($job->packed_at);
        $this->assertSame('PACKED', $order->status);

        $event = $job->events()
            ->where('event_type', 'PACKING_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($packing->id, $event->actor_user_id);
        $this->assertSame('PACKING', $event->from_status);
        $this->assertSame('PACKED', $event->to_status);
        $this->assertSame(1, $event->metadata['verified_item_count']);
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
            ->post(route('staff.packing-jobs.mark-packed', $job))
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

        $this->assertSame(2, $event->metadata['verified_item_count']);
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
        string $customerName = 'Staff Packing Workflow Test'
    ): array {
        $order = $this->printedOrder(
            $packageCount,
            $customerName
        );

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        return [$order->fresh(), $job];
    }

    private function printedOrder(
        int $packageCount = 1,
        string $customerName = 'Staff Packing Workflow Test'
    ) {
        $order = $this->paidOrder(
            $packageCount,
            $customerName
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
        string $customerName
    ) {
        $order = $this->approvedOrder(
            $packageCount,
            $customerName
        );

        $payment = app(CreateBalancePaymentService::class)
            ->create(
                $order->fresh(),
                $packageCount === 1 ? '250.00' : '500.00'
            );

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'STAFF-PACK-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'STAFF-PACK-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(
        int $packageCount,
        string $customerName
    ) {
        $order = $this->confirmedOrder(
            $packageCount,
            $customerName
        );

        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        $designJobs = app(InitializeDesignJobsForOrderService::class)
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
        string $customerName
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

        return app(ConfirmOrderDetailsService::class)
            ->confirm($order->fresh());
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