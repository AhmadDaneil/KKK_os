<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateOrderAccessLinkService
{
    public function generate(Order $order, ?int $expiresInDays = 30): string
    {
        $plainToken = Str::random(64);

        DB::transaction(function () use ($order, $plainToken, $expiresInDays) {
            $order->accessTokens()->create([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => $expiresInDays === null ? null : now()->addDays($expiresInDays),
            ]);
        });

        return route('orders.dashboard', [
            'orderId' => $order->order_id,
            'token' => $plainToken,
        ]);
    }
}
