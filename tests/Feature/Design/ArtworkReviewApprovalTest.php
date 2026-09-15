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
use App\Services\Orders\GenerateOrderAccessLinkService;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
            'preview_storage_path' => 'artworks/v1-preview.png',
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
            'preview_storage_path' => 'artworks/v2-preview.png',
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
            'preview_storage_path' => 'artworks/v1-preview.png',
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
                'preview_storage_path' => "artworks/{$job->side}-v1-preview.png",
            ], $designer);
            app(MarkDesignReadyService::class)->markReady($job->fresh(), $designer);
        }

        app(ApproveArtworkService::class)->approve($jobs->firstWhere('side', 'LELAKI')->fresh());

        $partiallyApproved = app(SyncOrderDesignStatusService::class)
        ->sync($order->fresh());

        $this->assertSame('DESIGN_READY', $partiallyApproved->status);

        $this->assertNotSame(
        'DESIGN_APPROVED',
        $partiallyApproved->status
        );

        app(ApproveArtworkService::class)->approve($jobs->firstWhere('side', 'PEREMPUAN')->fresh());

        $fullyApproved = app(SyncOrderDesignStatusService::class)->sync($order->fresh());

        $this->assertSame('DESIGN_APPROVED', $fullyApproved->status);
    }

    public function test_customer_can_preview_latest_artwork_through_authorized_order_session(): void
{
    Storage::fake('local');

    [$order, $job, $designer] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/latest-preview.png',
        'latest-preview-content'
    );

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/latest-source.psd',
        'preview_storage_path' => 'artworks/test/latest-preview.png',
        'original_filename' => 'latest-source.psd',
        'mime_type' => 'application/octet-stream',
    ], $designer);

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    app(SyncOrderDesignStatusService::class)
        ->sync($order->fresh());

    $link = app(GenerateOrderAccessLinkService::class)
        ->generate($order->fresh());

    $this->get($this->requestUri($link))
        ->assertRedirect(
            route('orders.dashboard', [
                'orderId' => $order->order_id,
            ])
        );

    $response = $this->get(
        route('orders.artwork.preview', [
            'orderId' => $order->order_id,
            'designJobId' => $job->id,
        ])
    );

    $response->assertOk();

    $this->assertSame(
        'latest-preview-content',
        $response->streamedContent()
    );
}

public function test_artwork_preview_requires_authorized_customer_session(): void
{
    Storage::fake('local');

    [$order, $job, $designer] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/session-required.png',
        'session-required-content'
    );

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/session-required-source.psd',
        'preview_storage_path' => 'artworks/test/session-required.png',
        'original_filename' => 'session-required-source.psd',
        'mime_type' => 'application/octet-stream',
    ], $designer);

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    $this->get(
        route('orders.artwork.preview', [
            'orderId' => $order->order_id,
            'designJobId' => $job->id,
        ])
    )->assertNotFound();
}

public function test_customer_cannot_preview_design_job_from_another_order(): void
{
    Storage::fake('local');

    [$orderA, $jobA, $designerA] = $this->preparedDesignJob();
    [$orderB, $jobB, $designerB] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/order-b-preview.png',
        'order-b-preview-content'
    );

    app(CreateArtworkVersionService::class)->create($jobB, [
        'storage_path' => 'artworks/test/order-b-source.psd',
        'preview_storage_path' => 'artworks/test/order-b-preview.png',
        'original_filename' => 'order-b-source.psd',
        'mime_type' => 'application/octet-stream',
    ], $designerB);

    $jobB = app(MarkDesignReadyService::class)
        ->markReady($jobB->fresh(), $designerB);

    $link = app(GenerateOrderAccessLinkService::class)
        ->generate($orderA->fresh());

    $this->get($this->requestUri($link))
        ->assertRedirect(
            route('orders.dashboard', [
                'orderId' => $orderA->order_id,
            ])
        );

    $this->get(
        route('orders.artwork.preview', [
            'orderId' => $orderA->order_id,
            'designJobId' => $jobB->id,
        ])
    )->assertNotFound();
}

public function test_artwork_preview_prefers_preview_file_over_original_file(): void
{
    Storage::fake('local');

    [$order, $job, $designer] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/original.pdf',
        'original-file-content'
    );

    Storage::disk('local')->put(
        'artworks/test/preview.png',
        'preview-file-content'
    );

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/original.pdf',
        'preview_storage_path' => 'artworks/test/preview.png',
        'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf',
    ], $designer);

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    $link = app(GenerateOrderAccessLinkService::class)
        ->generate($order->fresh());

    $this->get($this->requestUri($link));

    $response = $this->get(
        route('orders.artwork.preview', [
            'orderId' => $order->order_id,
            'designJobId' => $job->id,
        ])
    );

    $response->assertOk();

    $this->assertSame(
        'preview-file-content',
        $response->streamedContent()
    );
}

