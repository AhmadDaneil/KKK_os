<?php

namespace App\Services\Photoshop;

use App\Models\MergeJob;

interface PhotoshopExportContract
{
    /**
     * Transform one canonical merge job into the exact
     * row required by the verified Photoshop integration.
     */
    public function transform(MergeJob $job): array;

    /**
     * Exact ordered column/header list required downstream.
     */
    public function headers(): array;

    /**
     * Contract version for auditability.
     */
    public function version(): string;
}