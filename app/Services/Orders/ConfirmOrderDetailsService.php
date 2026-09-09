<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOrderDetailsService
{
    public function __construct(
        private ValidateOrderCompletionService $completionValidator,
    ) {}

    public function confirm(Order $order): Order
    {
        $result = $this->completionValidator->validate($order);

        if (! $result['complete']) {
            throw ValidationException::withMessages([
                'order' => $result['missing'],
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->confirmation()->updateOrCreate(
                [],
                [
                    'confirmed_at' => now(),
                    'confirmation_version' => 'v1',
                ]
            );

            $order->update([
                'status' => 'DETAILS_CONFIRMED',
                'details_confirmed_at' => now(),
            ]);

            return $order->fresh([
                'couples',
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'fulfilment',
                'confirmation',
            ]);
        });
    }
}
