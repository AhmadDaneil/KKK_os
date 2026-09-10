<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use App\Support\Photoshop\PhotoshopAutoMergeContractV1;
use Illuminate\Support\Collection;
use RuntimeException;

class ExportPhotoshopAutoMergeCsvService
{
    public function __construct(
        private readonly BuildPhotoshopAutoMergeRowService $rowBuilder,
    ) {
    }

    /**
     * @param Collection<int, MergeJob> $mergeJobs
     * @param callable(MergeJob): (int|string) $quantityResolver
     */
    public function export(
        Collection $mergeJobs,
        string $absolutePath,
        callable $quantityResolver,
    ): string {
        if ($mergeJobs->isEmpty()) {
            throw new RuntimeException('No merge jobs supplied for Photoshop CSV export.');
        }

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

            fputcsv($handle, PhotoshopAutoMergeContractV1::HEADERS);

            foreach ($mergeJobs->sortBy(['order_id', 'side', 'id']) as $mergeJob) {
                $row = $this->rowBuilder->build(
                    $mergeJob,
                    $quantityResolver($mergeJob)
                );

                $ordered = [];

                foreach (PhotoshopAutoMergeContractV1::HEADERS as $header) {
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
