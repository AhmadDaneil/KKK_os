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
            return 'Add-ons can only be added before packing starts and while the order is active.';
        }
        if ($order->payments->contains(fn ($payment): bool => $payment->payment_type === 'BALANCE' && $payment->status === 'PENDING')) {
            return 'A balance payment is in progress. Complete or review that payment before adding more items.';
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

    private function cents(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount);

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
