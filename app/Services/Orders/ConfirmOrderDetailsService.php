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

    public function confirm(Order $order, ?array $livePreview = null): Order
    {
        if ($order->status !== 'DETAILS_INCOMPLETE') {
            throw ValidationException::withMessages([
                'order' => "This order's details cannot be confirmed again.",
            ]);
        }

        $result = $this->completionValidator->validate($order);

        if (! $result['complete']) {
            throw ValidationException::withMessages([
                'order' => $result['missing'],
            ]);
        }

        return DB::transaction(function () use ($order, $livePreview) {
            $snapshot = $livePreview === null ? null : [
                'snapshot_type' => 'LIVE_PREVIEW_REFERENCE',
                'schema_version' => 'v1',
                'is_production_artwork' => false,
                'live_preview' => $livePreview,
            ];

            $order->confirmation()->updateOrCreate(
                [],
                [
                    'confirmed_at' => now(),
                    'confirmation_version' => 'v1',
                    'confirmed_snapshot' => $snapshot,
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
