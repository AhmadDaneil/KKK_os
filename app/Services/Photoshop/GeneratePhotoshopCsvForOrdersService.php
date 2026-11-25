<?php

namespace App\Services\Photoshop;

use App\Models\Order;
use App\Support\Photoshop\PhotoshopAutoMergeContractV1;
use App\Support\Photoshop\PhotoshopCekHantaranContractV2;
use Illuminate\Support\Collection;
use RuntimeException;

class GeneratePhotoshopCsvForOrdersService
{
    public function __construct(
        private readonly GeneratePhotoshopCsvForOrderService $orderCsvService,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders
     */
    public function generate(Collection $orders, string $absolutePath): string
    {
        if ($orders->isEmpty() || $orders->count() > 10) {
            throw new RuntimeException('The Photoshop batch must contain between 1 and 10 orders.');
        }

        $headers = $orders->contains(fn (Order $order): bool => (int) $order->package_count === 2)
            ? PhotoshopCekHantaranContractV2::HEADERS
            : PhotoshopAutoMergeContractV1::HEADERS;
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create export directory: {$directory}");
        }

        $temporaryPaths = [];
        $succeeded = false;
        $output = null;

        try {
            foreach ($orders as $order) {
                $temporaryPath = tempnam($directory, '.photoshop-order-');

                if ($temporaryPath === false) {
                    throw new RuntimeException('Cannot create a temporary Photoshop CSV export.');
                }

                $temporaryPaths[] = $temporaryPath;
                $this->orderCsvService->generate($order, $temporaryPath);
            }

            $output = fopen($absolutePath, 'wb');

            if ($output === false) {
                throw new RuntimeException("Cannot open Photoshop CSV export path: {$absolutePath}");
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, escape: '');

            foreach ($temporaryPaths as $temporaryPath) {
                $this->appendOrderRows($temporaryPath, $output, $headers);
            }

            $succeeded = true;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }

            foreach ($temporaryPaths as $temporaryPath) {
                if (is_file($temporaryPath)) {
                    unlink($temporaryPath);
                }
            }

            if (! $succeeded && is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }

        return $absolutePath;
    }

    /**
     * @param  resource  $output
     * @param  list<string>  $headers
     */
    private function appendOrderRows(string $absolutePath, mixed $output, array $headers): void
    {
        $input = fopen($absolutePath, 'rb');

        if ($input === false) {
            throw new RuntimeException("Cannot read temporary Photoshop CSV export: {$absolutePath}");
        }

        try {
            $orderHeaders = fgetcsv($input, escape: '');

            if ($orderHeaders === false) {
                throw new RuntimeException('A temporary Photoshop CSV export is missing its header.');
            }

            $firstHeader = (string) ($orderHeaders[0] ?? '');
            $orderHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', $firstHeader) ?? $firstHeader;

            while (($row = fgetcsv($input, escape: '')) !== false) {
                $row = array_pad(array_slice($row, 0, count($orderHeaders)), count($orderHeaders), '');
                $rowByHeader = array_combine($orderHeaders, $row);

                if ($rowByHeader === false) {
                    throw new RuntimeException('A temporary Photoshop CSV row does not match its header.');
                }

                $orderedRow = array_map(
                    fn (string $header): string => (string) ($rowByHeader[$header] ?? ''),
                    $headers
                );

                fputcsv($output, $orderedRow, escape: '');
            }
        } finally {
            fclose($input);
        }
    }
}
