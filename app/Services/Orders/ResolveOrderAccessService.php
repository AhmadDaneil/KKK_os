<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderAccessToken;

class ResolveOrderAccessService
{
    public function resolve(string $orderId, string $plainToken): Order
    {
        abort_if($plainToken === '', 404);

        $order = Order::where('order_id', $orderId)->firstOrFail();

        $accessToken = OrderAccessToken::query()
            ->where('order_fk', $order->id)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        abort_if(! $accessToken, 404);
        abort_if($accessToken->expires_at && $accessToken->expires_at->isPast(), 403);

        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $order;
    }
}
