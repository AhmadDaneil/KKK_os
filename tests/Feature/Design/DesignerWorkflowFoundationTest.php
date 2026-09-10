<?php

namespace Tests\Feature\Design;

use App\Models\User;
use App\Services\Design\AssignDesignJobService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\StartDesignJobService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DesignerWorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_package_creates_exactly_one_design_job(): void
    {
        $order = $this->confirmedOrder(1, 'LELAKI');
        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $jobs = app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        $this->assertCount(1, $jobs);
        $this->assertDatabaseCount('design_jobs', 1);
        $this->assertSame('LELAKI', $jobs->first()->side);
        $this->assertSame('READY_FOR_DESIGN', $jobs->first()->status);
    }

    public function test_two_packages_create_two_independent_design_jobs(): void
    {
        $order = $this->confirmedOrder(2);
        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $jobs = app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        $this->assertCount(2, $jobs);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('design_jobs', 2);
        $this->assertNotNull($jobs->firstWhere('side', 'LELAKI'));
        $this->assertNotNull($jobs->firstWhere('side', 'PEREMPUAN'));
    }

    public function test_initialization_is_idempotent(): void
    {
        $order = $this->confirmedOrder(2);
        app(GenerateMergeJobsForOrderService::class)->generate($order);

        app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());
        app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        $this->assertDatabaseCount('design_jobs', 2);
        $this->assertDatabaseCount('design_job_events', 2);
    }

    public function test_design_job_can_be_assigned_and_started(): void
    {
        $order = $this->confirmedOrder(1, 'PEREMPUAN');
        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $job = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())->first();

        $designer = User::factory()->create();

        $assigned = app(AssignDesignJobService::class)->assign($job, $designer);
        $this->assertSame($designer->id, $assigned->assigned_user_id);
        $this->assertNotNull($assigned->assigned_at);

        $started = app(StartDesignJobService::class)->start($assigned, $designer);
        $this->assertSame('DESIGN_IN_PROGRESS', $started->status);
        $this->assertNotNull($started->started_at);
    }

    public function test_artwork_versions_increment_without_overwriting(): void
    {
        $order = $this->confirmedOrder(1, 'LELAKI');
        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $job = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())->first();

        $designer = User::factory()->create();
        app(AssignDesignJobService::class)->assign($job, $designer);
        $job = app(StartDesignJobService::class)->start($job->fresh(), $designer);

        $v1 = app(CreateArtworkVersionService::class)->create($job, [
            'storage_path' => 'artworks/order-L/v1.pdf',
            'original_filename' => 'artwork-v1.pdf',
            'mime_type' => 'application/pdf',
        ], $designer);

        $v2 = app(CreateArtworkVersionService::class)->create($job->fresh(), [
            'storage_path' => 'artworks/order-L/v2.pdf',
            'original_filename' => 'artwork-v2.pdf',
            'mime_type' => 'application/pdf',
        ], $designer);

        $this->assertSame(1, $v1->version_number);
        $this->assertSame(2, $v2->version_number);
        $this->assertDatabaseCount('artwork_versions', 2);
        $this->assertNotSame($v1->storage_path, $v2->storage_path);
    }

    public function test_unconfirmed_order_cannot_initialize_design_jobs(): void
    {
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Incomplete',
        ]);

        $this->expectException(RuntimeException::class);
        app(InitializeDesignJobsForOrderService::class)->initialize($order);
    }

    private function confirmedOrder(int $packageCount, ?string $side = null)
    {
        $createData = ['package_count' => $packageCount, 'customer_name' => 'Designer Test'];

        if ($packageCount === 1) {
            $createData['side'] = $side;
        }

        $order = app(CreateOrderService::class)->create($createData);
        $sides = [];

        foreach ($order->packageSides()->get() as $packageSide) {
            $sides[$packageSide->side] = [
                'design' => [
                    'design_code' => $packageSide->side === 'LELAKI' ? 'L101' : 'P202',
                ],
                'parents' => [
                    'father_name' => 'Bapa ' . $packageSide->side,
                    'mother_name' => 'Ibu ' . $packageSide->side,
                ],
                'event' => [
                    'event_date' => '2026-12-20',
                    'meal_time' => '12:00',
                    'venue_name' => 'Dewan ' . $packageSide->side,
                    'full_address' => 'Alamat ' . $packageSide->side,
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

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }
}
