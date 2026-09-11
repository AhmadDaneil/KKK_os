<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CustomerOrderSessionAccessService
{
    private const SESSION_PREFIX = 'kkk.customer_orders.';

    public function establishFromToken(
        Request $request,
        string $orderId,
        string $plainToken,
        ResolveOrderAccessService $tokenAccess,
    ): Order {
        $order = $tokenAccess->resolve($orderId, $plainToken);

        $accessToken = OrderAccessToken::query()
            ->where('order_fk', $order->id)
            ->where('token_hash', hash('sha256', $plainToken))
            ->firstOrFail();

        $request->session()->regenerate();

        $request->session()->put($this->sessionKey($orderId), [
            'order_fk' => $order->id,
            'expires_at' => $accessToken->expires_at?->toIso8601String(),
        ]);

        return $order;
    }

    public function resolve(Request $request, string $orderId): Order
    {
        $order = Order::where('order_id', $orderId)->firstOrFail();
        $grant = $request->session()->get($this->sessionKey($orderId));

        abort_if(! is_array($grant), 404);
        abort_if((int) ($grant['order_fk'] ?? 0) !== (int) $order->id, 404);

        $expiresAt = $grant['expires_at'] ?? null;

        if ($expiresAt !== null && CarbonImmutable::parse($expiresAt)->isPast()) {
            $request->session()->forget($this->sessionKey($orderId));
            abort(403);
        }

        return $order;
    }

    private function sessionKey(string $orderId): string
    {
        return self::SESSION_PREFIX.$orderId;
    }
}
