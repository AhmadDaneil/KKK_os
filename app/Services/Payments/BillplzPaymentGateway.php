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
        if ($payment->payment_type !== 'BALANCE' || $payment->provider !== 'BILLPLZ') {
            throw new BillplzPaymentException('Billplz hanya boleh digunakan untuk bayaran baki.');
        }

        $payment->loadMissing('order');

        $secretKey = (string) config('services.billplz.secret_key', '');
        $collectionId = (string) config('services.billplz.collection_id', '');
        $baseUrl = rtrim((string) config('services.billplz.base_url', ''), '/');
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);

        if (
            $secretKey === ''
            || $collectionId === ''
            || $baseScheme !== 'https'
            || $baseHost !== 'www.billplz-sandbox.com'
        ) {
            throw new BillplzPaymentException('Konfigurasi Billplz Sandbox belum lengkap atau tidak sah.');
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');
        if (parse_url($appUrl, PHP_URL_SCHEME) !== 'https' || parse_url($appUrl, PHP_URL_HOST) === null) {
            throw new BillplzPaymentException('URL aplikasi mesti menggunakan HTTPS untuk pembayaran Billplz.');
        }

        $amountSen = $this->toSen((string) $payment->amount);
        $order = $payment->order;
        $payload = [
            'collection_id' => $collectionId,
            'email' => $order->customer_email,
            'name' => Str::limit(trim((string) $order->customer_name), 255, ''),
            'amount' => $amountSen,
            'description' => Str::limit('Bayaran baki tempahan '.$order->order_id, 200, ''),
            'callback_url' => $appUrl.route('api.billplz-sandbox.callback', [], false),
            'redirect_url' => $appUrl.route('public.orders.progress', [
                'order_id' => $order->order_id,
            ], false),
            'reference_1_label' => 'Order ID',
            'reference_1' => $order->order_id,
            'reference_2_label' => 'Payment ID',
            'reference_2' => (string) $payment->id,
            'deliver' => false,
        ];

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/v3/bills', $payload);
        } catch (ConnectionException) {
            throw new BillplzPaymentException('Sambungan ke Billplz Sandbox gagal.');
        }

        if (! $response->successful()) {
            throw new BillplzPaymentException('Billplz Sandbox tidak dapat mencipta bil pembayaran.');
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

        if (
            $billId === ''
            || $paymentUrl === ''
            || $paymentUrlScheme !== 'https'
            || $paymentUrlHost !== $baseHost
            || (string) ($bill['collection_id'] ?? '') !== $collectionId
            || (int) ($bill['amount'] ?? 0) !== $amountSen
        ) {
            throw new BillplzPaymentException('Respons bil daripada Billplz Sandbox tidak sah.');
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

    public function providerName(): string
    {
        return 'BILLPLZ';
    }

    private function toSen(string $amount): int
    {
        if (! preg_match('/^(0|[1-9]\d*)\.([0-9]{2})$/', $amount, $matches)) {
            throw new BillplzPaymentException('Amaun bayaran baki tidak sah.');
        }

        return ((int) $matches[1] * 100) + (int) $matches[2];
    }
}
