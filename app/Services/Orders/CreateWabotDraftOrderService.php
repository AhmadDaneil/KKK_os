<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateWabotDraftOrderService
{
    public function __construct(
        private CreateOrderService $orders,
        private CreateBillplzBookingPaymentService $bookingPayments,
        private NormalizeCustomerPhoneService $phones,
    ) {}

    /** @return array{order: Order, payment_url: string, reused: bool} */
    public function create(array $data): array
    {
        $sourceReference = trim((string) $data['external_reference']);
        $idempotencyKey = $data['wabot_idempotency_key'] ?? null;
        $channel = $data['wabot_channel'] ?? 'kingkadkahwin';
        $phone = $this->phones->normalize((string) $data['customer_phone']);
        $existing = $this->existing($sourceReference, $idempotencyKey);

        if ($existing !== null) {
            $this->ensureSameBooking($existing, $data, $phone, $sourceReference, $idempotencyKey, $channel);
            $existingPayment = $existing->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->where('status', 'PAID')
                ->latest('id')
                ->first();
            if ($existingPayment?->status === 'PAID') {
                return [
                    'order' => $existing,
                    'payment_url' => '',
                    'reused' => true,
                ];
            }

            $pendingPayment = $existing->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->where('status', 'PENDING')
                ->latest('id')
                ->first();
            if ($pendingPayment !== null && blank($pendingPayment->metadata['billplz']['checkout_url'] ?? null)) {
                throw new WabotBookingConflictException('The booking bill is still being prepared. Retry shortly.');
            }

            $checkout = $this->bookingPayments->create($existing, throwGatewayFailure: true);

            return ['order' => $existing, 'payment_url' => $checkout['payment_url'], 'reused' => true];
        }

        try {
            $order = DB::transaction(fn (): Order => $this->orders->create([
                ...$data,
                'source' => 'WABOT',
                'source_reference' => $sourceReference,
                'wabot_channel' => $channel,
                'wabot_idempotency_key' => $idempotencyKey,
                'customer_phone' => $phone,
            ]), 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existing($sourceReference, $idempotencyKey);

            if ($existing === null) {
                throw $exception;
            }

            return $this->create($data);
        }
        $checkout = $this->bookingPayments->create($order, throwGatewayFailure: true);

        return ['order' => $order, 'payment_url' => $checkout['payment_url'], 'reused' => false];
    }

    private function existing(string $sourceReference, ?string $idempotencyKey): ?Order
    {
        if ($idempotencyKey !== null) {
            $byKey = Order::query()
                ->where('source', 'WABOT')
                ->where('wabot_idempotency_key', $idempotencyKey)
                ->first();

            if ($byKey !== null) {
                return $byKey;
            }
        }

        return Order::query()
            ->where('source', 'WABOT')
            ->where('source_reference', $sourceReference)
            ->first();
    }

    private function ensureSameBooking(
        Order $order,
        array $data,
        string $phone,
        string $sourceReference,
        ?string $idempotencyKey,
        string $channel,
    ): void {
        $side = (int) $order->package_count === 1
            ? $order->packageSides()->value('side')
            : null;

        if ($order->source_reference !== $sourceReference
            || $order->wabot_idempotency_key !== $idempotencyKey
            || ($order->wabot_channel ?? 'kingkadkahwin') !== $channel
            || (int) $order->package_count !== (int) $data['package_count']
            || $order->package_type !== ($data['package_type'] ?? null)
            || $order->wabot_package_code !== ($data['wabot_package_code'] ?? null)
            || (string) $order->customer_phone !== $phone
            || trim((string) $order->customer_name) !== trim((string) $data['customer_name'])
            || (string) $order->customer_email !== strtolower(trim((string) ($data['customer_email'] ?? '')))
            || $side !== ($data['side'] ?? null)
            || (isset($data['package_format']) && $order->package_format !== $data['package_format'])
            || (isset($data['first_event_side']) && $order->first_event_side !== $data['first_event_side'])) {
            throw new WabotBookingConflictException('The Idempotency-Key or conversation reference belongs to a different booking.');
        }
    }
}
