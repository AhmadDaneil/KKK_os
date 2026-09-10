<?php

namespace Tests\Feature\Design;

use App\Models\User;
use App\Services\Design\ApproveArtworkService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Design\ResumeDesignAfterCorrectionService;
use App\Services\Design\StartDesignJobService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtworkReviewApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_can_be_marked_ready_only_after_artwork_exists(): void
    {
        [$order, $job, $designer] = $this->preparedDesignJob();

        $this->expectException(\RuntimeException::class);

        app(MarkDesignReadyService::class)->markReady($job, $designer);
    }

    public function test_customer_can_request_correction_and_designer_can_create_next_version(): void
    {
        [$order, $job, $designer] = $this->preparedDesignJob();

        app(CreateArtworkVersionService::class)->create($job, [
            'storage_path' => 'artworks/v1.pdf',
            'original_filename' => 'v1.pdf',
        ], $designer);

        $job = app(MarkDesignReadyService::class)->markReady($job->fresh(), $designer);

        $job = app(RequestArtworkCorrectionService::class)->request(
            $job,
            'Sila betulkan ejaan nama.'
        );

        $this->assertSame('CORRECTION_REQUESTED', $job->status);

        $job = app(ResumeDesignAfterCorrectionService::class)->resume($job, $designer);

        $v2 = app(CreateArtworkVersionService::class)->create($job, [
            'storage_path' => 'artworks/v2.pdf',
            'original_filename' => 'v2.pdf',
        ], $designer);

        $this->assertSame(2, $v2->version_number);
        $this->assertDatabaseCount('artwork_versions', 2);
    }

    public function test_customer_can_approve_ready_artwork(): void
    {
        [$order, $job, $designer] = $this->preparedDesignJob();

        app(CreateArtworkVersionService::class)->create($job, [
            'storage_path' => 'artworks/v1.pdf',
            'original_filename' => 'v1.pdf',
        ], $designer);

        $job = app(MarkDesignReadyService::class)->markReady($job->fresh(), $designer);
        $job = app(ApproveArtworkService::class)->approve($job);

        $this->assertSame('DESIGN_APPROVED', $job->status);

        $syncedOrder = app(SyncOrderDesignStatusService::class)->sync($order->fresh());

        $this->assertSame('DESIGN_APPROVED', $syncedOrder->status);
    }

    public function test_two_package_order_is_not_fully_approved_until_both_jobs_are_approved(): void
    {
        $order = $this->confirmedOrder(2);

        app(GenerateMergeJobsForOrderService::class)->generate($order);
        $jobs = app(InitializeDesignJobsForOrderService::class)->initialize($order->fresh());

        $designer = User::factory()->create();

        foreach ($jobs as $job) {
            app(StartDesignJobService::class)->start($job, $designer);
            app(CreateArtworkVersionService::class)->create($job->fresh(), [
                'storage_path' => "artworks/{$job->side}-v1.pdf",
            ], $designer);
            app(MarkDesignReadyService::class)->markReady($job->fresh(), $designer);
        }

        app(ApproveArtworkService::class)->approve($jobs->firstWhere('side', 'LELAKI')->fresh());

        $partiallyApproved = app(SyncOrderDesignStatusService::class)->sync($order->fresh());

        $this->assertNotSame('DESIGN_APPROVED', $partiallyApproved->status);

        app(ApproveArtworkService::class)->approve($jobs->firstWhere('side', 'PEREMPUAN')->fresh());

        $fullyApproved = app(SyncOrderDesignStatusService::class)->sync($order->fresh());

        $this->assertSame('DESIGN_APPROVED', $fullyApproved->status);
    }

    private function preparedDesignJob(): array
    {
        $order = $this->confirmedOrder(1, 'LELAKI');

        app(GenerateMergeJobsForOrderService::class)->generate($order);

        $job = app(InitializeDesignJobsForOrderService::class)
            ->initialize($order->fresh())
            ->first();

        $designer = User::factory()->create();

        $job = app(StartDesignJobService::class)->start($job, $designer);

        return [$order, $job, $designer];
    }

    private function confirmedOrder(int $packageCount, ?string $singleSide = null)
    {
        $data = [
            'package_count' => $packageCount,
            'customer_name' => 'Artwork Review Test',
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

        return app(ConfirmOrderDetailsService::class)->confirm($order->fresh());
    }
}
