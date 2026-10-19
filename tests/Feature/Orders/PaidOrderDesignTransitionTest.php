<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Services\Orders\CreateOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaidOrderDesignTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_command_advances_only_the_requested_eligible_order(): void
    {
        User::factory()->create(['role' => User::ROLE_DESIGNER, 'is_active' => true]);
        $eligible = app(CreateOrderService::class)->create([
            'package_count' => 2,
            'package_format' => 'SEPARATE',
            'first_event_side' => 'LELAKI',
        ]);
        $eligible->confirmation()->create([
            'confirmed_at' => now(),
            'confirmation_version' => 'v1',
        ]);
        $eligible->update([
            'status' => 'DETAILS_CONFIRMED',
            'details_confirmed_at' => now(),
            'booking_payment_status' => 'PAID',
        ]);

        $unpaid = app(CreateOrderService::class)->create([
            'package_count' => 1,
            'side' => 'LELAKI',
        ]);
        $unpaid->update(['status' => 'DETAILS_CONFIRMED']);

        $this->artisan('orders:advance-paid-confirmed', ['--order' => $eligible->order_id])
            ->assertSuccessful();

        $this->assertSame('READY_FOR_DESIGN', $eligible->fresh()->status);
        $this->assertSame(2, $eligible->mergeJobs()->count());
        $this->assertSame(2, $eligible->designJobs()->count());
        $this->assertSame('DETAILS_CONFIRMED', $unpaid->fresh()->status);
        $this->assertDatabaseHas('order_status_events', [
            'order_id' => $eligible->id,
            'event_type' => 'PAID_ORDER_RECONCILED',
        ]);
    }
}
