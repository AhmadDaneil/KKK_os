<?php

namespace Tests\Feature;

use App\Models\ArtworkVersion;
use App\Models\DesignJob;
use App\Models\MergeJob;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionWithoutProgressUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_can_complete_printing_without_uploading_progress(): void
    {
        [$job, $staff] = $this->printingJob();
        $this->actingAs($staff, 'staff')->get(route('staff.orders.show', $job->order->order_id))
            ->assertSee('Mark Printing Complete')
            ->assertDontSee('Upload production progress')
            ->assertDontSee('name="progress_files[]"', false);

        $this->post('/staff/print-jobs/'.$job->id.'/progress-files')->assertNotFound();
        $this->postJson('/staff/print-jobs/'.$job->id.'/progress-files')->assertNotFound();
        $this->assertSame('PRINTING', $job->fresh()->status);
        $this->assertEmpty($job->fresh()->progress_files);
        $this->assertDatabaseMissing('print_job_events', ['print_job_id' => $job->id, 'event_type' => 'PRINT_PROGRESS_UPLOADED']);

        $this->post(route('staff.print-jobs.mark-printed', $job))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('PRINTED', $job->fresh()->status);
        $this->assertDatabaseHas('packing_jobs', ['order_id' => $job->order_id, 'status' => 'READY_FOR_PACKING']);
    }

    public function test_existing_progress_files_remain_available(): void
    {
        Storage::fake('local');
        [$job, $staff] = $this->printingJob();
        Storage::disk('local')->put('printing-progress/existing.pdf', 'existing proof');
        $job->update(['progress_files' => [['path' => 'printing-progress/existing.pdf', 'original_name' => 'existing.pdf', 'mime_type' => 'application/pdf']]]);

        $this->actingAs($staff, 'staff')->get(route('staff.print-jobs.progress-files.show', ['printJob' => $job, 'file' => 0]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Storage::disk('local')->assertExists('printing-progress/existing.pdf');
    }

    /** @return array{PrintJob, User} */
    private function printingJob(): array
    {
        $staff = User::factory()->create(['role' => User::ROLE_PRODUCTION, 'is_active' => true]);
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'package_type' => 'PUTERI_MANIS', 'customer_name' => 'Production Customer']);
        $side = $order->packageSides->first();
        $order->update(['status' => 'PRINTING', 'card_quantity' => 100]);
        $side->update(['card_quantity' => 100]);
        $merge = MergeJob::create(['job_id' => 'PRINT-NO-UPLOAD', 'order_id' => $order->id, 'order_package_side_id' => $side->id, 'side' => 'LELAKI', 'canonical_payload' => [], 'generated_at' => now()]);
        $design = DesignJob::create(['merge_job_id' => $merge->id, 'order_id' => $order->id, 'order_package_side_id' => $side->id, 'side' => 'LELAKI', 'status' => 'DESIGN_APPROVED']);
        $artwork = ArtworkVersion::create(['design_job_id' => $design->id, 'version_number' => 1, 'storage_disk' => 'local', 'storage_path' => 'artwork.pdf', 'original_filename' => 'artwork.pdf', 'mime_type' => 'application/pdf']);
        $job = PrintJob::create(['order_id' => $order->id, 'order_package_side_id' => $side->id, 'design_job_id' => $design->id, 'artwork_version_id' => $artwork->id, 'side' => 'LELAKI', 'status' => 'PRINTING', 'assigned_user_id' => $staff->id, 'quantity' => 100]);

        return [$job, $staff];
    }
}
