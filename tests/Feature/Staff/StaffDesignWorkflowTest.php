<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use App\Services\Design\AssignDesignJobService;
use App\Services\Design\CreateArtworkVersionService;
use App\Services\Design\InitializeDesignJobsForOrderService;
use App\Services\Design\MarkDesignReadyService;
use App\Services\Design\StartDesignJobService;
use App\Models\ArtworkVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Merge\GenerateMergeJobsForOrderService;
use App\Services\Orders\ConfirmOrderDetailsService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;
use RuntimeException;

class StaffDesignWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_active_designer_can_start_design_job(): void
    {
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer);

        $response = $this->actingAs($designer)->post(
            route('staff.design-jobs.start', $job)
        );

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $job->status
        );

        $event = $job->events()
            ->where('event_type', 'DESIGN_STARTED')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $designer->id,
            $event->actor_user_id
        );
    }

    public function test_operation_management_cannot_start_job_assigned_to_designer(): void
    {
        $designer = $this->designer();
        $operationManagement = $this->staff(User::ROLE_OM);
        $job = $this->designJob();

        $this->assign($job, $designer);

        $this->actingAs($operationManagement)
            ->post(route('staff.design-jobs.start', $job))
            ->assertForbidden();

        $this->assertSame(
            'READY_FOR_DESIGN',
            $job->fresh()->status
        );
    }

    public function test_other_designer_cannot_start_job_assigned_to_someone_else(): void
    {
        $assignedDesigner = $this->designer();
        $otherDesigner = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $assignedDesigner);

        $this->actingAs($otherDesigner)
            ->post(route('staff.design-jobs.start', $job))
            ->assertNotFound();

        $this->assertSame(
            'READY_FOR_DESIGN',
            $job->fresh()->status
        );
    }

    private function authorizeAssignedPrintingStaff(
    Request $request,
    PrintJob $printJob
    ): void {
    abort_unless(
        $printJob->assigned_user_id === $request->user()->id,
        404
    );
    }

    public function test_printing_and_packing_staff_cannot_start_design_job(): void
    {
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer);

        foreach ([
            User::ROLE_PRINTING,
            User::ROLE_PACKING,
        ] as $role) {
            $staff = $this->staff($role);

            $this->actingAs($staff)
                ->post(route('staff.design-jobs.start', $job))
                ->assertForbidden();

            $this->assertSame(
                'READY_FOR_DESIGN',
                $job->fresh()->status
            );
        }
    }

    public function test_guest_is_redirected_to_staff_login_when_starting_design_job(): void
    {
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer);

        $this->post(
            route('staff.design-jobs.start', $job)
        )->assertRedirect(route('staff.login'));

        $this->assertSame(
            'READY_FOR_DESIGN',
            $job->fresh()->status
        );
    }

    public function test_assigned_designer_can_resume_correction_work(): void
    {
        $designer = $this->designer();
        $job = $this->correctionRequestedJob($designer);

        $response = $this->actingAs($designer)->post(
            route(
                'staff.design-jobs.resume-correction',
                $job
            )
        );

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $job->status
        );

        $event = $job->events()
            ->where(
                'event_type',
                'CORRECTION_WORK_STARTED'
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $designer->id,
            $event->actor_user_id
        );
    }

    public function test_other_designer_cannot_resume_correction_job(): void
    {
        $assignedDesigner = $this->designer();
        $otherDesigner = $this->designer();

        $job = $this->correctionRequestedJob(
            $assignedDesigner
        );

        $this->actingAs($otherDesigner)
            ->post(
                route(
                    'staff.design-jobs.resume-correction',
                    $job
                )
            )
            ->assertNotFound();

        $this->assertSame(
            'CORRECTION_REQUESTED',
            $job->fresh()->status
        );
    }

    public function test_invalid_design_status_returns_safe_session_error_instead_of_500(): void
    {
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer);

        $job->update([
            'status' => 'DESIGN_READY',
        ]);

        $response = $this
            ->from(route('staff.orders.index'))
            ->actingAs($designer)
            ->post(
                route(
                    'staff.design-jobs.start',
                    $job
                )
            );

        $response
            ->assertRedirect(
                route('staff.orders.index')
            )
            ->assertSessionHasErrors('design_job');

        $this->assertSame(
            'DESIGN_READY',
            $job->fresh()->status
        );
    }

    public function test_two_package_design_sides_remain_independently_authorized(): void
    {
        $lelakiDesigner = $this->designer();
        $perempuanDesigner = $this->designer();

        $order = $this->confirmedOrder(2);

        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        $jobs = app(
            InitializeDesignJobsForOrderService::class
        )->initialize($order->fresh());

        $this->assertCount(2, $jobs);

        $lelaki = $jobs->firstWhere(
            'side',
            'LELAKI'
        );

        $perempuan = $jobs->firstWhere(
            'side',
            'PEREMPUAN'
        );

        $this->assertNotNull($lelaki);
        $this->assertNotNull($perempuan);

        $this->assign(
            $lelaki,
            $lelakiDesigner
        );

        $this->assign(
            $perempuan,
            $perempuanDesigner
        );

        $this->actingAs($lelakiDesigner)
            ->post(
                route(
                    'staff.design-jobs.start',
                    $lelaki
                )
            )
            ->assertRedirect();

        $this->actingAs($lelakiDesigner)
            ->post(
                route(
                    'staff.design-jobs.start',
                    $perempuan
                )
            )
            ->assertNotFound();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $lelaki->fresh()->status
        );

        $this->assertSame(
            'READY_FOR_DESIGN',
            $perempuan->fresh()->status
        );

        $this->actingAs($perempuanDesigner)
            ->post(
                route(
                    'staff.design-jobs.start',
                    $perempuan
                )
            )
            ->assertRedirect();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $perempuan->fresh()->status
        );
    }

    public function test_assigned_designer_can_upload_private_source_and_customer_preview(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(
            route('staff.design-jobs.start', $job)
        )
        ->assertRedirect();

    $job->refresh();

    $source = UploadedFile::fake()->create(
        'kad-kahwin.pdf',
        1024,
        'application/pdf'
    );

    $preview = UploadedFile::fake()->image(
        'preview.jpg',
        1200,
        800
    );

    $response = $this->actingAs($designer)->post(
        route('staff.design-jobs.artwork.store', $job),
        [
            'source_artwork' => $source,
            'customer_preview' => $preview,
            'internal_note' => 'Artwork pertama.',
        ]
    );

    $response
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas(
            'status',
            'Artwork version 1 uploaded successfully.'
        );

    $artwork = ArtworkVersion::query()
        ->where('design_job_id', $job->id)
        ->sole();

    $this->assertSame(1, $artwork->version_number);
    $this->assertSame('local', $artwork->storage_disk);
    $this->assertSame(
        'kad-kahwin.pdf',
        $artwork->original_filename
    );
    $this->assertSame(
        $designer->id,
        $artwork->created_by_user_id
    );
    $this->assertSame(
        'Artwork pertama.',
        $artwork->internal_note
    );

    $this->assertMatchesRegularExpression(
        '#^artworks/' .
        preg_quote((string) $job->order_id, '#') .
        '/LELAKI/[0-9a-f-]+/source\.pdf$#',
        $artwork->storage_path
    );

    $this->assertMatchesRegularExpression(
        '#^artworks/' .
        preg_quote((string) $job->order_id, '#') .
        '/LELAKI/[0-9a-f-]+/preview\.jpg$#',
        $artwork->preview_storage_path
    );

    $this->assertSame(
        64,
        strlen($artwork->checksum_sha256)
    );

    Storage::disk('local')->assertExists(
        $artwork->storage_path
    );

    Storage::disk('local')->assertExists(
        $artwork->preview_storage_path
    );

    $event = $job->events()
        ->where(
            'event_type',
            'ARTWORK_VERSION_CREATED'
        )
        ->latest('id')
        ->firstOrFail();

    $this->assertSame(
        $designer->id,
        $event->actor_user_id
    );

    $this->assertSame(
        $artwork->id,
        $event->metadata['artwork_version_id']
    );

    $this->assertSame(
        1,
        $event->metadata['version_number']
    );
}

