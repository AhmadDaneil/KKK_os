<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Orders\AdvancePaidConfirmedOrderToDesignService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('orders:advance-paid-confirmed {--order= : Advance only this Order ID}')]
#[Description('Advance paid, details-confirmed orders to the design queue')]
class AdvancePaidConfirmedOrders extends Command
{
    public function handle(AdvancePaidConfirmedOrderToDesignService $readiness): int
    {
        $query = Order::query()
            ->where('status', 'DETAILS_CONFIRMED')
            ->where('booking_payment_status', 'PAID')
            ->when($this->option('order'), fn ($query, $orderId) => $query->where('order_id', strtoupper(trim((string) $orderId))))
            ->orderBy('id');

        $orders = $query->get();
        $failures = 0;

        foreach ($orders as $order) {
            try {
                $advanced = $readiness->advance(
                    $order,
                    eventType: 'PAID_ORDER_RECONCILED',
                    metadata: ['command' => 'orders:advance-paid-confirmed'],
                );
                $this->info("{$advanced->order_id}: {$advanced->status}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$order->order_id}: {$exception->getMessage()}");
                $failures++;
            }
        }

        if ($orders->isEmpty()) {
            $this->info('No paid, details-confirmed orders require advancement.');
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
