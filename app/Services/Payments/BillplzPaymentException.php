<?php

namespace App\Services\Payments;

use RuntimeException;

class BillplzPaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'unknown',
        public readonly ?int $providerStatus = null,
    ) {
        parent::__construct($message);
    }
}
