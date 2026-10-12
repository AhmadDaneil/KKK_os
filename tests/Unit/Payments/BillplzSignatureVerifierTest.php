<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\BillplzSignatureVerifier;
use RuntimeException;
use Tests\TestCase;

class BillplzSignatureVerifierTest extends TestCase
{
    private const SIGNATURE_KEY = 'billplz-test-signature-key';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.billplz.x_signature_key', self::SIGNATURE_KEY);
        config()->set('services.billplz.collection_id', 'collection-test-001');
    }

    public function test_it_accepts_a_valid_signed_paid_callback(): void
    {
        $payload = $this->signedPayload([
            'id' => 'bill-test-001',
            'collection_id' => 'collection-test-001',
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '1500',
            'paid_amount' => '1500',
        ]);

        $verified = app(BillplzSignatureVerifier::class)->verifyCallback($payload);

        $this->assertSame('BILLPLZ', $verified['provider']);
        $this->assertSame('bill-test-001', $verified['provider_reference']);
        $this->assertSame('PAID', $verified['status']);
        $this->assertSame(1500, $verified['amount_sen']);
    }

    public function test_it_rejects_a_tampered_callback(): void
    {
        $payload = $this->signedPayload([
            'id' => 'bill-test-002',
            'collection_id' => 'collection-test-001',
            'paid' => 'true',
            'state' => 'paid',
            'amount' => '1500',
            'paid_amount' => '1500',
        ]);
        $payload['amount'] = '1501';

        $this->expectException(RuntimeException::class);
        app(BillplzSignatureVerifier::class)->verifyCallback($payload);
    }

    public function test_it_rejects_a_callback_for_another_collection(): void
    {
        $payload = $this->signedPayload([
            'id' => 'bill-test-003',
            'collection_id' => 'collection-other',
            'paid' => 'false',
            'state' => 'due',
            'amount' => '1500',
            'paid_amount' => '0',
        ]);

        $this->expectException(RuntimeException::class);
        app(BillplzSignatureVerifier::class)->verifyCallback($payload);
    }

    private function signedPayload(array $payload): array
    {
        $parameters = $payload;
        uksort($parameters, static fn (string $left, string $right): int => strcasecmp($left, $right));
        $source = implode('|', array_map(
            static fn (string $key, mixed $value): string => $key.(string) $value,
            array_keys($parameters),
            array_values($parameters),
        ));
        $payload['x_signature'] = hash_hmac('sha256', $source, self::SIGNATURE_KEY);

        return $payload;
    }
}
