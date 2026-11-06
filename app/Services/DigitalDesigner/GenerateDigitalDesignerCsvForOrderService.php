<?php

namespace App\Services\DigitalDesigner;

use App\Models\Order;
use App\Support\DigitalDesigner\DigitalDesignerCsvContract;
use RuntimeException;

class GenerateDigitalDesignerCsvForOrderService
{
    public function __construct(
        private readonly BuildDigitalDesignerCsvRowService $rowBuilder,
    ) {}

    public function generate(Order $order, string $absolutePath): string
    {
        $order->refresh();
        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
        ]);

        if ($order->booking_payment_status !== 'PAID') {
            throw new RuntimeException('The booking deposit must be paid before this CSV can be downloaded.');
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the Digital Designer CSV file.');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, DigitalDesignerCsvContract::HEADERS);

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
        } finally {
            fclose($handle);
        }

        return $absolutePath;
    }
}
