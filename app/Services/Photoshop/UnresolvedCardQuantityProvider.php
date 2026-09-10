<?php

namespace App\Services\Photoshop;

use App\Contracts\Photoshop\CardQuantityProviderContract;
use App\Models\MergeJob;
use RuntimeException;

class UnresolvedCardQuantityProvider implements CardQuantityProviderContract
{
    public function quantityFor(MergeJob $mergeJob): int
    {
        throw new RuntimeException(
            'Photoshop qtykad source is not yet approved. ' .
            'Do not export production CSV until the Project Owner confirms ' .
            'whether card quantity is stored per order or per package side.'
        );
    }
}
