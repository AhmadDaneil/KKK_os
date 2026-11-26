<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Orders\CustomerOrderEmailNotificationService;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        app(CustomerOrderEmailNotificationService::class)->queueStatusUpdate(
            $order,
            (string) $order->status,
        );
    }
}