public function test_other_designer_cannot_upload_artwork_to_assigned_job(): void
{
    Storage::fake('local');

    $assignedDesigner = $this->designer();
    $otherDesigner = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $assignedDesigner);

    $this->actingAs($assignedDesigner)
        ->post(
            route('staff.design-jobs.start', $job)
        )
        ->assertRedirect();

    $response = $this->actingAs($otherDesigner)->post(
        route('staff.design-jobs.artwork.store', $job),
        [
            'source_artwork' =>
                UploadedFile::fake()->create(
                    'kad-kahwin.pdf',
                    1024,
                    'application/pdf'
                ),
            'customer_preview' =>
                UploadedFile::fake()->image(
                    'preview.jpg'
                ),
        ]
    );

    $response->assertNotFound();

    $this->assertSame(
        0,
        ArtworkVersion::query()
            ->where('design_job_id', $job->id)
            ->count()
    );

    $this->assertSame(
        [],
        Storage::disk('local')->allFiles()
    );
}

public function test_artwork_upload_is_rejected_before_design_is_in_progress(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route(
                'staff.design-jobs.artwork.store',
                $job
            ),
            [
                'source_artwork' =>
                    UploadedFile::fake()->create(
                        'kad-kahwin.pdf',
                        1024,
                        'application/pdf'
                    ),
                'customer_preview' =>
                    UploadedFile::fake()->image(
                        'preview.jpg'
                    ),
            ]
        );

    $response
        ->assertRedirect(
            route('staff.orders.index')
        )
        ->assertSessionHasErrors('design_job');

    $this->assertSame(
        'READY_FOR_DESIGN',
        $job->fresh()->status
    );

    $this->assertSame(
        0,
        ArtworkVersion::query()
            ->where('design_job_id', $job->id)
            ->count()
    );

    $this->assertSame(
        [],
        Storage::disk('local')->allFiles()
    );
}

