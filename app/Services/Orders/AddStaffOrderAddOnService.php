<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Payments\SynchronizeOrderFinancialsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddStaffOrderAddOnService
{
    public function __construct(private OrderAddOnCatalogService $catalog, private SynchronizeOrderFinancialsService $financials) {}

    public function blockingReason(Order $order): ?string
    {
        $order->loadMissing(['packingJob', 'payments']);
        if ($order->isAssignmentLocked() || in_array($order->status, ['PACKING', 'PACKED', 'READY_FOR_FULFILMENT', 'READY_FOR_PICKUP', 'SHIPPED'], true)
            || ($order->packingJob && $order->packingJob->status !== 'READY_FOR_PACKING')) {
            return 'Add-ons can only be changed before packing starts and while the order is active.';
        }
        if ($order->payments->contains(fn ($payment): bool => $payment->payment_type === 'BALANCE' && $payment->status === 'PENDING')) {
            return 'A balance payment is in progress. Complete or review that payment before changing Add-On items.';
        }

        return null;
    }

    public function add(Order $order, User $actor, string $code, int $quantity, ?string $notes, string $requestId): OrderItem
    {
        abort_unless($actor->isOperationManagement() || $actor->isCustomerService(), 403);
        $option = $this->catalog->find($code);
        if ($option === null || $quantity < $option['min_quantity'] || $quantity > 100000) {
            throw ValidationException::withMessages(['add_on' => 'Choose a valid add-on and its minimum quantity.']);
        }

        return DB::transaction(function () use ($order, $actor, $option, $quantity, $notes, $requestId): OrderItem {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $existing = $lockedOrder->items()->where('item_type', 'ADDON')->where('metadata->request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }
            if ($lockedOrder->statusEvents()->where('event_type', 'ADDON_REMOVED')->where('metadata->request_id', $requestId)->exists()) {
                throw ValidationException::withMessages(['add_on' => 'This Add-On submission was removed. Refresh the page before adding it again.']);
            }
            if ($reason = $this->blockingReason($lockedOrder)) {
                throw ValidationException::withMessages(['add_on' => $reason]);
            }
            $totalCents = $option['unit_price_cents'] * $quantity;
            $item = $lockedOrder->items()->create([
                'item_type' => 'ADDON', 'item_code' => $option['code'],
                'description' => Str::limit($option['description'].(filled($notes) ? ' - '.trim($notes) : ''), 160),
                'quantity' => $quantity,
                'unit_price' => $this->catalog->formatCents($option['unit_price_cents']),
                'line_total' => $this->catalog->formatCents($totalCents),
                'metadata' => [
                    'request_id' => $requestId, 'added_by_user_id' => $actor->id,
                    'notes' => filled($notes) ? trim($notes) : null,
                    'base_unit_price' => $this->catalog->formatCents($option['base_unit_price_cents']),
                    'welcome_board_postage_per_unit' => $this->catalog->formatCents($option['postage_cents']),
                    'minimum_quantity' => $option['min_quantity'],
                ],
            ]);
            $snapshot = $lockedOrder->pricingSnapshot()->lockForUpdate()->first();
            if ($snapshot) {
                $calculation = $snapshot->calculation_snapshot ?? [];
                $calculation['add_on_amount'] = $this->catalog->formatCents($this->cents((string) ($calculation['add_on_amount'] ?? '0.00')) + $totalCents);
                $calculation['add_ons'] = $lockedOrder->items()->where('item_type', 'ADDON')->get(['description', 'quantity', 'unit_price', 'line_total'])->toArray();
                $snapshot->update([
                    'subtotal' => $this->catalog->formatCents($this->cents((string) $snapshot->subtotal) + $totalCents),
                    'total_amount' => $this->catalog->formatCents($this->cents((string) $snapshot->total_amount) + $totalCents),
                    'outstanding_amount' => $this->catalog->formatCents($this->cents((string) $snapshot->outstanding_amount) + $totalCents),
                    'calculation_snapshot' => $calculation,
                ]);
                $this->financials->synchronize($lockedOrder);
            }
            $lockedOrder->statusEvents()->create([
                'event_type' => 'ADDON_ADDED', 'from_status' => $lockedOrder->status, 'to_status' => $lockedOrder->status,
                'actor_user_id' => $actor->id, 'source' => $actor->isAdmin() ? 'ADMIN' : 'STAFF', 'occurred_at' => now(),
                'metadata' => ['order_item_id' => $item->id, 'code' => $option['code'], 'quantity' => $quantity, 'amount' => $item->line_total],
            ]);

            return $item;
        }, 3);
    }

    public function remove(Order $order, OrderItem $item, User $actor): void
    {
        abort_unless($actor->isOperationManagement() || $actor->isCustomerService(), 403);

        DB::transaction(function () use ($order, $item, $actor): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedItem = $lockedOrder->items()->whereKey($item->id)->where('item_type', 'ADDON')->lockForUpdate()->firstOrFail();
            if ($reason = $this->blockingReason($lockedOrder)) {
                throw ValidationException::withMessages(['add_on' => $reason]);
            }
            $amountCents = $this->cents((string) $lockedItem->line_total);
            $snapshot = $lockedOrder->pricingSnapshot()->lockForUpdate()->first();
            if ($snapshot) {
                $newTotal = $this->cents((string) $snapshot->total_amount) - $amountCents;
                $newSubtotal = $this->cents((string) $snapshot->subtotal) - $amountCents;
                $paidCents = max(
                    $this->cents((string) $snapshot->paid_amount),
                    $lockedOrder->payments()->where('status', 'PAID')->where('currency', $snapshot->currency)->get(['amount'])
                        ->sum(fn ($payment): int => $this->cents((string) $payment->amount)),
                );
                if ($newTotal < $paidCents || $newSubtotal < 0) {
                    throw ValidationException::withMessages([
                        'add_on' => 'This removal requires a refund review because payments already received would exceed the new order total. Please contact Operations Management.',
                    ]);
                }
            }

            $auditItem = [
                'order_item_id' => $lockedItem->id, 'code' => $lockedItem->item_code,
                'description' => $lockedItem->description, 'quantity' => $lockedItem->quantity,
                'unit_price' => $lockedItem->unit_price, 'amount' => $lockedItem->line_total,
                'request_id' => data_get($lockedItem->metadata, 'request_id'),
                'item_metadata' => $lockedItem->metadata,
            ];
            $lockedItem->delete();

            if ($snapshot) {
                $remainingItems = $lockedOrder->items()->where('item_type', 'ADDON')->get(['description', 'quantity', 'unit_price', 'line_total']);
                $calculation = $snapshot->calculation_snapshot ?? [];
                $calculation['add_on_amount'] = $this->catalog->formatCents($remainingItems->sum(fn (OrderItem $remaining): int => $this->cents((string) $remaining->line_total)));
                $calculation['add_ons'] = $remainingItems->toArray();
                $snapshot->update([
                    'subtotal' => $this->catalog->formatCents($newSubtotal),
                    'total_amount' => $this->catalog->formatCents($newTotal),
                    'outstanding_amount' => $this->catalog->formatCents($newTotal - $paidCents),
                    'calculation_snapshot' => $calculation,
                ]);
                $this->financials->synchronize($lockedOrder);
            }
            $lockedOrder->statusEvents()->create([
                'event_type' => 'ADDON_REMOVED', 'from_status' => $lockedOrder->status, 'to_status' => $lockedOrder->status,
                'actor_user_id' => $actor->id, 'source' => $actor->isAdmin() ? 'ADMIN' : 'STAFF',
                'occurred_at' => now(), 'metadata' => $auditItem,
            ]);
        }, 3);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount);

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
