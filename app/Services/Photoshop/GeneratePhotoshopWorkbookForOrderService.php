<?php

namespace App\Services\Photoshop;

use App\Models\Order;
use App\Support\Photoshop\PhotoshopCekHantaranContractV2;
use App\Support\Photoshop\PhotoshopWorkbookWriter;
use RuntimeException;

class GeneratePhotoshopWorkbookForOrderService
{
    public function __construct(
        private readonly GeneratePhotoshopCsvForOrderService $csvGenerator,
        private readonly PhotoshopWorkbookWriter $workbookWriter,
    ) {}

    public function generate(Order $order, string $absolutePath): string
    {
        if ((int) $order->package_count !== 2) {
            throw new RuntimeException('The Photoshop workbook is available only for two-package orders.');
        }

        $csvPath = tempnam(sys_get_temp_dir(), 'photoshop-');

        if ($csvPath === false) {
            throw new RuntimeException('Cannot create a temporary Photoshop CSV.');
        }

        try {
            $this->csvGenerator->generate($order, $csvPath);
            [$headers, $cardRows, $mockupRows] = $this->splitRows($csvPath);

            return $this->workbookWriter->write($absolutePath, [
                'READY_TO_MERGE' => [$headers, ...$cardRows],
                $order->order_id.'_MOCKUP_CHEQUE' => [$headers, ...$mockupRows],
            ]);
        } finally {
            unlink($csvPath);
        }
    }

    /**
     * @return array{array<int, string>, array<int, array<int, string>>, array<int, array<int, string>>}
     */
    private function splitRows(string $csvPath): array
    {
        $handle = fopen($csvPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Cannot read the generated Photoshop CSV.');
        }

        try {
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle);

            if ($headers !== PhotoshopCekHantaranContractV2::HEADERS) {
                throw new RuntimeException('The Photoshop CSV has an unexpected header.');
            }

            $sideIndex = array_search('majlis', $headers, true);
            $cardRows = [];
            $mockupRows = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($headers)) {
                    throw new RuntimeException('The Photoshop CSV has an incomplete row.');
                }

                if ($row[$sideIndex] === PhotoshopCekHantaranContractV2::MOCKUP_SIDE) {
                    $mockupRows[] = $row;
                } else {
                    $cardRows[] = $row;
                }
            }

            if (count($cardRows) !== 2 || count($mockupRows) !== 1) {
                throw new RuntimeException('The Photoshop workbook requires two card rows and one mockup cheque row.');
            }

            return [$headers, $cardRows, $mockupRows];
        } finally {
            fclose($handle);
        }
    }
}