public function test_artwork_upload_requires_source_and_customer_preview(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(
            route('staff.design-jobs.start', $job)
        )
        ->assertRedirect();

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route(
                'staff.design-jobs.artwork.store',
                $job
            ),
            []
        );

    $response
        ->assertRedirect(
            route('staff.orders.index')
        )
        ->assertSessionHasErrors([
            'source_artwork',
            'customer_preview',
        ]);

    $this->assertSame(
        0,
        ArtworkVersion::query()
            ->where('design_job_id', $job->id)
            ->count()
    );

    $this->assertSame(
        [],
        Storage::disk('local')->allFiles()
    );
    }

    public function test_second_artwork_upload_creates_version_two_without_overwriting_version_one(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(route('staff.design-jobs.start', $job))
        ->assertRedirect();

    $this->actingAs($designer)->post(
        route('staff.design-jobs.artwork.store', $job),
        [
            'source_artwork' => UploadedFile::fake()->create(
                'version-1.pdf',
                1024,
                'application/pdf'
            ),
            'customer_preview' => UploadedFile::fake()->image(
                'preview-1.jpg'
            ),
        ]
    )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $versionOne = ArtworkVersion::query()
        ->where('design_job_id', $job->id)
        ->where('version_number', 1)
        ->sole();

    $this->actingAs($designer)->post(
        route('staff.design-jobs.artwork.store', $job),
        [
            'source_artwork' => UploadedFile::fake()->create(
                'version-2.pdf',
                2048,
                'application/pdf'
            ),
            'customer_preview' => UploadedFile::fake()->image(
                'preview-2.jpg'
            ),
        ]
    )
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas(
            'status',
            'Artwork version 2 uploaded successfully.'
        );

    $versions = ArtworkVersion::query()
        ->where('design_job_id', $job->id)
        ->orderBy('version_number')
        ->get();

    $this->assertCount(2, $versions);
    $this->assertSame(
        [1, 2],
        $versions->pluck('version_number')->all()
    );

    $versionTwo = $versions->last();

    $this->assertNotSame(
        $versionOne->storage_path,
        $versionTwo->storage_path
    );

    $this->assertNotSame(
        $versionOne->preview_storage_path,
        $versionTwo->preview_storage_path
    );

    Storage::disk('local')->assertExists(
        $versionOne->storage_path
    );

    Storage::disk('local')->assertExists(
        $versionOne->preview_storage_path
    );

    Storage::disk('local')->assertExists(
        $versionTwo->storage_path
    );

    Storage::disk('local')->assertExists(
        $versionTwo->preview_storage_path
    );
}

