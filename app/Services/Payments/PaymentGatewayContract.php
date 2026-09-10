<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;

interface PaymentGatewayContract
{
    public function createBalancePayment(PaymentTransaction $payment): array;

    public function verifyCallback(array $payload): array;

    public function providerName(): string;
}
