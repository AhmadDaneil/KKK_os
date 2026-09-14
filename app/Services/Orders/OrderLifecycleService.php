<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderLifecycleService
{
    public function cancel(
        Order $order,
        ?string $reason = null,
        ?User $actor = null,
        string $source = 'SYSTEM',
    ): Order {
        return $this->moveToTerminalStatus($order, 'CANCELLED', 'ORDER_CANCELLED', $reason, $actor, $source);
    }

    public function archive(
        Order $order,
        ?string $reason = null,
        ?User $actor = null,
        string $source = 'SYSTEM',
    ): Order {
        return $this->moveToTerminalStatus($order, 'ARCHIVED', 'ORDER_ARCHIVED', $reason, $actor, $source);
    }

    private function moveToTerminalStatus(
        Order $order,
        string $targetStatus,
        string $eventType,
        ?string $reason,
        ?User $actor,
        string $source,
    ): Order {
        return DB::transaction(function () use ($order, $targetStatus, $eventType, $reason, $actor, $source) {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === $targetStatus) {
                return $lockedOrder->fresh(['statusEvents']);
            }

            if ($lockedOrder->isTerminalOperationalStatus()) {
                throw new RuntimeException(
                    "Order {$lockedOrder->order_id} is already {$lockedOrder->status} and cannot transition to {$targetStatus}."
                );
            }

            $fromStatus = $lockedOrder->status;

            $lockedOrder->update([
                'status' => $targetStatus,
            ]);

            $lockedOrder->statusEvents()->create([
                'event_type' => $eventType,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'reason' => $this->cleanNullableText($reason),
                'actor_user_id' => $actor?->id,
                'source' => $this->cleanSource($source),
                'occurred_at' => now(),
                'metadata' => [
                    'package_count' => (int) $lockedOrder->package_count,
                ],
            ]);

            return $lockedOrder->fresh(['statusEvents']);
        });
    }

    private function cleanNullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    private function cleanSource(string $source): string
    {
        $source = strtoupper(trim($source));

        return $source === '' ? 'SYSTEM' : substr($source, 0, 32);
    }
}
