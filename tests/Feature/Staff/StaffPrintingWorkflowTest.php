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
use App\Services\Payments\CreateBalancePaymentService;
use App\Services\Payments\HandlePaymentCallbackService;
use App\Services\Printing\AssignPrintJobService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPrintingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_printing_staff_can_start_print_job(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        [$order, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $response = $this->actingAs($printing)->post(
            route('staff.print-jobs.start', $job)
        );

        $response->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PRINTING', $job->status);
        $this->assertNotNull($job->started_at);
        $this->assertSame('PRINTING', $order->status);

        $event = $job->events()
            ->where('event_type', 'PRINTING_STARTED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($printing->id, $event->actor_user_id);
        $this->assertSame('READY_FOR_PRINT', $event->from_status);
        $this->assertSame('PRINTING', $event->to_status);
    }

    public function test_assigned_printing_staff_can_mark_print_job_printed(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        [$order, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->actingAs($printing)->post(
            route('staff.print-jobs.start', $job)
        );

        $response = $this->actingAs($printing)->post(
            route('staff.print-jobs.mark-printed', $job)
        );

        $response->assertRedirect();

        $job->refresh();
        $order->refresh();

        $this->assertSame('PRINTED', $job->status);
        $this->assertNotNull($job->printed_at);
        $this->assertSame('PRINTED', $order->status);

        $event = $job->events()
            ->where('event_type', 'PRINTING_COMPLETED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($printing->id, $event->actor_user_id);
        $this->assertSame('PRINTING', $event->from_status);
        $this->assertSame('PRINTED', $event->to_status);
    }

    public function test_other_printing_staff_cannot_start_assigned_print_job(): void
    {
        $admin = $this->admin();
        $assigned = $this->staff(User::ROLE_PRINTING);
        $other = $this->staff(User::ROLE_PRINTING);

        [, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $assigned, $admin);

        $this->actingAs($other)
            ->post(route('staff.print-jobs.start', $job))
            ->assertNotFound();

        $this->assertSame(
            'READY_FOR_PRINT',
            $job->fresh()->status
        );
    }

    public function test_other_printing_staff_cannot_mark_assigned_print_job_printed(): void
    {
        $admin = $this->admin();
        $assigned = $this->staff(User::ROLE_PRINTING);
        $other = $this->staff(User::ROLE_PRINTING);

        [, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $assigned, $admin);

        $this->actingAs($assigned)->post(
            route('staff.print-jobs.start', $job)
        );

        $this->actingAs($other)
            ->post(route('staff.print-jobs.mark-printed', $job))
            ->assertNotFound();

        $this->assertSame(
            'PRINTING',
            $job->fresh()->status
        );
    }

    public function test_admin_cannot_operate_print_job_assigned_to_printing_staff(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        [, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->actingAs($admin)
            ->post(route('staff.print-jobs.start', $job))
            ->assertNotFound();

        $this->assertSame(
            'READY_FOR_PRINT',
            $job->fresh()->status
        );
    }

    public function test_operation_management_cannot_operate_print_job_assigned_to_printing_staff(): void
    {
        $admin = $this->admin();
        $operationManagement = $this->staff(User::ROLE_OM);
        $printing = $this->staff(User::ROLE_PRINTING);

        [, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->actingAs($operationManagement)
            ->post(route('staff.print-jobs.start', $job))
            ->assertForbidden();

        $this->assertSame(
            'READY_FOR_PRINT',
            $job->fresh()->status
        );
    }

    public function test_invalid_printing_transition_returns_safe_error(): void
    {
        $admin = $this->admin();
        $printing = $this->staff(User::ROLE_PRINTING);

        [, $jobs] = $this->printJobs();
        $job = $jobs->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $response = $this->actingAs($printing)->post(
            route('staff.print-jobs.mark-printed', $job)
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('print_job');

        $this->assertSame(
            'READY_FOR_PRINT',
            $job->fresh()->status
        );

        $this->assertNull($job->fresh()->printed_at);
    }

    public function test_two_package_order_remains_printing_after_only_one_side_is_printed(): void
    {
        $admin = $this->admin();
        $lelakiStaff = $this->staff(User::ROLE_PRINTING);
        $perempuanStaff = $this->staff(User::ROLE_PRINTING);

        [$order, $jobs] = $this->printJobs(2);

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        app(AssignPrintJobService::class)
            ->assign($lelaki, $lelakiStaff, $admin);

        app(AssignPrintJobService::class)
            ->assign($perempuan, $perempuanStaff, $admin);

        $this->actingAs($lelakiStaff)->post(
            route('staff.print-jobs.start', $lelaki)
        );

        $this->actingAs($perempuanStaff)->post(
            route('staff.print-jobs.start', $perempuan)
        );

        $this->actingAs($lelakiStaff)->post(
            route('staff.print-jobs.mark-printed', $lelaki)
        );

        $this->assertSame(
            'PRINTED',
            $lelaki->fresh()->status
        );

        $this->assertSame(
            'PRINTING',
            $perempuan->fresh()->status
        );

        $this->assertSame(
            'PRINTING',
            $order->fresh()->status
        );
    }

    public function test_two_package_order_becomes_printed_only_after_both_sides_complete(): void
    {
        $admin = $this->admin();
        $lelakiStaff = $this->staff(User::ROLE_PRINTING);
        $perempuanStaff = $this->staff(User::ROLE_PRINTING);

        [$order, $jobs] = $this->printJobs(2);

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        app(AssignPrintJobService::class)
            ->assign($lelaki, $lelakiStaff, $admin);

        app(AssignPrintJobService::class)
            ->assign($perempuan, $perempuanStaff, $admin);

        $this->actingAs($lelakiStaff)->post(
            route('staff.print-jobs.start', $lelaki)
        );

        $this->actingAs($perempuanStaff)->post(
            route('staff.print-jobs.start', $perempuan)
        );

        $this->actingAs($lelakiStaff)->post(
            route('staff.print-jobs.mark-printed', $lelaki)
        );

        $this->assertSame(
            'PRINTING',
            $order->fresh()->status
        );

        $this->actingAs($perempuanStaff)->post(
            route('staff.print-jobs.mark-printed', $perempuan)
        );

        $this->assertSame(
            'PRINTED',
            $lelaki->fresh()->status
        );

        $this->assertSame(
            'PRINTED',
            $perempuan->fresh()->status
        );

        $this->assertSame(
            'PRINTED',
            $order->fresh()->status
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

    private function printJobs(int $packageCount = 1): array
    {
        $order = $this->paidOrder($packageCount);

        $jobs = app(InitializePrintJobsForOrderService::class)
            ->initialize($order);

        return [$order->fresh(), $jobs];
    }

    private function paidOrder(int $packageCount = 1)
    {
        $order = $this->approvedOrder($packageCount);

        $payment = app(CreateBalancePaymentService::class)
            ->create(
                $order->fresh(),
                $packageCount === 1 ? '250.00' : '500.00'
            );

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'STAFF-PRINT-' . $payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'STAFF-PRINT-EVENT-' . $payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function approvedOrder(int $packageCount = 1)
    {
        $order = $this->confirmedOrder($packageCount);

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

    private function confirmedOrder(int $packageCount = 1)
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => $packageCount,
            'side' => $packageCount === 1 ? 'LELAKI' : null,
            'customer_name' => 'Staff Printing Workflow Test',
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
