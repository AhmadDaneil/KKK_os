<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use App\Models\Order;
use App\Support\Photoshop\PhotoshopAutoMergeContractV1;
use App\Support\Photoshop\PhotoshopCekHantaranContractV2;
use Illuminate\Support\Collection;
use RuntimeException;

class ExportPhotoshopAutoMergeCsvService
{
    public function __construct(
        private readonly BuildPhotoshopAutoMergeRowService $rowBuilder,
    ) {}

    /**
     * @param  Collection<int, MergeJob>  $mergeJobs
     * @param  callable(MergeJob): (int|string)  $quantityResolver
     */
    public function export(
        Collection $mergeJobs,
        string $absolutePath,
        callable $quantityResolver,
        ?Order $order = null,
    ): string {
        if ($mergeJobs->isEmpty()) {
            throw new RuntimeException('No merge jobs supplied for Photoshop CSV export.');
        }

        $hasMockup = $order !== null && (int) $order->package_count === 2;

        if ($hasMockup && blank($order->jumlah_hantaran)) {
            throw new RuntimeException('Jumlah Hantaran is required for a two-package Photoshop export.');
        }

        $headers = $hasMockup
            ? PhotoshopCekHantaranContractV2::HEADERS
            : PhotoshopAutoMergeContractV1::HEADERS;

        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create export directory: {$directory}");
        }

        $handle = fopen($absolutePath, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open Photoshop CSV export path: {$absolutePath}");
        }

        try {
            // UTF-8 BOM improves Windows/Photoshop handling of Malay names/characters.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            $firstCardRow = null;
            $cardRowsBySide = [];

            foreach ($mergeJobs->sortBy(['order_id', 'side', 'id']) as $mergeJob) {
                $row = $this->rowBuilder->build(
                    $mergeJob,
                    $quantityResolver($mergeJob)
                );

                if ($hasMockup) {
                    $row['output_id'] = $mergeJob->job_id;
                    $row['jumlah_hantaran'] = '';
                    $firstCardRow ??= $row;
                    $cardRowsBySide[$mergeJob->side] = $row;
                }

                $ordered = [];

                foreach ($headers as $header) {
                    $ordered[] = $row[$header] ?? '';
                }

                fputcsv($handle, $ordered);
            }

            if ($hasMockup) {
                $eventRow = $cardRowsBySide[$order->first_event_side] ?? $firstCardRow;
                $mockupRow = array_fill_keys($headers, '');
                $mockupRow['noinvoice'] = $order->order_id;
                $mockupRow['tema'] = PhotoshopCekHantaranContractV2::MOCKUP_THEME;
                $mockupRow['designcode'] = PhotoshopCekHantaranContractV2::MOCKUP_DESIGN_CODE;
                $mockupRow['majlis'] = PhotoshopCekHantaranContractV2::MOCKUP_SIDE;
                $mockupRow['namapengantinlelaki'] = $firstCardRow['namapengantinlelaki'] ?? '';
                $mockupRow['namapengantinperempuan'] = $firstCardRow['namapengantinperempuan'] ?? '';
                $mockupRow['hari'] = $eventRow['hari'] ?? '';
                $mockupRow['tarikh'] = $eventRow['tarikh'] ?? '';
                $mockupRow['output_id'] = $order->order_id.'-CH';
                $mockupRow['jumlah_hantaran'] = $order->jumlah_hantaran;

                fputcsv($handle, array_values($mockupRow));
            }
        } finally {
            fclose($handle);
        }

        return $absolutePath;
    }
}
