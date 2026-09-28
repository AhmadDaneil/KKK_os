<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CustomerOrderSessionAccessService
{
    private const SESSION_PREFIX = 'kkk.customer_orders.';
    private const ACTIVE_DRAFT_SESSION_KEY = 'kkk.customer_active_draft';

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
        $this->rememberActiveDraft($request, $order, $accessToken->expires_at?->toIso8601String());

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

    public function hasAccess(Request $request, Order $order): bool
    {
        $grant = $request->session()->get($this->sessionKey($order->order_id));

        if (! is_array($grant) || (int) ($grant['order_fk'] ?? 0) !== (int) $order->id) {
            return false;
        }

        $expiresAt = $grant['expires_at'] ?? null;

        if ($expiresAt !== null && CarbonImmutable::parse($expiresAt)->isPast()) {
            $request->session()->forget($this->sessionKey($order->order_id));

            return false;
        }

        return true;
    }

    public function establishFromProgressLookup(Request $request, Order $order): void
    {
        $request->session()->put($this->sessionKey($order->order_id), [
            'order_fk' => $order->id,
            'expires_at' => now()->addHours(2)->toIso8601String(),
        ]);

        $this->rememberActiveDraft($request, $order, now()->addHours(2)->toIso8601String());
    }

    public function unfinishedDraft(Request $request): ?Order
    {
        $draft = $request->session()->get(self::ACTIVE_DRAFT_SESSION_KEY);

        if (! is_array($draft)) {
            return null;
        }

        $expiresAt = $draft['expires_at'] ?? null;

        if ($expiresAt !== null && CarbonImmutable::parse($expiresAt)->isPast()) {
            $request->session()->forget(self::ACTIVE_DRAFT_SESSION_KEY);

            return null;
        }

        $order = Order::query()
            ->whereKey($draft['order_fk'] ?? 0)
            ->where('order_id', $draft['order_id'] ?? '')
            ->where('status', 'DETAILS_INCOMPLETE')
            ->first();

        if (! $order) {
            $request->session()->forget(self::ACTIVE_DRAFT_SESSION_KEY);
        }

        return $order;
    }

    private function sessionKey(string $orderId): string
    {
        return self::SESSION_PREFIX.$orderId;
    }

    private function rememberActiveDraft(Request $request, Order $order, ?string $expiresAt): void
    {
        if ($order->status !== 'DETAILS_INCOMPLETE') {
            return;
        }

        $request->session()->put(self::ACTIVE_DRAFT_SESSION_KEY, [
            'order_fk' => $order->id,
            'order_id' => $order->order_id,
            'expires_at' => $expiresAt,
        ]);
    }
}
