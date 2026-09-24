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
use App\Services\Packing\InitializePackingJobForOrderService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProductionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_om_can_assign_production_staff_after_design_approval_and_assignees_can_view_read_only_order(): void
    {
        $operationManagement = $this->staff(User::ROLE_OM);
        $designer = $this->staff(User::ROLE_DESIGNER);
        $printing = $this->staff(User::ROLE_PRINTING);
        $otherPrinting = $this->staff(User::ROLE_PRINTING);
        $packing = $this->staff(User::ROLE_PACKING);
        $order = $this->approvedOrder();

        $this->actingAs($operationManagement)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Assign Production Staff')
            ->assertSee('Assign Printing Staff')
            ->assertSee('Assign Packing &amp; Fulfilment Staff', false);

        $this->actingAs($operationManagement)
            ->post(route('staff.design-jobs.assign', $order->designJobs->first()), ['assigned_user_id' => $designer->id])
            ->assertRedirect();

        $this->actingAs($operationManagement)
            ->post(route('staff.orders.assign-printing', $order), ['assigned_user_id' => $printing->id])
            ->assertRedirect();

        $this->actingAs($operationManagement)
            ->post(route('staff.orders.assign-packing-fulfilment', $order), ['assigned_user_id' => $packing->id])
            ->assertRedirect();

        $this->assertSame($printing->id, $order->fresh()->printing_assigned_user_id);
        $this->assertSame($packing->id, $order->fresh()->packing_assigned_user_id);

        foreach ([$designer, $printing, $packing] as $assignee) {
            $this->actingAs($assignee)
                ->get(route('staff.orders.index'))
                ->assertOk()
                ->assertSee($order->order_id);

            $this->actingAs($assignee)
                ->get(route('staff.orders.show', $order->order_id))
                ->assertOk()
                ->assertSee('Read-only order details.')
                ->assertSee($order->order_id);
        }

        $this->actingAs($otherPrinting)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertNotFound();
    }

    public function test_order_assignments_are_inherited_when_printing_and_packing_jobs_are_created(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $printing = $this->staff(User::ROLE_PRINTING);
        $packing = $this->staff(User::ROLE_PACKING);
        $order = $this->approvedOrder();

        $this->actingAs($admin)
            ->post(route('admin.orders.assign-printing', $order), ['assigned_user_id' => $printing->id])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.orders.assign-packing-fulfilment', $order), ['assigned_user_id' => $packing->id])
            ->assertRedirect();

        $order->update(['status' => 'PAID']);
        $printJobs = app(InitializePrintJobsForOrderService::class)->initialize($order->fresh());

        $this->assertTrue($printJobs->every(fn ($job) => $job->assigned_user_id === $printing->id));

        $printJobs->each(fn ($job) => $job->update(['status' => 'PRINTED']));
        $order->update(['status' => 'PRINTED']);
        $packingJob = app(InitializePackingJobForOrderService::class)->initialize($order->fresh());

        $this->assertSame($packing->id, $packingJob->assigned_user_id);
    }

    public function test_production_assignment_rejects_wrong_staff_role_and_unapproved_design(): void
    {
        $operationManagement = $this->staff(User::ROLE_OM);
        $designer = $this->staff(User::ROLE_DESIGNER);
        $approvedOrder = $this->approvedOrder();

        $this->actingAs($operationManagement)
            ->from(route('staff.orders.show', $approvedOrder->order_id))
            ->post(route('staff.orders.assign-printing', $approvedOrder), ['assigned_user_id' => $designer->id])
            ->assertRedirect(route('staff.orders.show', $approvedOrder->order_id))
            ->assertSessionHasErrors('assigned_user_id');

        $unapprovedOrder = $this->confirmedOrder();

        $this->actingAs($operationManagement)
            ->post(route('staff.orders.assign-packing-fulfilment', $unapprovedOrder), ['assigned_user_id' => $designer->id])
            ->assertUnprocessable();
    }

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function approvedOrder()
    {
        $order = $this->confirmedOrder();
        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $designJobs = app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        foreach ($designJobs as $designJob) {
            $designJob = app(StartDesignJobService::class)->start($designJob);
            app(CreateArtworkVersionService::class)->create($designJob, [
                'storage_path' => "artworks/{$designJob->side}/v1.pdf",
                'preview_storage_path' => "artworks/{$designJob->side}/v1-preview.png",
                'original_filename' => "{$designJob->side}-v1.pdf",
                'mime_type' => 'application/pdf',
            ]);
            $designJob = app(MarkDesignReadyService::class)->markReady($designJob->fresh());
            app(ApproveArtworkService::class)->approve($designJob);
        }

        $order->update(['status' => 'DESIGN_APPROVED']);

        return $order->fresh();
    }

    private function confirmedOrder()
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Production Assignment Test',
        ]);

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
            'couple' => ['groom_name' => 'Muhammad Syafiq', 'bride_name' => 'Nur Awanis'],
            'sides' => [
                'LELAKI' => [
                    'design' => ['design_code' => 'L101'],
                    'parents' => ['father_name' => 'Bapa Lelaki', 'mother_name' => 'Ibu Lelaki'],
                    'event' => [
                        'event_date' => '2026-12-20',
                        'meal_time' => '12:00',
                        'venue_name' => 'Dewan Lelaki',
                        'full_address' => 'Alamat Test',
                        'contacts' => [
                            1 => ['contact_name' => 'Contact 1', 'contact_phone' => '0111111111'],
                            2 => ['contact_name' => 'Contact 2', 'contact_phone' => '0122222222'],
                            3 => ['contact_name' => 'Contact 3', 'contact_phone' => '0133333333'],
                        ],
                    ],
                ],
            ],
            'fulfilment' => ['method' => 'PICKUP'],
        ]);

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }
}
