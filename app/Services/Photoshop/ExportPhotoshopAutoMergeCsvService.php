<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use App\Models\Order;
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
            throw new RuntimeException('Gift amount is required for a two-package Photoshop export.');
        }

        $headers = PhotoshopCekHantaranContractV2::HEADERS;

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

            $sortedMergeJobs = $mergeJobs->sortBy(['order_id', 'side', 'id']);
            $mockupSide = $hasMockup
                ? ($sortedMergeJobs->firstWhere('side', $order->first_event_side)?->side
                    ?? $sortedMergeJobs->first()?->side)
                : null;

            foreach ($sortedMergeJobs as $mergeJob) {
                $row = $this->rowBuilder->build(
                    $mergeJob,
                    $quantityResolver($mergeJob)
                );
                $row['output_id'] = $hasMockup ? $mergeJob->job_id : '';
                $row['jumlah_hantaran'] = '';

                if (! $hasMockup) {
                    $row['majlis_ch'] = 'TIADA';
                    $row['tema_ch'] = 'TIADA';
                    $row['designcode_ch'] = 'TIADA';
                } elseif ($mergeJob->side === $mockupSide) {
                    $row['majlis_ch'] = PhotoshopCekHantaranContractV2::MOCKUP_SIDE;
                    $row['tema_ch'] = PhotoshopCekHantaranContractV2::MOCKUP_THEME;
                    $row['designcode_ch'] = PhotoshopCekHantaranContractV2::MOCKUP_DESIGN_CODE;
                    $row['jumlah_hantaran'] = mb_strtoupper(
                        trim((string) $order->jumlah_hantaran),
                        'UTF-8'
                    );
                }

                $ordered = [];

                foreach ($headers as $header) {
                    $ordered[] = $row[$header] ?? '';
                }

                fputcsv($handle, $ordered);
            }
        } finally {
            fclose($handle);
        }

        return $absolutePath;
    }
}