public function test_two_package_artwork_uploads_keep_sides_and_versions_independent(): void
{
    Storage::fake('local');

    $lelakiDesigner = $this->designer();
    $perempuanDesigner = $this->designer();

    $order = $this->confirmedOrder(2);

    app(GenerateMergeJobsForOrderService::class)
        ->generate($order);

    $jobs = app(InitializeDesignJobsForOrderService::class)
        ->initialize($order->fresh());

    $this->assertCount(2, $jobs);

    $lelaki = $jobs->firstWhere('side', 'LELAKI');
    $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

    $this->assertNotNull($lelaki);
    $this->assertNotNull($perempuan);

    $this->assign($lelaki, $lelakiDesigner);
    $this->assign($perempuan, $perempuanDesigner);

    $this->actingAs($lelakiDesigner)
        ->post(route('staff.design-jobs.start', $lelaki))
        ->assertRedirect();

    $this->actingAs($perempuanDesigner)
        ->post(route('staff.design-jobs.start', $perempuan))
        ->assertRedirect();

    $this->actingAs($lelakiDesigner)->post(
        route('staff.design-jobs.artwork.store', $lelaki),
        [
            'source_artwork' => UploadedFile::fake()->create(
                'lelaki.pdf',
                1024,
                'application/pdf'
            ),
            'customer_preview' => UploadedFile::fake()->image(
                'lelaki-preview.jpg'
            ),
        ]
    )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($perempuanDesigner)->post(
        route('staff.design-jobs.artwork.store', $perempuan),
        [
            'source_artwork' => UploadedFile::fake()->create(
                'perempuan.pdf',
                1024,
                'application/pdf'
            ),
            'customer_preview' => UploadedFile::fake()->image(
                'perempuan-preview.jpg'
            ),
        ]
    )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $lelakiArtwork = ArtworkVersion::query()
        ->where('design_job_id', $lelaki->id)
        ->sole();

    $perempuanArtwork = ArtworkVersion::query()
        ->where('design_job_id', $perempuan->id)
        ->sole();

    $this->assertSame(1, $lelakiArtwork->version_number);
    $this->assertSame(1, $perempuanArtwork->version_number);

    $this->assertStringContainsString(
        '/LELAKI/',
        $lelakiArtwork->storage_path
    );

    $this->assertStringContainsString(
        '/PEREMPUAN/',
        $perempuanArtwork->storage_path
    );

    $this->assertNotSame(
        $lelakiArtwork->storage_path,
        $perempuanArtwork->storage_path
    );

    $this->assertNotSame(
        $lelakiArtwork->preview_storage_path,
        $perempuanArtwork->preview_storage_path
    );

    Storage::disk('local')->assertExists(
        $lelakiArtwork->storage_path
    );

    Storage::disk('local')->assertExists(
        $lelakiArtwork->preview_storage_path
    );

    Storage::disk('local')->assertExists(
        $perempuanArtwork->storage_path
    );

    Storage::disk('local')->assertExists(
        $perempuanArtwork->preview_storage_path
    );
}

