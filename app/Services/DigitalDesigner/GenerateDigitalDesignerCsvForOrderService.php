<?php

namespace App\Services\DigitalDesigner;

use App\Models\Order;
use App\Support\DigitalDesigner\DigitalDesignerCsvContract;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use RuntimeException;

class GenerateDigitalDesignerCsvForOrderService
{
    public function __construct(
        private readonly BuildDigitalDesignerCsvRowService $rowBuilder,
    ) {}

    public function generate(Order $order, string $absolutePath): string
    {
        $order->refresh();

        if ($order->booking_payment_status !== 'PAID') {
            throw new RuntimeException('The booking deposit must be paid before this CSV can be downloaded.');
        }

        $orders = new EloquentCollection([$order]);
        $orders->load($this->relations());

        return $this->writeOrders($orders, $absolutePath);
    }

    /**
     * @param EloquentCollection<int, Order> $orders
     */
    public function generateForOrders(EloquentCollection $orders, string $absolutePath): string
    {
        if ($orders->isEmpty()) {
            throw new RuntimeException('There are no customer orders available for this CSV.');
        }

        $orderIds = $orders->modelKeys();
        $eligibleOrders = Order::query()
            ->with($this->relations())
            ->whereKey($orderIds)
            ->where('booking_payment_status', 'PAID')
            ->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES)
            ->get();

        if ($eligibleOrders->count() !== count($orderIds)) {
            throw new RuntimeException('One or more customer orders are no longer available for this CSV.');
        }

        $ordersById = $eligibleOrders->keyBy(
            fn (Order $order): int => (int) $order->getKey(),
        );
        $orderedOrders = new EloquentCollection(array_map(
            fn (int|string $orderId): Order => $ordersById->get((int) $orderId),
            $orderIds,
        ));

        return $this->writeOrders($orderedOrders, $absolutePath);
    }

    /**
     * @param EloquentCollection<int, Order> $orders
     */
    private function writeOrders(EloquentCollection $orders, string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the Digital Designer CSV file.');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, DigitalDesignerCsvContract::HEADERS);

            foreach ($orders as $order) {
                $packageSides = $order->packageSides->sortBy('id')->values();
                $rows = $packageSides->isEmpty() ? [null] : $packageSides;

                foreach ($rows as $packageSide) {
                    $row = $this->rowBuilder->build($order, $packageSide);
                    $values = array_map(
                        fn (string $header): string => (string) ($row[$header] ?? ''),
                        DigitalDesignerCsvContract::HEADERS,
                    );

                    if (fputcsv($handle, $values) === false) {
                        throw new RuntimeException('Unable to write a Digital Designer CSV row.');
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return $absolutePath;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
        ];
    }
}
