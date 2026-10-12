<?php

namespace App\Services\Payments;

use RuntimeException;

class BillplzSignatureVerifier
{
    public function verifyCallback(array $payload): array
    {
        $signatureKey = (string) config('services.billplz.x_signature_key', '');
        $providedSignature = $payload['x_signature'] ?? null;

        if ($signatureKey === '' || ! is_string($providedSignature) || $providedSignature === '') {
            throw new RuntimeException('Billplz X Signature is missing or not configured.');
        }

        $parameters = [];
        foreach ($payload as $key => $value) {
            if (strtolower((string) $key) === 'x_signature') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                throw new RuntimeException('Billplz callback parameters must be flat values.');
            }

            $parameters[(string) $key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
        }

        uksort($parameters, static fn (string $left, string $right): int => strcasecmp($left, $right));
        $source = implode('|', array_map(
            static fn (string $key, string $value): string => $key.$value,
            array_keys($parameters),
            array_values($parameters),
        ));
        $expectedSignature = hash_hmac('sha256', $source, $signatureKey);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Billplz X Signature verification failed.');
        }

        foreach (['id', 'collection_id', 'paid', 'state', 'amount'] as $required) {
            if (! array_key_exists($required, $payload)) {
                throw new RuntimeException("Missing Billplz callback field: {$required}.");
            }
        }

        $collectionId = (string) config('services.billplz.collection_id', '');
        if ($collectionId === '' || ! hash_equals($collectionId, (string) $payload['collection_id'])) {
            throw new RuntimeException('Billplz callback collection does not match configuration.');
        }

        $state = strtolower(trim((string) $payload['state']));
        $paid = filter_var($payload['paid'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($paid === null || ! in_array($state, ['paid', 'due', 'deleted'], true)) {
            throw new RuntimeException('Billplz callback payment state is invalid.');
        }

        $amountSen = $this->integerSen($payload['amount'], 'amount');
        $paidAmountSen = $this->integerSen($payload['paid_amount'] ?? ($paid ? $payload['amount'] : 0), 'paid_amount');
        if (($paid && ($state !== 'paid' || $paidAmountSen !== $amountSen))
            || (! $paid && $state === 'paid')
            || $paidAmountSen > $amountSen) {
            throw new RuntimeException('Billplz callback payment state and amount do not match.');
        }

        return [
            'provider' => 'BILLPLZ',
            'provider_reference' => (string) $payload['id'],
            'provider_event_id' => hash('sha256', implode('|', [
                (string) $payload['id'], $state, $paid ? 'true' : 'false',
                (string) ($payload['paid_at'] ?? ''), (string) $paidAmountSen,
            ])),
            'status' => $paid ? 'PAID' : ($state === 'deleted' ? 'CANCELLED' : 'PENDING'),
            'amount_sen' => $amountSen,
            'paid_at' => $payload['paid_at'] ?? null,
            'raw_payload' => $payload,
        ];
    }

    private function integerSen(mixed $value, string $field): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d+$/', (string) $value)) {
            throw new RuntimeException("Billplz callback {$field} must be integer sen.");
        }

        return (int) $value;
    }
}