public function test_uploaded_files_are_cleaned_up_when_artwork_creation_service_fails(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(route('staff.design-jobs.start', $job))
        ->assertRedirect();

    $this->mock(
        CreateArtworkVersionService::class,
        function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(
                    new RuntimeException(
                        'Simulated artwork creation failure.'
                    )
                );
        }
    );

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route('staff.design-jobs.artwork.store', $job),
            [
                'source_artwork' => UploadedFile::fake()->create(
                    'failed.pdf',
                    1024,
                    'application/pdf'
                ),
                'customer_preview' => UploadedFile::fake()->image(
                    'failed-preview.jpg'
                ),
            ]
        );

    $response
        ->assertRedirect(route('staff.orders.index'))
        ->assertSessionHasErrors('design_job');

    $this->assertSame(
        0,
        ArtworkVersion::query()
            ->where('design_job_id', $job->id)
            ->count()
    );

    $this->assertSame(
        [],
        Storage::disk('local')->allFiles()
    );

    $this->assertSame(
        'DESIGN_IN_PROGRESS',
        $job->fresh()->status
    );
    }
    public function test_unexpected_artwork_creation_failure_is_reported(): void
{
    Storage::fake('local');

    Exceptions::fake();

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(route('staff.design-jobs.start', $job))
        ->assertRedirect();

    $this->mock(
        CreateArtworkVersionService::class,
        function ($mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(
                    new \Exception(
                        'Simulated unexpected artwork creation failure.'
                    )
                );
        }
    );

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route('staff.design-jobs.artwork.store', $job),
            [
                'source_artwork' => UploadedFile::fake()->create(
                    'failed.pdf',
                    1024,
                    'application/pdf'
                ),
                'customer_preview' => UploadedFile::fake()->image(
                    'failed-preview.jpg'
                ),
            ]
        );

    $response
        ->assertRedirect(route('staff.orders.index'))
        ->assertSessionHasErrors([
            'design_job' =>
                'Artwork could not be uploaded. Please try again.',
        ]);

    Exceptions::assertReported(
        function (\Exception $exception): bool {
            return $exception->getMessage()
                === 'Simulated unexpected artwork creation failure.';
        }
    );

    Exceptions::assertReportedCount(1);

    $this->assertSame(
        0,
        ArtworkVersion::query()
            ->where('design_job_id', $job->id)
            ->count()
    );
}
    public function test_assigned_designer_can_mark_uploaded_artwork_ready(): void
{
    Storage::fake('local');

    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(route('staff.design-jobs.start', $job))
        ->assertRedirect();

    $this->actingAs($designer)->post(
        route('staff.design-jobs.artwork.store', $job),
        [
            'source_artwork' => UploadedFile::fake()->create(
                'ready.pdf',
                1024,
                'application/pdf'
            ),
            'customer_preview' => UploadedFile::fake()->image(
                'ready-preview.jpg'
            ),
        ]
    )
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route('staff.design-jobs.mark-ready', $job)
        );

    $response
        ->assertRedirect(route('staff.orders.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(
            'status',
            'Artwork marked ready for customer review.'
        );

    $job->refresh();

    $this->assertSame('DESIGN_READY', $job->status);
    $this->assertNotNull($job->design_ready_at);

    $event = $job->events()
        ->where('event_type', 'DESIGN_READY')
        ->latest('id')
        ->firstOrFail();

    $this->assertSame(
        $designer->id,
        $event->actor_user_id
    );

    $reviewAction = $job->reviewActions()
        ->where('action', 'DESIGN_READY')
        ->latest('id')
        ->firstOrFail();

    $this->assertSame(
        $designer->id,
        $reviewAction->actor_user_id
    );
}

public function test_mark_ready_requires_an_artwork_version(): void
{
    $designer = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $designer);

    $this->actingAs($designer)
        ->post(route('staff.design-jobs.start', $job))
        ->assertRedirect();

    $response = $this
        ->from(route('staff.orders.index'))
        ->actingAs($designer)
        ->post(
            route('staff.design-jobs.mark-ready', $job)
        );

    $response
        ->assertRedirect(route('staff.orders.index'))
        ->assertSessionHasErrors('design_job');

    $this->assertSame(
        'DESIGN_IN_PROGRESS',
        $job->fresh()->status
    );
}

