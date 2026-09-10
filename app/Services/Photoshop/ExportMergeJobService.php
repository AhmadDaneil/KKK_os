<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;
use RuntimeException;

class ExportMergeJobService
{
    public function __construct(
        private PhotoshopExportContract $contract,
    ) {}

    /**
     * Prepare one merge job for downstream Photoshop export.
     *
     * This service does not write CSV/Google Sheet yet.
     * It only delegates transformation to the verified contract.
     */
    public function prepare(MergeJob $job): array
    {
        if ($job->status !== 'PENDING_EXPORT') {
            throw new RuntimeException(
                "Merge job {$job->job_id} is not ready for export."
            );
        }

        return [
            'job_id' => $job->job_id,

            'contract_version' => $this->contract->version(),

            'headers' => $this->contract->headers(),

            'row' => $this->contract->transform($job),
        ];
    }
}