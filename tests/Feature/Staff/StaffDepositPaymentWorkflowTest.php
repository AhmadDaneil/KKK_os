<?php

namespace Tests\Feature\Staff;

use App\Models\User;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffDepositPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_reject_and_later_approve_resubmitted_deposit(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->depositOrder();
        $admin = User::factory()->create(['role' => User::ROLE_OM, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('staff.payments.receipt', $payment))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('staff.payments.deposit.reject', $payment), [
                'rejection_reason' => 'Jumlah pada resit tidak jelas.',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('FAILED', $payment->status);
        $this->assertSame('REJECTED', $order->fresh()->booking_payment_status);
        $this->assertSame('Jumlah pada resit tidak jelas.', $payment->metadata['rejection_reason']);
        $this->assertDatabaseHas('payment_events', ['payment_transaction_id' => $payment->id, 'event_type' => 'DEPOSIT_REJECTED']);

        $accessUrl = app(GenerateOrderAccessLinkService::class)->generate($order);
        $this->get($accessUrl)->assertRedirect(route('orders.dashboard', ['orderId' => $order->order_id]));
        $this->get(route('orders.dashboard', ['orderId' => $order->order_id]))
            ->assertOk()
            ->assertSee('Resit Ditolak')
            ->assertSee('Jumlah pada resit tidak jelas.')
            ->assertSee('Hantar Semula Resit');
        $this->post(route('public.orders.progress.lookup'), ['order_id' => $order->order_id])
            ->assertOk()
            ->assertSee('Resit Ditolak')
            ->assertSee('Jumlah pada resit tidak jelas.');
        $this->post(route('orders.deposit-receipt.update', ['orderId' => $order->order_id]), [
            'deposit_receipt' => UploadedFile::fake()->image('resit-baharu.jpg'),
        ])->assertRedirect();

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('RECEIPT_SUBMITTED', $order->fresh()->booking_payment_status);

        $this->actingAs($admin)
            ->post(route('staff.payments.deposit.approve', $payment), ['amount' => '125.50'])
            ->assertRedirect();

        $this->assertSame('PAID', $payment->fresh()->status);
        $this->assertSame('125.50', $payment->fresh()->amount);
        $this->assertSame('READY_FOR_DESIGN', $order->fresh()->status);
        $this->assertSame('PAID', $order->fresh()->booking_payment_status);
        $this->assertDatabaseCount('design_jobs', 1);
        $this->assertDatabaseHas('payment_events', ['payment_transaction_id' => $payment->id, 'event_type' => 'DEPOSIT_APPROVED']);
    }

    public function test_non_admin_staff_cannot_review_deposit(): void
    {
        [$order, $payment] = $this->depositOrder();
        $designer = User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);

        $this->actingAs($designer)
            ->post(route('staff.payments.deposit.approve', $payment), ['amount' => '125.50'])
            ->assertForbidden();

        $this->assertSame('PENDING', $payment->fresh()->status);
        $this->assertSame('DETAILS_CONFIRMED', $order->fresh()->status);
    }

    public function test_only_authorized_payment_staff_can_access_private_receipt(): void
    {
        Storage::fake('local');

        [, $payment] = $this->depositOrder();

        $operationManagement = User::factory()->create([
            'role' => User::ROLE_OM,
            'is_active' => true,
        ]);

        $designer = User::factory()->create([
            'role' => User::ROLE_DESIGNER,
            'is_active' => true,
        ]);

        $printing = User::factory()->create([
            'role' => User::ROLE_PRINTING,
            'is_active' => true,
        ]);

        $packing = User::factory()->create([
            'role' => User::ROLE_PACKING,
            'is_active' => true,
        ]);

        // Guest must not be able to retrieve a private receipt.
        $this->get(route('staff.payments.receipt', $payment))
            ->assertRedirect();

        // Operational roles outside payment review must be denied.
        $this->actingAs($designer)
            ->get(route('staff.payments.receipt', $payment))
            ->assertForbidden();

        $this->actingAs($printing)
            ->get(route('staff.payments.receipt', $payment))
            ->assertForbidden();

        $this->actingAs($packing)
            ->get(route('staff.payments.receipt', $payment))
            ->assertForbidden();

        // Operation Management is authorized to retrieve the receipt.
        $this->actingAs($operationManagement)
            ->get(route('staff.payments.receipt', $payment))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_invalid_amount_does_not_approve_deposit(): void
    {
        Storage::fake('local');
        [$order, $payment] = $this->depositOrder();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        foreach ([null, '0', '-5', '12.345', 'abc', '10000000000'] as $amount) {
            $this->actingAs($admin, 'admin')
                ->post(route('admin.payments.deposit.approve', $payment), ['amount' => $amount])
                ->assertSessionHasErrors('amount');
            $this->assertSame('PENDING', $payment->fresh()->status);
            $this->assertSame('100.00', $payment->fresh()->amount);
            $this->assertSame('DETAILS_CONFIRMED', $order->fresh()->status);
        }
    }

    private function depositOrder(): array
    {
        Storage::disk('local')->put('deposit-receipts/test/resit.jpg', 'receipt');
        $order = app(CreateOrderService::class)->create(['package_count' => 1, 'side' => 'LELAKI', 'customer_name' => 'Deposit Test']);
        $order->confirmation()->create(['confirmed_at' => now(), 'confirmation_version' => 'v1']);
        $order->update(['status' => 'DETAILS_CONFIRMED', 'details_confirmed_at' => now(), 'booking_payment_status' => 'RECEIPT_SUBMITTED']);
        $payment = $order->payments()->create([
            'payment_type' => 'BOOKING_DEPOSIT', 'provider' => 'MANUAL_QR', 'amount' => '100.00',
            'currency' => 'MYR', 'status' => 'PENDING',
            'metadata' => ['receipt_path' => 'deposit-receipts/test/resit.jpg', 'receipt_original_name' => 'resit.jpg', 'receipt_mime_type' => 'image/jpeg'],
        ]);

        return [$order, $payment];
    }
}
