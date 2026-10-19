<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $phone = $this->phones->normalize((string) $data['customer_phone']);
        $existing = Order::query()
            ->where('source', 'WABOT')
            ->where('source_reference', $sourceReference)
            ->first();

        if ($existing !== null) {
            $this->ensureSameBooking($existing, $data, $phone);
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

            $checkout = $this->bookingPayments->create($existing);

            return ['order' => $existing, 'payment_url' => $checkout['payment_url'], 'reused' => true];
        }

        try {
            $order = DB::transaction(fn (): Order => $this->orders->create([
                ...$data,
                'source' => 'WABOT',
                'source_reference' => $sourceReference,
                'customer_phone' => $phone,
            ]), 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::query()
                ->where('source', 'WABOT')
                ->where('source_reference', $sourceReference)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return $this->create($data);
        }
        $checkout = $this->bookingPayments->create($order);

        return ['order' => $order, 'payment_url' => $checkout['payment_url'], 'reused' => false];
    }

    private function ensureSameBooking(Order $order, array $data, string $phone): void
    {
        $side = (int) $order->package_count === 1
            ? $order->packageSides()->value('side')
            : null;

        if ((int) $order->package_count !== (int) $data['package_count']
            || $order->package_type !== $data['package_type']
            || (string) $order->customer_phone !== $phone
            || trim((string) $order->customer_name) !== trim((string) $data['customer_name'])
            || $side !== ($data['side'] ?? null)
            || (isset($data['package_format']) && $order->package_format !== $data['package_format'])
            || (isset($data['first_event_side']) && $order->first_event_side !== $data['first_event_side'])) {
            throw ValidationException::withMessages([
                'external_reference' => 'This Wabot reference belongs to a different booking.',
            ]);
        }
    }
}
