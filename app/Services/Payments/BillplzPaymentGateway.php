<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BillplzPaymentGateway implements PaymentGatewayContract
{
    public function __construct(private BillplzSignatureVerifier $signatureVerifier) {}

    /**
     * @return array{provider_reference: string, payment_url: string}
     */
    public function createBalancePayment(PaymentTransaction $payment): array
    {
        return $this->createPayment($payment);
    }

    /** @return array{provider_reference: string, payment_url: string} */
    public function createPayment(PaymentTransaction $payment): array
    {
        if (! in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE'], true)
            || $payment->provider !== 'BILLPLZ') {
            throw new BillplzPaymentException('This online payment type is not supported.', 'unsupported_payment');
        }

        $payment->loadMissing('order');

        $secretKey = trim((string) config('services.billplz.secret_key', ''));
        $collectionId = trim((string) config('services.billplz.collection_id', ''));
        $baseUrl = rtrim((string) config('services.billplz.base_url', ''), '/');
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);

        if (
            $secretKey === ''
            || $collectionId === ''
            || $baseScheme !== 'https'
            || ! in_array($baseHost, ['www.billplz-sandbox.com', 'www.billplz.com'], true)
        ) {
            throw new BillplzPaymentException('The Billplz Sandbox configuration is incomplete or invalid.', 'invalid_configuration');
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https' || parse_url($appUrl, PHP_URL_HOST) === null) {
            throw new BillplzPaymentException('The application URL must use HTTPS for Billplz payments.', 'invalid_app_url');
        }

        $amountSen = $this->toSen((string) $payment->amount);
        $order = $payment->order;
        $paymentMethod = BillplzPaymentMethod::fromPayment($payment);
        BillplzPaymentMethod::assertEnabled($paymentMethod);
        $payload = [
            'collection_id' => $collectionId,
            'name' => Str::limit(trim((string) $order->customer_name), 255, ''),
            'amount' => $amountSen,
            'description' => Str::limit(
                ($payment->payment_type === 'BOOKING_DEPOSIT' ? 'Order booking fee ' : 'Order balance payment ').$order->order_id,
                200,
                '',
            ),
            'callback_url' => $appUrl.route('api.billplz.callback', [], false),
            'redirect_url' => $payment->payment_type === 'BOOKING_DEPOSIT'
                ? $appUrl.route('payments.booking.return', ['reference' => $payment->public_reference], false)
                : $appUrl.route('payments.balance.return', ['reference' => $payment->public_reference], false),
            'reference_1_label' => $paymentMethod === BillplzPaymentMethod::DUITNOW_QR ? 'Bank Code' : 'Order ID',
            'reference_1' => $paymentMethod === BillplzPaymentMethod::DUITNOW_QR ? 'BP-RHBQR' : $order->order_id,
            'reference_2_label' => 'Payment ID',
            'reference_2' => (string) $payment->id,
            'deliver' => false,
        ];

        if (filled($order->customer_phone)) {
            $payload['mobile'] = (string) $order->customer_phone;
        }

        if (filled($order->customer_email)) {
            $payload['email'] = $order->customer_email;
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/v3/bills', $payload);
        } catch (ConnectionException) {
            throw new BillplzPaymentException('The connection to Billplz Sandbox failed.', 'connection_failed');
        }

        if (! $response->successful()) {
            throw new BillplzPaymentException(
                'Billplz Sandbox could not create the payment bill.',
                'provider_rejected',
                $response->status(),
            );
        }

        $bill = $response->json();
        $billId = is_array($bill) && is_scalar($bill['id'] ?? null)
            ? trim((string) $bill['id'])
            : '';
        $paymentUrl = is_array($bill) && is_string($bill['url'] ?? null)
            ? trim($bill['url'])
            : '';
        $paymentUrlHost = parse_url($paymentUrl, PHP_URL_HOST);
        $paymentUrlScheme = parse_url($paymentUrl, PHP_URL_SCHEME);

        // Billplz Sandbox has historically returned an http bill URL even though
        // the same trusted host supports HTTPS. Upgrade only this allow-listed URL.
        if ($paymentUrlScheme === 'http' && $paymentUrlHost === $baseHost) {
            $paymentUrl = 'https://'.substr($paymentUrl, strlen('http://'));
            $paymentUrlScheme = 'https';
        }

        if (
            $billId === ''
            || $paymentUrl === ''
            || $paymentUrlScheme !== 'https'
            || $paymentUrlHost !== $baseHost
            || (string) ($bill['collection_id'] ?? '') !== $collectionId
            || (int) ($bill['amount'] ?? 0) !== $amountSen
        ) {
            throw new BillplzPaymentException('The bill response from Billplz Sandbox is invalid.', 'invalid_provider_response');
        }

        if ($paymentMethod === BillplzPaymentMethod::DUITNOW_QR) {
            $paymentUrl .= (str_contains($paymentUrl, '?') ? '&' : '?').'auto_submit=true';
        }

        return [
            'provider_reference' => $billId,
            'payment_url' => $paymentUrl,
        ];
    }

    public function verifyCallback(array $payload): array
    {
        return $this->signatureVerifier->verifyCallback($payload);
    }

    /**
     * Reconcile a payment through Billplz's authenticated API.
     *
     * This is used on the customer return page as a safe fallback when the
     * provider callback is delayed or cannot be delivered. Redirect query
     * parameters are deliberately not trusted as proof of payment.
     */
    public function retrievePaymentStatus(PaymentTransaction $payment): array
    {
        if ($payment->provider !== 'BILLPLZ' || trim((string) $payment->provider_reference) === '') {
            throw new BillplzPaymentException('This payment cannot be reconciled with Billplz.', 'unsupported_payment');
        }

        $secretKey = trim((string) config('services.billplz.secret_key', ''));
        $collectionId = trim((string) config('services.billplz.collection_id', ''));
        $baseUrl = rtrim((string) config('services.billplz.base_url', ''), '/');

        if ($secretKey === '' || $collectionId === '' || $baseUrl === '') {
            throw new BillplzPaymentException('The Billplz Sandbox configuration is incomplete.', 'invalid_configuration');
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->get($baseUrl.'/v3/bills/'.rawurlencode((string) $payment->provider_reference));
        } catch (ConnectionException) {
            throw new BillplzPaymentException('The connection to Billplz Sandbox failed.', 'connection_failed');
        }

        if (! $response->successful()) {
            throw new BillplzPaymentException(
                'Billplz Sandbox could not confirm the payment status.',
                'provider_rejected',
                $response->status(),
            );
        }

        $bill = $response->json();
        if (! is_array($bill)) {
            throw new BillplzPaymentException('The Billplz status response is invalid.', 'invalid_provider_response');
        }

        $billId = is_scalar($bill['id'] ?? null) ? trim((string) $bill['id']) : '';
        $billCollectionId = is_scalar($bill['collection_id'] ?? null) ? trim((string) $bill['collection_id']) : '';
        $state = is_scalar($bill['state'] ?? null) ? strtolower(trim((string) $bill['state'])) : '';
        $paid = filter_var($bill['paid'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $amountSen = $this->integerSen($bill['amount'] ?? null, 'amount');
        $paidAmountSen = $this->integerSen($bill['paid_amount'] ?? ($paid ? $amountSen : 0), 'paid_amount');
        $ledgerAmountSen = $this->toSen((string) $payment->amount);

        if (
            $billId === ''
            || ! hash_equals((string) $payment->provider_reference, $billId)
            || ! hash_equals($collectionId, $billCollectionId)
            || $paid === null
            || ! in_array($state, ['paid', 'due', 'deleted'], true)
            || $amountSen !== $ledgerAmountSen
            || ($paid && ($state !== 'paid' || $paidAmountSen !== $amountSen))
            || (! $paid && $state === 'paid')
            || $paidAmountSen > $amountSen
        ) {
            throw new BillplzPaymentException('The Billplz status response does not match the payment ledger.', 'invalid_provider_response');
        }

        $paidAt = is_scalar($bill['paid_at'] ?? null) ? (string) $bill['paid_at'] : null;
        $safePayload = [
            'id' => $billId,
            'collection_id' => $billCollectionId,
            'paid' => $paid,
            'state' => $state,
            'amount' => $amountSen,
            'paid_amount' => $paidAmountSen,
            'paid_at' => $paidAt,
            'source' => 'authenticated_bill_status',
        ];

        return [
            'provider' => 'BILLPLZ',
            'provider_reference' => $billId,
            'provider_event_id' => hash('sha256', implode('|', [
                $billId, $state, $paid ? 'true' : 'false', (string) $paidAt, (string) $paidAmountSen,
            ])),
            'status' => $paid ? 'PAID' : ($state === 'deleted' ? 'CANCELLED' : 'PENDING'),
            'amount_sen' => $amountSen,
            'paid_at' => $paidAt,
            'raw_payload' => $safePayload,
        ];
    }

    public function providerName(): string
    {
        return 'BILLPLZ';
    }

    private function toSen(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\d*)\.([0-9]{2})$/', $amount, $matches)) {
            throw new BillplzPaymentException('The balance payment amount is invalid.', 'invalid_amount');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }

    private function integerSen(mixed $value, string $field): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d+$/', (string) $value)) {
            throw new BillplzPaymentException("Billplz {$field} must be integer sen.", 'invalid_provider_response');
        }

        return (int) $value;
    }
}
