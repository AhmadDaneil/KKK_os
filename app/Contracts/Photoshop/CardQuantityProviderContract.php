<?php

namespace App\Contracts\Photoshop;

use App\Models\MergeJob;

interface CardQuantityProviderContract
{
    /**
     * Return the approved card quantity for one Photoshop merge job.
     *
     * Important:
     * KKK OS V1 has not yet locked whether quantity belongs to the whole
     * business order or independently to each package side.
     *
     * Implementations must return a positive whole number and must not
     * silently fall back to a hard-coded/default quantity.
     */
    public function quantityFor(MergeJob $mergeJob): int;
}
