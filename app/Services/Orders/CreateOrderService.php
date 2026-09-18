<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CreateOrderService
{
    public function __construct(
        private GenerateOrderIdService $orderIdGenerator,
        private InitializeOrderStructureService $structureInitializer,
    ) {}

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'order_id' => $this->orderIdGenerator->generate(),
                'package_count' => $data['package_count'],
                'package_format' => (int) $data['package_count'] === 2
                    ? ($data['package_format'] ?? 'SEPARATE')
                    : null,
                'first_event_side' => (int) $data['package_count'] === 2
                    ? ($data['first_event_side'] ?? 'LELAKI')
                    : null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => isset($data['customer_email'])
                    ? strtolower(trim($data['customer_email']))
                    : null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'booking_payment_status' => 'TEST',
                'status' => 'DETAILS_INCOMPLETE',
            ]);

            $this->structureInitializer->initialize(
                $order,
                $data['side'] ?? null
            );

            return $order->fresh();
        });
    }
}
