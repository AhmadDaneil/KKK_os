<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Payments\BillplzPaymentMethod;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWabotDraftOrderService
{
    public function __construct(
        private CreateOrderService $orders,
        private CreateBillplzBookingPaymentService $bookingPayments,
        private NormalizeCustomerPhoneService $phones,
    ) {}

    /** @return array{order: Order, payment_url: string, reused: bool} */
    public function createForConversation(array $data, string $paymentMethod = BillplzPaymentMethod::BILLPLZ): array
    {
        $phone = $this->phones->normalize((string) $data['customer_phone']);
        $channel = (string) $data['wabot_channel'];
        $conversationId = $data['wabot_conversation_id'] ?? null;
        $activeKey = hash('sha256', implode('|', [$channel, $phone]));

        try {
            $selection = DB::transaction(function () use ($data, $phone, $channel, $conversationId, $activeKey): array {
                $existing = Order::query()
                    ->where('source', 'WABOT')
                    ->where('wabot_active_booking_key', $activeKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $previousBookings = Order::query()
                        ->where('source', 'WABOT')
                        ->where('wabot_channel', $channel)
                        ->where('customer_phone', $phone)
                        ->where('status', 'DETAILS_INCOMPLETE')
                        ->where('booking_payment_status', 'PENDING')
                        ->latest('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($previousBookings as $previousBooking) {
                        if (! $this->isActiveBooking($previousBooking)
                            || ! $this->matchesConversationBooking($previousBooking, $data, $phone, $channel)
                            || ! $this->usesConfiguredBillplzEnvironment($previousBooking)) {
                            continue;
                        }

                        $previousBooking->update([
                            'wabot_conversation_id' => $previousBooking->wabot_conversation_id ?? $conversationId,
                            'wabot_active_booking_key' => $activeKey,
                        ]);
                        $existing = $previousBooking;

                        break;
                    }
                }

                if ($existing !== null
                    && $this->isActiveBooking($existing)
                    && $this->matchesConversationBooking($existing, $data, $phone, $channel)
                    && $this->usesConfiguredBillplzEnvironment($existing)) {
                    if ($existing->wabot_conversation_id === null && $conversationId !== null) {
                        $existing->update(['wabot_conversation_id' => $conversationId]);
                    }

                    return ['order' => $existing, 'reused' => true];
                }

                if ($existing !== null) {
                    $existing->update(['wabot_active_booking_key' => null]);
                }

                $order = $this->orders->create([
                    ...$data,
                    'source' => 'WABOT',
                    'source_reference' => (string) Str::uuid(),
                    'wabot_active_booking_key' => $activeKey,
                    'wabot_idempotency_key' => null,
                    'customer_phone' => $phone,
                    'wabot_channel' => $channel,
                ]);

                return ['order' => $order, 'reused' => false];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::query()
                ->where('source', 'WABOT')
                ->where('wabot_active_booking_key', $activeKey)
                ->first();

            if ($existing === null
                || ! $this->isActiveBooking($existing)
                || ! $this->matchesConversationBooking($existing, $data, $phone, $channel)
                || ! $this->usesConfiguredBillplzEnvironment($existing)) {
                throw $exception;
            }

            $selection = ['order' => $existing, 'reused' => true];
        }

        /** @var Order $order */
        $order = $selection['order'];
        if ($selection['reused']) {
            $pendingPayment = $order->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->where('status', 'PENDING')
                ->latest('id')
                ->first();

            if ($pendingPayment !== null && blank($pendingPayment->metadata['billplz']['checkout_url'] ?? null)) {
                throw new WabotBookingConflictException('The booking bill is still being prepared. Retry shortly.');
            }
        }

        $checkout = $this->bookingPayments->create(
            $order,
            throwGatewayFailure: true,
            paymentMethod: $paymentMethod,
        );

        return ['order' => $order, 'payment_url' => $checkout['payment_url'], 'reused' => $selection['reused']];
    }

    private function isActiveBooking(Order $order): bool
    {
        if ($order->status !== 'DETAILS_INCOMPLETE' || $order->booking_payment_status !== 'PENDING') {
            return false;
        }

        $payment = $order->payments()->where('payment_type', 'BOOKING_DEPOSIT')->latest('id')->first();

        return $payment === null || $payment->status === 'PENDING';
    }

    private function matchesConversationBooking(Order $order, array $data, string $phone, string $channel): bool
    {
        if (($order->wabot_channel ?? 'kingkadkahwin') !== $channel
            || (string) $order->customer_phone !== $phone
            || (int) $order->package_count !== (int) $data['package_count']
            || Str::lower(trim((string) $order->customer_name)) !== Str::lower(trim((string) $data['customer_name']))
            || $order->package_type !== ($data['package_type'] ?? null)
            || $order->wabot_package_code !== ($data['wabot_package_code'] ?? null)) {
            return false;
        }

        if (array_key_exists('customer_email', $data)
            && (string) $order->customer_email !== strtolower(trim((string) ($data['customer_email'] ?? '')))) {
            return false;
        }

        if (array_key_exists('side', $data)
            && (int) $order->package_count === 1
            && $order->packageSides()->value('side') !== $data['side']) {
            return false;
        }

        if (array_key_exists('package_format', $data)
            && (int) $order->package_count === 2
            && $order->package_format !== $data['package_format']) {
            return false;
        }

        return ! array_key_exists('first_event_side', $data)
            || (int) $order->package_count !== 2
            || $order->first_event_side === $data['first_event_side'];
    }

    private function usesConfiguredBillplzEnvironment(Order $order): bool
    {
        $configuredHost = parse_url((string) config('services.billplz.base_url', ''), PHP_URL_HOST);
        if (! in_array($configuredHost, ['www.billplz.com', 'www.billplz-sandbox.com'], true)) {
            return false;
        }

        $payment = $order->payments()
            ->where('payment_type', 'BOOKING_DEPOSIT')
            ->latest('id')
            ->first();
        $checkoutUrl = $payment?->metadata['billplz']['checkout_url'] ?? null;

        return ! is_string($checkoutUrl)
            || trim($checkoutUrl) === ''
            || (parse_url($checkoutUrl, PHP_URL_SCHEME) === 'https'
                && parse_url($checkoutUrl, PHP_URL_HOST) === $configuredHost);
    }

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
            || $order->wabot_conversation_id !== ($data['wabot_conversation_id'] ?? null)
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
            throw new WabotBookingConflictException('The booking attempt or Idempotency-Key belongs to a different booking.');
        }
    }
}
