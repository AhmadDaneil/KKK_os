<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use RuntimeException;

class ExportMergeJobService
{
    public function __construct(
        private PhotoshopExportContract $contract,
    ) {}

    public function prepare(MergeJob $job): array
    {
        if ($job->status !== 'PENDING_EXPORT') {
            throw new RuntimeException(
                "Merge job {$job->job_id} is not ready for export."
            );
        }

        return [
            'contract_version' => $this->contract->version(),
            'headers' => $this->contract->headers(),
            'row' => $this->contract->transform($job),
        ];
    }
}