public function test_other_designer_cannot_mark_assigned_job_ready(): void
{
    $assignedDesigner = $this->designer();
    $otherDesigner = $this->designer();
    $job = $this->designJob();

    $this->assign($job, $assignedDesigner);

    app(StartDesignJobService::class)
        ->start($job, $assignedDesigner);

    app(CreateArtworkVersionService::class)->create(
        $job->fresh(),
        [
            'storage_path' => 'artworks/test/source.pdf',
            'preview_storage_path' =>
                'artworks/test/preview.jpg',
            'original_filename' => 'source.pdf',
            'mime_type' => 'application/pdf',
        ],
        $assignedDesigner
    );

    $this->actingAs($otherDesigner)
        ->post(
            route('staff.design-jobs.mark-ready', $job)
        )
        ->assertNotFound();

    $this->assertSame(
        'DESIGN_IN_PROGRESS',
        $job->fresh()->status
    );
}

    public function test_admin_cannot_mark_designer_job_ready(): void
    {
        $admin = $this->admin();
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer, $admin);

        app(StartDesignJobService::class)
            ->start($job, $designer);

        app(CreateArtworkVersionService::class)->create(
            $job->fresh(),
            [
                'storage_path' => 'artworks/test/source.pdf',
                'preview_storage_path' =>
                    'artworks/test/preview.jpg',
                'original_filename' => 'source.pdf',
                'mime_type' => 'application/pdf',
            ],
            $designer
        );

        $this->actingAs($admin)
            ->post(
                route('staff.design-jobs.mark-ready', $job)
            )
            ->assertNotFound();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $job->fresh()->status
        );
    }

        public function test_operation_management_cannot_mark_designer_job_ready(): void
    {
        $admin = $this->admin();
        $operationManagement = $this->staff(User::ROLE_OM);
        $designer = $this->designer();
        $job = $this->designJob();

        $this->assign($job, $designer, $admin);

        app(StartDesignJobService::class)
            ->start($job, $designer);

        app(CreateArtworkVersionService::class)->create(
            $job->fresh(),
            [
                'storage_path' => 'artworks/test/source.pdf',
                'preview_storage_path' =>
                    'artworks/test/preview.jpg',
                'original_filename' => 'source.pdf',
                'mime_type' => 'application/pdf',
            ],
            $designer
        );

        $this->actingAs($operationManagement)
            ->post(
                route('staff.design-jobs.mark-ready', $job)
            )
            ->assertForbidden();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $job->fresh()->status
        );
    }

    public function test_two_package_design_sides_can_be_marked_ready_independently(): void
    {
        $lelakiDesigner = $this->designer();
        $perempuanDesigner = $this->designer();

        $order = $this->confirmedOrder(2);

        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        $jobs = app(
            InitializeDesignJobsForOrderService::class
        )->initialize($order->fresh());

        $lelaki = $jobs->firstWhere('side', 'LELAKI');
        $perempuan = $jobs->firstWhere('side', 'PEREMPUAN');

        $this->assertNotNull($lelaki);
        $this->assertNotNull($perempuan);

        $this->assign($lelaki, $lelakiDesigner);
        $this->assign($perempuan, $perempuanDesigner);

        foreach ([
            [$lelaki, $lelakiDesigner],
            [$perempuan, $perempuanDesigner],
        ] as [$job, $designer]) {
            app(StartDesignJobService::class)
                ->start($job, $designer);

            app(CreateArtworkVersionService::class)->create(
                $job->fresh(),
                [
                    'storage_path' =>
                        "artworks/{$job->side}/source.pdf",
                    'preview_storage_path' =>
                        "artworks/{$job->side}/preview.jpg",
                    'original_filename' =>
                        "{$job->side}.pdf",
                    'mime_type' => 'application/pdf',
                ],
                $designer
            );
        }

        $this->actingAs($lelakiDesigner)
            ->post(
                route('staff.design-jobs.mark-ready', $lelaki)
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'DESIGN_READY',
            $lelaki->fresh()->status
        );

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $perempuan->fresh()->status
        );

        $this->actingAs($lelakiDesigner)
            ->post(
                route(
                    'staff.design-jobs.mark-ready',
                    $perempuan
                )
            )
            ->assertNotFound();

        $this->assertSame(
            'DESIGN_IN_PROGRESS',
            $perempuan->fresh()->status
        );

        $this->actingAs($perempuanDesigner)
            ->post(
                route(
                    'staff.design-jobs.mark-ready',
                    $perempuan
                )
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'DESIGN_READY',
            $perempuan->fresh()->status
        );
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function designer(): User
    {
        return $this->staff(
            User::ROLE_DESIGNER
        );
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function assign(
        $job,
        User $designer,
        ?User $actor = null
    ) {
        return app(
            AssignDesignJobService::class
        )->assign(
            $job,
            $designer,
            $actor ?? $designer
        );
    }

    private function designJob()
    {
        $order = $this->confirmedOrder();

        app(GenerateMergeJobsForOrderService::class)
            ->generate($order);

        return app(
            InitializeDesignJobsForOrderService::class
        )
            ->initialize($order->fresh())
            ->first();
    }

    private function correctionRequestedJob(
        User $designer
    ) {
        $job = $this->designJob();

        $job = $this->assign(
            $job,
            $designer
        );

        $job = app(
            \App\Services\Design\StartDesignJobService::class
        )->start(
            $job,
            $designer
        );

        app(CreateArtworkVersionService::class)
            ->create(
                $job,
                [
                    'storage_path' =>
                        "artworks/{$job->side}/v1.psd",
                    'preview_storage_path' =>
                        "artworks/{$job->side}/v1-preview.jpg",
                    'original_filename' =>
                        "{$job->side}-v1.psd",
                    'mime_type' =>
                        'image/vnd.adobe.photoshop',
                ],
                $designer
            );

        $job = app(
            MarkDesignReadyService::class
        )->markReady(
            $job->fresh(),
            $designer
        );

        return app(
            RequestArtworkCorrectionService::class
        )->request(
            $job->fresh(),
            'Sila betulkan maklumat artwork.'
        );
    }

    private function confirmedOrder(
        int $packageCount = 1
    ) {
        $order = app(
            CreateOrderService::class
        )->create([
            'package_count' => $packageCount,
            'side' =>
                $packageCount === 1
                    ? 'LELAKI'
                    : null,
            'customer_name' =>
                'Staff Design Workflow Test',
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
            $sides['PEREMPUAN'] =
                $this->sidePayload(
                    'P101',
                    'Bapa Perempuan',
                    'Ibu Perempuan',
                    'Dewan Perempuan'
                );
        }

        app(SaveOrderDraftService::class)
            ->save(
                $order,
                [
                    'card_quantity' => 200,
                    'couple' => [
                        'groom_name' =>
                            'Muhammad Syafiq',
                        'bride_name' =>
                            'Nur Awanis',
                    ],
                    'sides' => $sides,
                    'fulfilment' => [
                        'method' => 'PICKUP',
                    ],
                ]
            );

        return app(
            ConfirmOrderDetailsService::class
        )->confirm(
            $order->fresh()
        );
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
                        'contact_name' =>
                            'Contact 1',
                        'contact_phone' =>
                            '0111111111',
                    ],
                    2 => [
                        'contact_name' =>
                            'Contact 2',
                        'contact_phone' =>
                            '0122222222',
                    ],
                    3 => [
                        'contact_name' =>
                            'Contact 3',
                        'contact_phone' =>
                            '0133333333',
                    ],
                ],
            ],
        ];
    }
}
