<?php

namespace App\Services\Photoshop;

use App\Contracts\Photoshop\CardQuantityProviderContract;
use App\Models\MergeJob;
use RuntimeException;

class DatabaseCardQuantityProvider implements CardQuantityProviderContract
{
    public function quantityFor(MergeJob $mergeJob): int
    {
        $mergeJob->loadMissing('order');

        $quantity = $mergeJob->order?->card_quantity;

        if (! is_int($quantity) && ! ctype_digit((string) $quantity)) {
            throw new RuntimeException(
                "Order {$mergeJob->order?->order_id} has no valid approved card quantity."
            );
        }

        $quantity = (int) $quantity;

        if ($quantity < 1) {
            throw new RuntimeException(
                "Order {$mergeJob->order?->order_id} card quantity must be at least 1."
            );
        }

        return $quantity;
    }
}
