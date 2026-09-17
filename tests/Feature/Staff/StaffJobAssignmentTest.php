<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\GenerateMergeJobsForOrderService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\StartDesignJobService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffJobAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_design_job_to_active_designer(): void
    {
        $admin = $this->admin();
        $designer = $this->staff(User::ROLE_DESIGNER);

        $job = $this->designJob();

        $response = $this->actingAs($admin)->post(
            route('staff.design-jobs.assign', $job),
            ['assigned_user_id' => $designer->id]
        );

        $response->assertRedirect();

        $job->refresh();

        $this->assertSame($designer->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'DESIGNER_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(
            $designer->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_admin_can_assign_print_job_to_active_printing_staff(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        $job = $this->printJob();

        $response = $this->actingAs($admin)->post(
            route('staff.print-jobs.assign', $job),
            ['assigned_user_id' => $printing->id]
        );

        $response->assertRedirect();

        $job->refresh();

        $this->assertSame($printing->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'PRINT_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);
    }

    public function test_admin_can_assign_packing_job_to_active_packing_staff(): void
    {
        $admin = $this->admin();
        $packing = $this->staff(User::ROLE_PACKING);

        $job = $this->packingJob();

        $response = $this->actingAs($admin)->post(
            route('staff.packing-jobs.assign', $job),
            ['assigned_user_id' => $packing->id]
        );

        $response->assertRedirect();

        $job->refresh();

        $this->assertSame($packing->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'PACKING_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);
    }

    public function test_design_reassignment_records_previous_and_new_assignee_with_admin_actor(): void
    {
        $admin = $this->admin();
        $first = $this->staff(User::ROLE_DESIGNER);
        $second = $this->staff(User::ROLE_DESIGNER);

        $job = $this->designJob();

        $this->actingAs($admin)->post(
            route('staff.design-jobs.assign', $job),
            ['assigned_user_id' => $first->id]
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('staff.design-jobs.assign', $job),
            ['assigned_user_id' => $second->id]
        )->assertRedirect();

        $job->refresh();

        $this->assertSame($second->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'DESIGNER_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);
        $this->assertSame(
            $first->id,
            $event->metadata['previous_assigned_user_id']
        );
        $this->assertSame(
            $second->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_print_reassignment_records_previous_and_new_assignee_with_admin_actor(): void
    {
        $admin = $this->admin();
        $first = $this->staff(User::ROLE_PRINTING);
        $second = $this->staff(User::ROLE_PRINTING);

        $job = $this->printJob();

        $this->actingAs($admin)->post(
            route('staff.print-jobs.assign', $job),
            ['assigned_user_id' => $first->id]
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('staff.print-jobs.assign', $job),
            ['assigned_user_id' => $second->id]
        )->assertRedirect();

        $job->refresh();

        $this->assertSame($second->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'PRINT_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);

        $this->assertSame(
            $first->id,
            $event->metadata['previous_assigned_user_id']
        );

        $this->assertSame(
            $second->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_packing_reassignment_records_previous_and_new_assignee_with_admin_actor(): void
    {
        $admin = $this->admin();
        $first = $this->staff(User::ROLE_PACKING);
        $second = $this->staff(User::ROLE_PACKING);

        $job = $this->packingJob();

        $this->actingAs($admin)->post(
            route('staff.packing-jobs.assign', $job),
            ['assigned_user_id' => $first->id]
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('staff.packing-jobs.assign', $job),
            ['assigned_user_id' => $second->id]
        )->assertRedirect();

        $job->refresh();

        $this->assertSame($second->id, $job->assigned_user_id);

        $event = $job->events()
            ->where('event_type', 'PACKING_JOB_ASSIGNED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $event->actor_user_id);

        $this->assertSame(
            $first->id,
            $event->metadata['previous_assigned_user_id']
        );

        $this->assertSame(
            $second->id,
            $event->metadata['assigned_user_id']
        );
    }

    public function test_admin_cannot_assign_design_job_to_wrong_role(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        $job = $this->designJob();

        $response = $this->actingAs($admin)
            ->from(route('staff.orders.index'))
            ->post(
                route('staff.design-jobs.assign', $job),
                ['assigned_user_id' => $printing->id]
            );

        $response
            ->assertRedirect(route('staff.orders.index'))
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertNull($job->fresh()->assigned_user_id);
    }

    public function test_admin_cannot_assign_job_to_inactive_staff(): void
    {
        $admin = $this->admin();

        $designer = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => false,
        ]);

        $job = $this->designJob();

        $response = $this->actingAs($admin)
            ->from(route('staff.orders.index'))
            ->post(
                route('staff.design-jobs.assign', $job),
                ['assigned_user_id' => $designer->id]
            );

        $response
            ->assertRedirect(route('staff.orders.index'))
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertNull($job->fresh()->assigned_user_id);
    }

    public function test_operational_staff_cannot_use_admin_assignment_endpoint(): void
    {
        $job = $this->designJob();
        $target = $this->staff(User::ROLE_DESIGNER);

        foreach ([
            User::ROLE_DESIGNER,
            User::ROLE_PRINTING,
            User::ROLE_PACKING,
        ] as $role) {
            $actor = $this->staff($role);

            $this->actingAs($actor)->post(
                route('staff.design-jobs.assign', $job),
                ['assigned_user_id' => $target->id]
            )->assertForbidden();

            $this->assertNull($job->fresh()->assigned_user_id);
        }
    }

    public function test_guest_cannot_assign_job(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $job = $this->designJob();

        $this->post(
            route('staff.design-jobs.assign', $job),
            ['assigned_user_id' => $designer->id]
        )->assertRedirect(route('staff.login'));

        $this->assertNull($job->fresh()->assigned_user_id);
    }

    public function test_two_package_design_sides_can_be_assigned_independently(): void
    {
        $admin = $this->admin();
        $lelakiDesigner = $this->staff(User::ROLE_DESIGNER);
        $perempuanDesigner = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder(2);

        app(\App\Services\Merge\GenerateMergeJobsForOrderService::class)
            ->generate($order);

        $jobs = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh());

        $this->assertCount(2, $jobs);

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        $this->assertNotNull($lelaki);
        $this->assertNotNull($perempuan);

        $this->actingAs($admin)->post(
            route('staff.design-jobs.assign', $lelaki),
            ['assigned_user_id' => $lelakiDesigner->id]
        )->assertRedirect();

        $this->actingAs($admin)->post(
            route('staff.design-jobs.assign', $perempuan),
            ['assigned_user_id' => $perempuanDesigner->id]
        )->assertRedirect();

        $this->assertSame(
            $lelakiDesigner->id,
            $lelaki->fresh()->assigned_user_id
        );

        $this->assertSame(
            $perempuanDesigner->id,
            $perempuan->fresh()->assigned_user_id
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

    private function designJob()
    {
        $order = $this->confirmedOrder();

        app(\App\Services\Merge\GenerateMergeJobsForOrderService::class)
            ->generate($order);

        return app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())
            ->first();
    }

    private function printJob()
    {
        return app(InitializePrintJobsForOrderService::class)
            ->initialize($this->paidOrder())
            ->first();
    }

    private function packingJob()
    {
        return app(InitializePackingJobForOrderService::class)
            ->initialize($this->printedOrder());
    }

    private function confirmedOrder(int $packageCount = 1)
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'customer_name' => 'Staff Assignment Test',
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

    private function approvedOrder()
    {
        $order = $this->confirmedOrder();

        app(\App\Services\Merge\GenerateMergeJobsForOrderService::class)
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

    private function paidOrder()
    {
        $order = $this->approvedOrder();

        $payment = app(CreateBalancePaymentService::class)
            ->create($order->fresh(), '250.00');

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'STAFF-ASSIGN-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'STAFF-ASSIGN-EVENT-' . $payment->id,
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
