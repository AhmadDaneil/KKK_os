<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Payments\BillplzPaymentException;
use App\Services\Payments\BillplzPaymentGateway;
use App\Services\Payments\HandlePaymentCallbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class BalancePaymentReturnController extends Controller
{
    public function show(
        Request $request,
        string $reference,
        CustomerOrderSessionAccessService $access,
        BillplzPaymentGateway $gateway,
        HandlePaymentCallbackService $handler,
    ): View {
        $payment = PaymentTransaction::query()
            ->where('public_reference', $reference)
            ->where('payment_type', 'BALANCE')
            ->where('provider', 'BILLPLZ')
            ->with('order')
            ->firstOrFail();

        if ($payment->status === 'PENDING') {
            try {
                $payment = $handler->handle($gateway->retrievePaymentStatus($payment));
                $payment->load('order');
            } catch (Throwable $exception) {
                Log::warning('Billplz balance return reconciliation did not complete.', [
                    'payment_id' => $payment->id,
                    'reason' => $exception instanceof BillplzPaymentException
                        ? $exception->reason
                        : 'unexpected_error',
                ]);
            }
        }

        $access->establishFromProgressLookup($request, $payment->order);

        return view('public.balance-payment-return', [
            'payment' => $payment,
            'order' => $payment->order,
            'receiptUrl' => $this->trustedReceiptUrl($payment),
        ]);
    }

    private function trustedReceiptUrl(PaymentTransaction $payment): ?string
    {
        $url = data_get($payment->metadata, 'billplz.checkout_url');

        if (! is_string($url) || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(parse_url($url, PHP_URL_HOST), ['www.billplz-sandbox.com', 'www.billplz.com'], true)
                ? $url
                : null;
    }
}
