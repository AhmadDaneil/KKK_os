<?php

namespace Tests\Feature\Security;

use App\Models\ArtworkVersion;
use App\Models\DesignJob;
use App\Models\MergeJob;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerCrossOrderMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_a_session_cannot_mutate_order_b_draft(): void
    {
        [$orderA, $orderB] = $this->orders();

        $this->establishSession($orderA);

        $before = $orderB->fresh()->toArray();

        $this->post(route('orders.draft.update', [
            'orderId' => $orderB->order_id,
        ]), [
            'couple' => [
                'groom_name' => 'Unauthorized Change',
            ],
        ])->assertNotFound();

        $this->assertSame($before, $orderB->fresh()->toArray());
    }

    public function test_order_a_session_cannot_confirm_order_b(): void
    {
        Storage::fake('local');

        [$orderA, $orderB] = $this->orders();

        $this->establishSession($orderA);

        $this->post(route('orders.confirm.store', [
            'orderId' => $orderB->order_id,
        ]), [
            'responsibility_acknowledged' => '1',
            'post_confirmation_liability_acknowledged' => '1',
            'deposit_receipt' => UploadedFile::fake()->image('unauthorized.jpg'),
        ])->assertNotFound();

        $this->assertNull($orderB->fresh()->details_confirmed_at);
        $this->assertDatabaseMissing('order_confirmations', [
            'order_id' => $orderB->id,
        ]);
    }

    public function test_order_a_session_cannot_upload_deposit_receipt_for_order_b(): void
    {
        Storage::fake('local');

        [$orderA, $orderB] = $this->orders();

        $this->establishSession($orderA);

        $this->post(route('orders.deposit-receipt.update', [
            'orderId' => $orderB->order_id,
        ]), [
            'deposit_receipt' => UploadedFile::fake()->image('unauthorized-deposit.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseMissing('payment_transactions', [
            'order_id' => $orderB->id,
        ]);
    }

    public function test_order_a_session_cannot_upload_balance_receipt_for_order_b(): void
    {
        Storage::fake('local');

        [$orderA, $orderB] = $this->orders();

        $orderB->update(['status' => 'DESIGN_APPROVED']);

        $this->establishSession($orderA);

        $this->post(route('orders.balance-receipt.store', [
            'orderId' => $orderB->order_id,
        ]), [
            'balance_receipt' => UploadedFile::fake()->image('unauthorized-balance.jpg'),
        ])->assertNotFound();

        $this->assertDatabaseMissing('payment_transactions', [
            'order_id' => $orderB->id,
            'payment_type' => 'BALANCE',
        ]);

        $this->assertSame('DESIGN_APPROVED', $orderB->fresh()->status);
    }

    public function test_order_a_session_cannot_request_correction_for_order_b_design_job(): void
    {
        [$orderA, $orderB] = $this->orders();

        $jobB = $this->readyDesignJob($orderB);

        $this->establishSession($orderA);

        $this->post(route('orders.artwork.correction', [
            'orderId' => $orderB->order_id,
            'designJobId' => $jobB->id,
        ]), [
            'correction_notes' => 'Unauthorized correction',
        ])->assertNotFound();

        $this->assertSame('DESIGN_READY', $jobB->fresh()->status);
    }

    public function test_order_a_session_cannot_approve_order_b_design_job(): void
    {
        [$orderA, $orderB] = $this->orders();

        $jobB = $this->readyDesignJob($orderB);

        $this->establishSession($orderA);

        $this->post(route('orders.artwork.approve', [
            'orderId' => $orderB->order_id,
            'designJobId' => $jobB->id,
        ]))->assertNotFound();

        $this->assertSame('DESIGN_READY', $jobB->fresh()->status);
    }

    private function orders(): array
    {
        $orderA = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
            'customer_name' => 'Customer A',
        ]);

        $orderB = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'PEREMPUAN',
            'customer_name' => 'Customer B',
        ]);

        return [$orderA, $orderB];
    }

    private function establishSession($order): void
    {
        $link = app(GenerateOrderAccessLinkService::class)->generate($order);

        $this->get($this->requestUri($link))
            ->assertRedirect(route('orders.dashboard', [
                'orderId' => $order->order_id,
            ]));
    }

    private function readyDesignJob($order): DesignJob
    {
        $side = $order->packageSides()->firstOrFail();

        $merge = MergeJob::create([
            'job_id' => 'SECURITY-'.$order->order_id,
            'order_id' => $order->id,
            'order_package_side_id' => $side->id,
            'side' => $side->side,
            'status' => 'PENDING_EXPORT',
            'payload_schema_version' => 'test',
            'canonical_payload' => [],
            'generated_at' => now(),
        ]);

        $job = DesignJob::create([
            'merge_job_id' => $merge->id,
            'order_id' => $order->id,
            'order_package_side_id' => $side->id,
            'side' => $side->side,
            'status' => 'DESIGN_READY',
        ]);

        ArtworkVersion::create([
            'design_job_id' => $job->id,
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'artworks/security/source.pdf',
            'preview_storage_path' => 'artworks/security/preview.jpg',
        ]);

        return $job;
    }

    private function requestUri(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $query === '' ? $path : $path.'?'.$query;
    }
}