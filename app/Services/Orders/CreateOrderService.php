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
            $channel = $data['wabot_channel'] ?? 'kingkadkahwin';
            $servingPhone = $data['serving_phone_number']
                ?? config("kingkadkahwin.booking.whatsapp_channels.{$channel}.number");

            $order = Order::create([
                'order_id' => $this->orderIdGenerator->generate(),
                'source' => $data['source'] ?? 'DIRECT',
                'source_reference' => $data['source_reference'] ?? null,
                'wabot_conversation_id' => $data['wabot_conversation_id'] ?? null,
                'wabot_active_booking_key' => $data['wabot_active_booking_key'] ?? null,
                'wabot_channel' => $data['wabot_channel'] ?? null,
                'serving_phone_number' => is_string($servingPhone)
                    ? preg_replace('/\\D+/', '', $servingPhone)
                    : null,
                'wabot_idempotency_key' => $data['wabot_idempotency_key'] ?? null,
                'wabot_package_code' => $data['wabot_package_code'] ?? null,
                'package_count' => $data['package_count'],
                'package_type' => $data['package_type'] ?? null,
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
                'booking_payment_status' => 'PENDING',
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