public function test_customer_preview_never_falls_back_to_source_artwork(): void
{
    Storage::fake('local');

    [$order, $job, $designer] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/private-source.psd',
        'private-source-content'
    );

    $artwork = app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/private-source.psd',
        'original_filename' => 'private-source.psd',
        'mime_type' => 'application/octet-stream',
    ], $designer);

    /*
     * Simulate legacy/inconsistent persisted data.
     * New production flow cannot reach DESIGN_READY without a
     * customer preview, but the HTTP boundary must still never
     * expose storage_path.
     */
    $job->update([
        'status' => 'DESIGN_READY',
        'design_ready_at' => now(),
    ]);

    $this->assertNull(
        $artwork->fresh()->preview_storage_path
    );

    $link = app(GenerateOrderAccessLinkService::class)
        ->generate($order->fresh());

    $this->get($this->requestUri($link))
        ->assertRedirect(
            route('orders.dashboard', [
                'orderId' => $order->order_id,
            ])
        );

    $this->get(
        route('orders.artwork.preview', [
            'orderId' => $order->order_id,
            'designJobId' => $job->id,
        ])
    )->assertNotFound();
}

public function test_cancelled_order_cannot_approve_artwork(): void
{
    [$order, $job, $designer] = $this->preparedDesignJob();

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/archived-v1.pdf',
        'preview_storage_path' => 'artworks/test/archived-v1-preview.png',
        'original_filename' => 'archived-v1.pdf',
    ], $designer);

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    app(OrderLifecycleService::class)->cancel(
        $order->fresh(),
        'Artwork approval terminal guard test',
        null,
        'TEST'
    );

    $this->expectException(RuntimeException::class);

    app(ApproveArtworkService::class)
        ->approve($job->fresh());
}

public function test_archived_order_cannot_request_artwork_correction(): void
{
    [$order, $job, $designer] = $this->preparedDesignJob();

    app(CreateArtworkVersionService::class)->create($job, [
    'storage_path' => 'artworks/test/archived-v1.pdf',
    'preview_storage_path' => 'artworks/test/archived-v1-preview.png',
    'original_filename' => 'archived-v1.pdf',
    ], $designer);

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    app(OrderLifecycleService::class)->archive(
        $order->fresh(),
        'Artwork correction terminal guard test',
        null,
        'TEST'
    );

    $this->expectException(RuntimeException::class);

    app(RequestArtworkCorrectionService::class)->request(
        $job->fresh(),
        'Pembetulan tidak sepatutnya dibenarkan.'
    );
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

        public function test_customer_cannot_preview_artwork_while_design_is_still_in_progress(): void
{
    Storage::fake('local');

    [$order, $job, $designer] = $this->preparedDesignJob();

    Storage::disk('local')->put(
        'artworks/test/in-progress-preview.png',
        'unreleased-artwork-content'
    );

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/in-progress-preview.png',
        'original_filename' => 'in-progress-preview.png',
        'mime_type' => 'image/png',
    ], $designer);

    // Jangan panggil MarkDesignReadyService.
    // Job mesti kekal DESIGN_IN_PROGRESS.
    $this->assertSame(
        'DESIGN_IN_PROGRESS',
        $job->fresh()->status
    );

    $link = app(GenerateOrderAccessLinkService::class)
        ->generate($order->fresh());

    $this->get($this->requestUri($link))
        ->assertRedirect(
            route('orders.dashboard', [
                'orderId' => $order->order_id,
            ])
        );

    $this->get(
        route('orders.artwork.preview', [
            'orderId' => $order->order_id,
            'designJobId' => $job->id,
        ])
    )->assertNotFound();
}

    public function test_design_cannot_be_marked_ready_without_customer_preview(): void
{
    [$order, $job, $designer] = $this->preparedDesignJob();

    app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/source-only.psd',
        'original_filename' => 'source-only.psd',
        'mime_type' => 'application/octet-stream',
    ], $designer);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('customer preview');

    app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);
    }

    public function test_design_can_be_marked_ready_when_customer_preview_exists(): void
{
    [$order, $job, $designer] = $this->preparedDesignJob();

    $artwork = app(CreateArtworkVersionService::class)->create($job, [
        'storage_path' => 'artworks/test/source.psd',
        'preview_storage_path' => 'artworks/test/customer-preview.png',
        'original_filename' => 'source.psd',
        'mime_type' => 'application/octet-stream',
    ], $designer);

    $this->assertSame(
        'artworks/test/customer-preview.png',
        $artwork->preview_storage_path
    );

    $job = app(MarkDesignReadyService::class)
        ->markReady($job->fresh(), $designer);

    $this->assertSame(
        'DESIGN_READY',
        $job->status
    );
    }

    private function requestUri(string $url): string
    {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    return $query === ''
        ? $path
        : $path . '?' . $query;
    }
}
