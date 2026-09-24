<?php

namespace Tests\Feature\Staff;

use App\Models\Order;
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
use App\Services\Photoshop\LaunchPhotoshopService;
use App\Services\Printing\AssignPrintJobService;
use App\Services\Printing\InitializePrintJobsForOrderService;
use App\Services\Printing\MarkPrintJobPrintedService;
use App\Services\Printing\StartPrintingService;
use App\Services\Printing\SyncOrderPrintStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffOrderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_all_orders(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $first = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin Visible One'
        );

        $second = $this->confirmedOrder(
            1,
            'PEREMPUAN',
            'Admin Visible Two'
        );

        $response = $this
            ->actingAs($admin)
            ->get(route('staff.orders.index'));

        $response->assertOk();

        $response->assertSee($first->order_id);
        $response->assertSee($second->order_id);
    }

    public function test_operation_management_can_see_all_orders(): void
    {
        $operationManagement = $this->staff(User::ROLE_OM);

        $first = $this->confirmedOrder(
            1,
            'LELAKI',
            'OM Visible One'
        );

        $second = $this->confirmedOrder(
            1,
            'PEREMPUAN',
            'OM Visible Two'
        );

        $response = $this
            ->actingAs($operationManagement)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        $response->assertSee($first->order_id);
        $response->assertSee($second->order_id);
    }

    public function test_admin_can_search_orders_by_customer(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $matching = $this->confirmedOrder(1, 'LELAKI', 'Nur Aisyah Searchable');
        $other = $this->confirmedOrder(1, 'PEREMPUAN', 'Customer Lain');

        $this->actingAs($admin)
            ->get(route('staff.orders.index', ['search' => 'Nur Aisyah']))
            ->assertOk()
            ->assertSee($matching->order_id)
            ->assertDontSee($other->order_id);
    }

    public function test_designer_only_sees_orders_assigned_to_them(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $mine = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Mine'
        );

        $other = $this->confirmedOrder(
            1,
            'PEREMPUAN',
            'Designer Other'
        );

        $mineJob = $this->initializeDesignJobs($mine)->first();
        $otherJob = $this->initializeDesignJobs($other)->first();

        app(AssignDesignJobService::class)
            ->assign($mineJob, $designer, $admin);

        app(AssignDesignJobService::class)
            ->assign($otherJob, $otherDesigner, $admin);

        $response = $this
            ->actingAs($designer)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        $response->assertSee($mine->order_id);
        $response->assertDontSee($other->order_id);
    }

    public function test_printing_only_sees_orders_assigned_to_them(): void
    {
        $printing = $this->staff(User::ROLE_PRINTING);
        $otherPrinting = $this->staff(User::ROLE_PRINTING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $mine = $this->paidOrder('Printing Mine');
        $other = $this->paidOrder('Printing Other');

        $mineJob = app(InitializePrintJobsForOrderService::class)
            ->initialize($mine)
            ->first();

        $otherJob = app(InitializePrintJobsForOrderService::class)
            ->initialize($other)
            ->first();

        app(AssignPrintJobService::class)
            ->assign($mineJob, $printing, $admin);

        app(AssignPrintJobService::class)
            ->assign($otherJob, $otherPrinting, $admin);

        $response = $this
            ->actingAs($printing)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        $response->assertSee($mine->order_id);
        $response->assertDontSee($other->order_id);
    }

    public function test_packing_only_sees_orders_assigned_to_them(): void
    {
        $packing = $this->staff(User::ROLE_PACKING);
        $otherPacking = $this->staff(User::ROLE_PACKING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $mine = $this->printedOrder('Packing Mine');
        $other = $this->printedOrder('Packing Other');

        $mineJob = app(InitializePackingJobForOrderService::class)
            ->initialize($mine);

        $otherJob = app(InitializePackingJobForOrderService::class)
            ->initialize($other);

        app(AssignPackingJobService::class)
            ->assign($mineJob, $packing, $admin);

        app(AssignPackingJobService::class)
            ->assign($otherJob, $otherPacking, $admin);

        $response = $this
            ->actingAs($packing)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        $response->assertSee($mine->order_id);
        $response->assertDontSee($other->order_id);
    }

    public function test_unassigned_work_is_not_visible_to_operational_staff(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Unassigned Design'
        );

        $this->initializeDesignJobs($order);

        $response = $this
            ->actingAs($designer)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        $response->assertDontSee($order->order_id);
    }

    public function test_two_package_order_appears_only_once_for_assigned_designer(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            2,
            null,
            'Two Package Designer'
        );

        $jobs = $this->initializeDesignJobs($order);

        $this->assertCount(2, $jobs);

        foreach ($jobs as $job) {
            app(AssignDesignJobService::class)
                ->assign($job, $designer, $admin);
        }

        $response = $this
            ->actingAs($designer)
            ->get(route('staff.orders.index'));

        $response->assertOk();
        file_put_contents(
            storage_path('logs/staff-order-visibility-debug.html'),
            $response->getContent()
        );

        $response->assertSee($order->order_id);
        $response->assertSee(
            route('staff.orders.show', $order->order_id),
            false
        );

        $this->assertSame(
            1,
            substr_count(
                $response->getContent(),
                'class="staff-order-card"'
            )
        );
    }

    public function test_admin_can_open_any_order_detail(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin Detail'
        );

        $this->actingAs($admin)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_operation_management_can_open_any_order_detail(): void
    {
        $operationManagement = $this->staff(User::ROLE_OM);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'OM Detail'
        );

        $this->actingAs($operationManagement)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_designer_can_open_order_assigned_to_them(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Detail Mine'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_designer_gets_404_for_order_assigned_to_another_designer(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Detail Other'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $otherDesigner, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertNotFound();
    }

    public function test_designer_can_open_two_package_order_when_only_one_side_is_assigned_to_them(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            2,
            null,
            'Two Package Detail'
        );

        $jobs = $this->initializeDesignJobs($order);

        $this->assertCount(2, $jobs);

        $lelakiJob = $jobs->firstWhere('side', 'LELAKI');
        $perempuanJob = $jobs->firstWhere('side', 'PEREMPUAN');

        $this->assertNotNull($lelakiJob);
        $this->assertNotNull($perempuanJob);

        app(AssignDesignJobService::class)
            ->assign($lelakiJob, $designer, $admin);

        app(AssignDesignJobService::class)
            ->assign($perempuanJob, $otherDesigner, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_printing_can_open_order_assigned_to_them(): void
    {
        $printing = $this->staff(User::ROLE_PRINTING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->paidOrder('Printing Detail Mine');

        $job = app(InitializePrintJobsForOrderService::class)
            ->initialize($order)
            ->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->actingAs($printing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_printing_gets_404_for_order_assigned_to_another_printing_staff(): void
    {
        $printing = $this->staff(User::ROLE_PRINTING);
        $otherPrinting = $this->staff(User::ROLE_PRINTING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->paidOrder('Printing Detail Other');

        $job = app(InitializePrintJobsForOrderService::class)
            ->initialize($order)
            ->first();

        app(AssignPrintJobService::class)
            ->assign($job, $otherPrinting, $admin);

        $this->actingAs($printing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertNotFound();
    }

    public function test_packing_can_open_order_assigned_to_them(): void
    {
        $packing = $this->staff(User::ROLE_PACKING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->printedOrder('Packing Detail Mine');

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee($order->order_id);
    }

    public function test_packing_gets_404_for_order_assigned_to_another_packing_staff(): void
    {
        $packing = $this->staff(User::ROLE_PACKING);
        $otherPacking = $this->staff(User::ROLE_PACKING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->printedOrder('Packing Detail Other');

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        app(AssignPackingJobService::class)
            ->assign($job, $otherPacking, $admin);

        $this->actingAs($packing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_before_opening_order_detail(): void
    {
        $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Guest Detail Blocked'
        );

        $this->get(route('staff.orders.show', $order->order_id))
            ->assertRedirect(route('staff.login'));
    }

    public function test_designer_only_receives_their_assigned_side_on_two_package_order_detail(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            2,
            null,
            'Designer Side Isolation'
        );

        $jobs = $this->initializeDesignJobs($order)
            ->keyBy('side');

        $lelakiJob = $jobs->get('LELAKI');
        $perempuanJob = $jobs->get('PEREMPUAN');

        $this->assertNotNull($lelakiJob);
        $this->assertNotNull($perempuanJob);

        app(AssignDesignJobService::class)
            ->assign($lelakiJob, $designer, $admin);

        app(AssignDesignJobService::class)
            ->assign($perempuanJob, $otherDesigner, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertViewHas('order', function (Order $viewOrder) use ($designer): bool {
                return $viewOrder->designJobs->count() === 1
                    && $viewOrder->designJobs->first()->side === 'LELAKI'
                    && $viewOrder->designJobs->first()->assigned_user_id === $designer->id;
            });
    }

    public function test_admin_receives_both_sides_on_two_package_order_detail(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $designerLelaki = $this->staff(User::ROLE_DESIGNER);
        $designerPerempuan = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder(
            2,
            null,
            'Admin Both Sides'
        );

        $jobs = $this->initializeDesignJobs($order)
            ->keyBy('side');

        $lelakiJob = $jobs->get('LELAKI');
        $perempuanJob = $jobs->get('PEREMPUAN');

        $this->assertNotNull($lelakiJob);
        $this->assertNotNull($perempuanJob);

        app(AssignDesignJobService::class)
            ->assign($lelakiJob, $designerLelaki, $admin);

        app(AssignDesignJobService::class)
            ->assign($perempuanJob, $designerPerempuan, $admin);

        $this->actingAs($admin)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertViewHas('order', function (Order $viewOrder): bool {
                return $viewOrder->designJobs->count() === 2
                    && $viewOrder->designJobs
                        ->pluck('side')
                        ->sort()
                        ->values()
                        ->all() === ['LELAKI', 'PEREMPUAN'];
            });
    }

    public function test_designer_detail_renders_design_work_but_not_printing_or_packing_work(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Render Contract'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Design Work')
            ->assertDontSee('Printing')
            ->assertDontSee('Packing');
    }

    public function test_printing_detail_renders_printing_work_but_not_design_or_packing_work(): void
    {
        $printing = $this->staff(User::ROLE_PRINTING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->paidOrder('Printing Render Contract');

        $job = app(InitializePrintJobsForOrderService::class)
            ->initialize($order)
            ->first();

        app(AssignPrintJobService::class)
            ->assign($job, $printing, $admin);

        $this->actingAs($printing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Printing')
            ->assertDontSee('Design Work')
            ->assertDontSee('Packing');
    }

    public function test_packing_detail_renders_packing_work_but_not_design_or_printing_work(): void
    {
        $packing = $this->staff(User::ROLE_PACKING);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->printedOrder('Packing Render Contract');

        $job = app(InitializePackingJobForOrderService::class)
            ->initialize($order);

        app(AssignPackingJobService::class)
            ->assign($job, $packing, $admin);

        $this->actingAs($packing)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Packing')
            ->assertDontSee('Design Work')
            ->assertDontSee('Printing');
    }

    public function test_assigned_designer_sees_start_design_action_when_job_is_ready(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Start UI'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Start Design')
            ->assertSee(
                route('staff.design-jobs.start', $job),
                false
            )
            ->assertDontSee('Upload Artwork Version')
            ->assertDontSee('Resume Correction');
    }

    public function test_assigned_designer_sees_artwork_upload_form_when_design_is_in_progress(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Designer Upload UI'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        app(StartDesignJobService::class)
            ->start($job, $designer);

        $this->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Upload Artwork Version')
            ->assertSee('Source Artwork')
            ->assertSee('Customer Preview')
            ->assertSee('Internal Note')
            ->assertSee(
                route('staff.design-jobs.artwork.store', $job),
                false
            )
            ->assertSee('name="source_artwork"', false)
            ->assertSee('name="customer_preview"', false)
            ->assertDontSee('Start Design')
            ->assertDontSee('Resume Correction');
    }

    public function test_admin_only_sees_assignment_controls_for_designer_job(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);
        $designer = $this->staff(User::ROLE_DESIGNER);

        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin Monitor Only UI'
        );

        $job = $this->initializeDesignJobs($order)->first();

        app(AssignDesignJobService::class)
            ->assign($job, $designer, $admin);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Assign')
            ->assertDontSee('Start Design')
            ->assertDontSee(
                route('admin.design-jobs.start', $job),
                false
            )
            ->assertSee('Admin Operations')
            ->assertSee('admin-operations-mode', false)
            ->assertDontSee('Upload Artwork Version')
            ->assertDontSee('Resume Correction');
    }

    public function test_two_package_designer_ui_only_contains_operational_controls_for_assigned_side(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);

        $order = $this->confirmedOrder(
            2,
            null,
            'Designer UI Side Isolation'
        );

        $jobs = $this->initializeDesignJobs($order)
            ->keyBy('side');

        $lelakiJob = $jobs->get('LELAKI');
        $perempuanJob = $jobs->get('PEREMPUAN');

        $this->assertNotNull($lelakiJob);
        $this->assertNotNull($perempuanJob);

        app(AssignDesignJobService::class)
            ->assign($lelakiJob, $designer, $admin);

        app(AssignDesignJobService::class)
            ->assign($perempuanJob, $otherDesigner, $admin);

        $response = $this
            ->actingAs($designer)
            ->get(route('staff.orders.show', $order->order_id));

        $response
            ->assertOk()
            ->assertSee('LELAKI')
            ->assertSee(
                route('staff.design-jobs.start', $lelakiJob),
                false
            )
            ->assertDontSee(
                route('staff.design-jobs.start', $perempuanJob),
                false
            );

        $this->assertSame(
            1,
            substr_count(
                $response->getContent(),
                'Start Design'
            )
        );
    }

    public function test_admin_can_filter_orders_by_design_workstream(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $designOrder = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin Design Filter'
        );

        $plainOrder = $this->confirmedOrder(
            1,
            'PEREMPUAN',
            'Admin No Design Filter'
        );

        $this->initializeDesignJobs($designOrder);

        $this->actingAs($admin)
            ->get(route('staff.orders.index', ['workstream' => 'design']))
            ->assertOk()
            ->assertSee($designOrder->order_id)
            ->assertDontSee($plainOrder->order_id);
    }

    public function test_admin_can_filter_orders_by_printing_workstream(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $printingOrder = $this->paidOrder('Admin Printing Filter');

        $plainOrder = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin No Printing Filter'
        );

        app(InitializePrintJobsForOrderService::class)
            ->initialize($printingOrder);

        $this->actingAs($admin)
            ->get(route('staff.orders.index', ['workstream' => 'printing']))
            ->assertOk()
            ->assertSee($printingOrder->order_id)
            ->assertDontSee($plainOrder->order_id);
    }

    public function test_admin_can_filter_orders_by_packing_workstream(): void
    {
        $admin = $this->staff(User::ROLE_ADMIN);

        $packingOrder = $this->printedOrder('Admin Packing Filter');

        $plainOrder = $this->confirmedOrder(
            1,
            'LELAKI',
            'Admin No Packing Filter'
        );

        app(InitializePackingJobForOrderService::class)
            ->initialize($packingOrder);

        $this->actingAs($admin)
            ->get(route('staff.orders.index', ['workstream' => 'packing']))
            ->assertOk()
            ->assertSee($packingOrder->order_id)
            ->assertDontSee($plainOrder->order_id);
    }

    public function test_assigned_designer_sees_photoshop_and_csv_tools(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->confirmedOrder(1, 'LELAKI', 'Photoshop Tools UI');
        $job = $this->initializeDesignJobs($order)->firstOrFail();

        app(AssignDesignJobService::class)->assign($job, $designer, $admin);

        $this->actingAs($designer, 'staff')
            ->get(route('staff.orders.show', $order->order_id))
            ->assertOk()
            ->assertSee('Photoshop Auto Merge V11')
            ->assertSee('Download Customer CSV')
            ->assertSee('Open Photoshop')
            ->assertSee(route('staff.orders.photoshop.csv', $order), false)
            ->assertSee(route('staff.orders.photoshop.launch', $order), false);
    }

    public function test_assigned_designer_can_download_photoshop_ready_csv(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->confirmedOrder(1, 'LELAKI', 'Photoshop CSV Download');
        $job = $this->initializeDesignJobs($order)->firstOrFail();

        app(AssignDesignJobService::class)->assign($job, $designer, $admin);

        $response = $this->actingAs($designer, 'staff')
            ->get(route('staff.orders.photoshop.csv', $order));

        $response
            ->assertOk()
            ->assertDownload($order->order_id.'_READY_TO_MERGE.csv');

        $contents = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertIsString($contents);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);
        $this->assertStringContainsString('noinvoice,qtykad,tema,designcode', $contents);
        $this->assertStringContainsString($order->order_id, $contents);
    }

    public function test_assigned_designer_can_launch_photoshop_but_other_designer_cannot(): void
    {
        $designer = $this->staff(User::ROLE_DESIGNER);
        $otherDesigner = $this->staff(User::ROLE_DESIGNER);
        $admin = $this->staff(User::ROLE_ADMIN);
        $order = $this->confirmedOrder(1, 'LELAKI', 'Photoshop Launch');
        $job = $this->initializeDesignJobs($order)->firstOrFail();

        app(AssignDesignJobService::class)->assign($job, $designer, $admin);

        $launcher = $this->mock(LaunchPhotoshopService::class);
        $launcher->shouldReceive('launch')->once();

        $this->actingAs($designer, 'staff')
            ->post(route('staff.orders.photoshop.launch', $order))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($otherDesigner, 'staff')
            ->post(route('staff.orders.photoshop.launch', $order))
            ->assertNotFound();
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function initializeDesignJobs($order)
    {
        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        return app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh());
    }

    private function confirmedOrder(
        int $packageCount = 1,
        ?string $singleSide = 'LELAKI',
        string $customerName = 'Staff Visibility Test'
    ) {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => $customerName,
        ];

        if ($packageCount === 1) {
            $data['side'] = $singleSide;
        }

        $order = app(CreateOrderService::class)->create($data);

        $sides = [];

        foreach ($order->packageSides()->get() as $side) {
            $sides[$side->side] = [
                'design' => [
                    'theme' => $side->side === 'LELAKI'
                        ? 'SONGKET'
                        : 'ISLAMIC',
                    'design_code' => $side->side === 'LELAKI'
                        ? 'L101'
                        : 'P202',
                ],
                'parents' => [
                    'father_name' => 'Bapa '.$side->side,
                    'mother_name' => 'Ibu '.$side->side,
                ],
                'event' => [
                    'event_date' => '2026-12-20',
                    'meal_time' => '12:00',
                    'venue_name' => 'Dewan '.$side->side,
                    'full_address' => 'Alamat '.$side->side,
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

        app(SaveOrderDraftService::class)->save($order, [
            'card_quantity' => 200,
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

    private function approvedOrder(
        string $customerName = 'Approved Visibility Test'
    ) {
        $order = $this->confirmedOrder(
            1,
            'LELAKI',
            $customerName
        );

        $designJobs = $this->initializeDesignJobs($order);

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

    private function paidOrder(
        string $customerName = 'Paid Visibility Test'
    ) {
        $order = $this->approvedOrder($customerName);

        $payment = app(CreateBalancePaymentService::class)
            ->create($order->fresh(), '250.00');

        $payment->update([
            'provider' => 'TEST',
            'provider_reference' => 'VISIBILITY-REF-'.$payment->id,
        ]);

        app(HandlePaymentCallbackService::class)->handle([
            'provider' => 'TEST',
            'provider_reference' => $payment->provider_reference,
            'provider_event_id' => 'VISIBILITY-EVENT-'.$payment->id,
            'status' => 'PAID',
        ]);

        return $order->fresh();
    }

    private function printedOrder(
        string $customerName = 'Printed Visibility Test'
    ) {
        $order = $this->paidOrder($customerName);

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
