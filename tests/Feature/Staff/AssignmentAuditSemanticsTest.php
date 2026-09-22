<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\AssignDesignJobService;
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
use App\Services\Printing\AssignPrintJobService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentAuditSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_assignment_separates_assignee_from_actor(): void
    {
        $admin = $this->admin();
        $designer = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder();

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $job = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())
            ->first();

        $assigned = app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        $this->assertSame(
            $designer->id,
            $assigned->assigned_user_id
        );

        $event = $assigned->events()
            ->where('event_type', 'DESIGNER_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $event->actor_user_id
        );

        $this->assertSame(
            $designer->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_print_assignment_separates_assignee_from_actor(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        $order = $this->paidOrder();

        $job = app(InitializePrintJobsForOrderService::class)
            ->initialize($order)
            ->first();

        $assigned = app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->assertSame(
            $printing->id,
            $assigned->assigned_user_id
        );

        $event = $assigned->events()
            ->where('event_type', 'PRINT_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $event->actor_user_id
        );

        $this->assertSame(
            $printing->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_packing_assignment_separates_assignee_from_actor(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        $order = $this->printedOrder();

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        $assigned = app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->assertSame(
            $packing->id,
            $assigned->assigned_user_id
        );

        $event = $assigned->events()
            ->where('event_type', 'PACKING_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $event->actor_user_id
        );

        $this->assertSame(
            $packing->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_design_reassignment_records_previous_and_new_assignee_with_admin_actor(): void
    {
        $admin = $this->admin();
        $firstDesigner = $this->staff(User::ROLE_DESIGNER);
        $secondDesigner = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder();

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $job = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())
            ->first();

        $job = app(AssignDesignJobService::class)
            ->assign($job, $firstDesigner, $admin);

        $job = app(AssignDesignJobService::class)
            ->assign($job, $secondDesigner, $admin);

        $this->assertSame(
            $secondDesigner->id,
            $job->assigned_user_id
        );

        $event = $job->events()
            ->where('event_type', 'DESIGNER_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $admin->id,
            $event->actor_user_id
        );

        $this->assertSame(
            $firstDesigner->id,
            $event->metadata['previous_assigned_user_id']
        );

        $this->assertSame(
            $secondDesigner->id,
            $event->metadata['assigned_user_id']
        );
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function confirmedOrder()
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Assignment Audit Test',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => [
                'groom_name' => 'Muhammad Syafiq',
                'bride_name' => 'Nur Awanis',
            ],
            'sides' => [
                'LELAKI' => [
                    'design' => [
                        'design_code' => 'L101',
                    ],
                    'parents' => [
                        'father_name' => 'Bapa Lelaki',
                        'mother_name' => 'Ibu Lelaki',
                    ],
                    'event' => [
                        'event_date' => '2026-12-20',
                        'meal_time' => '12:00',
                        'venue_name' => 'Dewan Lelaki',
                        'full_address' => 'Alamat Lelaki',
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
                ],
            ],
            'fulfilment' => [
                'method' => 'PICKUP',
            ],
        ]);

        return app(ConfirmOrderDetailsService::class)
            ->confirm($order->fresh());
    }

    private function approvedOrder()
    {
        $order = $this->confirmedOrder();

        app(GenerateMergeJobsForOrderService::class)->generate($order);

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

    private function paidOrder()
    {
        $order = $this->approvedOrder();

        $payment = app(CreateBalancePaymentService::class)
            ->create($order->fresh(), '250.00');

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'ASSIGN-REF-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'ASSIGN-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function printedOrder()
    {
        $order = $this->paidOrder();

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
}