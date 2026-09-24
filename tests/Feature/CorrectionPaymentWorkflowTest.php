<?php

namespace Tests\Feature;

use App\Models\ArtworkVersion;
use App\Models\DesignJob;
use App\Models\MergeJob;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CorrectionPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_waits_for_manager_approval_before_releasing_correction(): void
    {
        [$order, $job] = $this->readyJob();
        $this->get(route('orders.artwork.review', $order->order_id))->assertOk()->assertSee('caj RM10')->assertSee('correction_receipt');
        $this->submit($order, $job)->assertRedirect()->assertSessionHasNoErrors();
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('10.00', $payment->amount);
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame('DESIGN_READY', $job->fresh()->status);
        $this->assertSame(0, $job->reviewActions()->count());
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $job->update(['assigned_user_id' => $designer->id]);
        $this->actingAs($designer, 'staff')->post(route('staff.design-jobs.resume-correction', $job))
            ->assertSessionHasErrors('design_job');
        $this->assertSame('DESIGN_READY', $job->fresh()->status);
        Storage::disk('local')->assertExists($payment->metadata['receipt_path']);
        $this->get(route('orders.artwork.review', $order->order_id))->assertSee('Menunggu pengesahan bayaran RM10')->assertDontSee('Luluskan Artwork');
        $this->submit($order, $job)->assertSessionHasErrors('correction_receipt');
        $this->assertSame(1, $order->payments()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->post(route('orders.artwork.approve', [$order->order_id, $job->id]))->assertSessionHasErrors('artwork');

        $manager = User::factory()->create(['role' => User::ROLE_OM, 'is_active' => true]);
        $this->actingAs($manager, 'staff')->get(route('staff.orders.show', $order->order_id))->assertOk()->assertSee('Sahkan Bayaran Pembetulan RM10');
        $this->get(route('staff.payments.receipt', $payment))->assertOk();
        $this->post(route('staff.payments.correction.approve', $payment))->assertRedirect();
        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('CORRECTION_REQUESTED', $job->fresh()->status);
        $this->assertSame('CORRECTION_REQUESTED', $order->fresh()->status);
        $this->assertSame('Betulkan nama', $job->reviewActions()->firstOrFail()->customer_comment);
        $this->post(route('staff.payments.correction.approve', $payment))->assertUnprocessable();
        $this->assertSame(1, $job->reviewActions()->count());

        $job->refresh()->update(['status' => 'DESIGN_READY']);
        $artwork = $job->artworkVersions()->firstOrFail();
        $next = $artwork->replicate();
        $next->version_number = 2;
        $next->save();
        $this->submit($order, $job)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame('10.00', $order->payments()->latest('id')->firstOrFail()->amount);
        $this->assertSame('PENDING', $order->payments()->latest('id')->firstOrFail()->status);
    }

    public function test_rejected_receipt_can_be_resubmitted_and_staff_cannot_approve(): void
    {
        [$order, $job] = $this->readyJob();
        $this->submit($order, $job)->assertSessionHasNoErrors();
        $payment = $order->payments()->firstOrFail();
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $this->actingAs($designer, 'staff')->post(route('staff.payments.correction.approve', $payment))->assertForbidden();
        $this->get(route('staff.payments.receipt', $payment))->assertForbidden();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'admin')->post(route('admin.payments.correction.reject', $payment), ['rejection_reason' => 'Resit tidak jelas'])->assertRedirect();
        $this->assertSame('FAILED', $payment->fresh()->status);
        $this->assertSame('DESIGN_READY', $job->fresh()->status);
        $this->get(route('orders.artwork.review', $order->order_id))->assertSee('Resit tidak jelas');
        $this->submit($order, $job)->assertSessionHasNoErrors();
        $new = $order->payments()->latest('id')->firstOrFail();
        $this->actingAs($admin, 'admin')->post(route('admin.payments.correction.approve', $new))->assertRedirect();
        $this->assertSame('PAID', $new->fresh()->status);
    }

    public function test_consent_and_valid_receipt_are_required(): void
    {
        [$order, $job] = $this->readyJob();
        $url = route('orders.artwork.correction', [$order->order_id, $job->id]);
        $this->post($url, ['correction_comment' => 'Betulkan nama'])->assertSessionHasErrors(['correction_fee_agreed', 'correction_receipt']);
        $this->post($url, ['correction_comment' => 'Betulkan nama', 'correction_fee_agreed' => 1, 'correction_receipt' => UploadedFile::fake()->create('bad.exe', 1)])->assertSessionHasErrors('correction_receipt');
        $this->post($url, ['correction_comment' => 'Betulkan nama', 'correction_fee_agreed' => 1, 'correction_receipt' => UploadedFile::fake()->image('large.jpg')->size(10241)])->assertSessionHasErrors('correction_receipt');
        $this->assertSame(0, $order->payments()->count());
        $this->assertSame('DESIGN_READY', $job->fresh()->status);
    }

    public function test_other_order_job_and_terminal_order_cannot_submit_receipt(): void
    {
        [$order, $job] = $this->readyJob();
        [$other, $otherJob] = $this->readyJob();
        $this->submit($order, $otherJob)->assertNotFound();
        $order->update(['status' => 'CANCELLED']);
        $this->submit($order, $job)->assertUnprocessable();
        $this->assertSame(0, $order->payments()->count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    private function submit(Order $order, DesignJob $job): TestResponse
    {
        return $this->post(route('orders.artwork.correction', [$order->order_id, $job->id]), [
            'correction_comment' => 'Betulkan nama', 'correction_fee_agreed' => 1,
            'correction_receipt' => UploadedFile::fake()->image('receipt.jpg'), 'amount' => 0,
        ]);
    }

    private function readyJob(): array
    {
        Storage::fake('local');
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'customer_name' => 'Correction Customer']);
        $order->update(['status' => 'DESIGN_READY']);
        $side = $order->packageSides()->firstOrFail();
        $merge = MergeJob::create(['job_id' => 'CORRECTION-'.$order->order_id, 'order_id' => $order->id, 'order_package_side_id' => $side->id, 'side' => $side->side, 'status' => 'PENDING_EXPORT', 'payload_schema_version' => 'test', 'canonical_payload' => [], 'generated_at' => now()]);
        $job = DesignJob::create(['merge_job_id' => $merge->id, 'order_id' => $order->id, 'order_package_side_id' => $side->id, 'side' => $side->side, 'status' => 'DESIGN_READY']);
        ArtworkVersion::create(['design_job_id' => $job->id, 'version_number' => 1, 'storage_disk' => 'local', 'storage_path' => 'artworks/source.pdf', 'preview_storage_path' => 'artworks/preview.jpg']);
        $this->withSession(['kkk.customer_orders.'.$order->order_id => ['order_fk' => $order->id, 'expires_at' => null]]);

        return [$order, $job];
    }
}
