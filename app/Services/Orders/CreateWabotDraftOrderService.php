<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Services\Payments\CreateBillplzBookingPaymentService;
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
        $existing = Order::query()
            ->where('source', 'WABOT')
            ->where('source_reference', $sourceReference)
            ->first();

        if ($existing !== null) {
            $existingPayment = $existing->payments()
                ->where('payment_type', 'BOOKING_DEPOSIT')
                ->latest('id')
                ->first();
            if ($existingPayment?->status === 'PAID') {
                return [
                    'order' => $existing,
                    'payment_url' => (string) data_get($existingPayment->metadata, 'billplz.checkout_url', ''),
                    'reused' => true,
                ];
            }

            $checkout = $this->bookingPayments->create($existing);

            return ['order' => $existing, 'payment_url' => $checkout['payment_url'], 'reused' => true];
        }

        $order = DB::transaction(fn (): Order => $this->orders->create([
            ...$data,
            'source' => 'WABOT',
            'source_reference' => $sourceReference,
            'customer_phone' => $this->phones->normalize((string) $data['customer_phone']),
        ]), 3);
        $checkout = $this->bookingPayments->create($order);

        return ['order' => $order, 'payment_url' => $checkout['payment_url'], 'reused' => false];
    }
}
