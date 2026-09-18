<?php

namespace Tests\Feature\Payments;

use App\Models\ArtworkVersion;
use App\Models\DesignJob;
use App\Models\MergeJob;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BalanceReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receipt_can_be_approved_and_creates_print_job(): void
    {
        Storage::fake('local');
        $order = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer Bayaran',
            'customer_email' => 'bayaran@example.com',
            'customer_phone' => '0123456789',
        ]);
        $side = $order->packageSides()->firstOrFail();
        $merge = MergeJob::create([
            'job_id' => 'MERGE-BALANCE-1',
            'order_id' => $order->id,
            'order_package_side_id' => $side->id,
            'side' => 'LELAKI',
            'status' => 'PENDING_EXPORT',
            'payload_schema_version' => 'test',
            'canonical_payload' => [],
            'generated_at' => now(),
        ]);
        $job = DesignJob::create([
            'merge_job_id' => $merge->id,
            'order_id' => $order->id,
            'order_package_side_id' => $side->id,
            'side' => 'LELAKI',
            'status' => 'DESIGN_APPROVED',
        ]);
        ArtworkVersion::create([
            'design_job_id' => $job->id,
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'artworks/source.pdf',
            'preview_storage_path' => 'artworks/preview.jpg',
        ]);
        $order->update(['status' => 'DESIGN_APPROVED']);

        $this->post(route('public.orders.progress.lookup'), ['order_id' => $order->order_id])->assertOk();
        $this->post(route('orders.balance-receipt.store', $order->order_id), [
            'balance_receipt' => UploadedFile::fake()->image('resit.jpg'),
        ])->assertRedirect(route('public.orders.progress', ['order_id' => $order->order_id]));

        $payment = PaymentTransaction::where('order_id', $order->id)->where('payment_type', 'BALANCE')->firstOrFail();
        $this->assertSame('PENDING', $payment->status);
        $this->assertSame('BALANCE_PENDING', $order->fresh()->status);

        $om = User::factory()->create(['role' => User::ROLE_OM, 'is_active' => true]);
        $this->actingAs($om)->post(route('staff.payments.balance.approve', $payment))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('READY_FOR_PRINT', $order->fresh()->status);
        $this->assertCount(1, $order->printJobs()->get());
    }
}
