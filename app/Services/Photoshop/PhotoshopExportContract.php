<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;

interface PhotoshopExportContract
{
    /**
     * Transform one internal canonical merge job
     * into one exact Photoshop-ready export row.
     *
     * The actual mapping must follow the verified
     * Photoshop Auto Merge contract.
     */
    public function transform(MergeJob $job): array;

    /**
     * Return the exact ordered list of export headers.
     *
     * Do not guess these headers.
     * They must be verified from the real working
     * Photoshop Sheet/CSV.
     */
    public function headers(): array;

    /**
     * Contract version for traceability.
     *
     * Example:
     * photoshop_auto_merge_v1
     */
    public function version(): string;
}