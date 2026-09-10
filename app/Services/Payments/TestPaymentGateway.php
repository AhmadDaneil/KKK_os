<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

class TestPaymentGateway implements PaymentGatewayContract
{
    public function createBalancePayment(PaymentTransaction $payment): array
    {
        $reference = $payment->provider_reference ?: 'TEST-' . strtoupper(Str::random(12));

        if (! $payment->provider_reference) {
            $payment->update([
                'provider' => $this->providerName(),
                'provider_reference' => $reference,
            ]);
        }

        return [
            'provider' => $this->providerName(),
            'provider_reference' => $reference,
            'payment_url' => url("/dev/payments/{$payment->id}/pay"),
        ];
    }

    public function verifyCallback(array $payload): array
    {
        return [
            'provider' => $this->providerName(),
            'provider_reference' => $payload['provider_reference'],
            'provider_event_id' => $payload['provider_event_id'] ?? null,
            'status' => $payload['status'],
            'raw_payload' => $payload,
        ];
    }

    public function providerName(): string
    {
        return 'TEST';
    }
}
