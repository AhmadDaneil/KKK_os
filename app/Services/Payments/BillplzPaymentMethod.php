<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Validation\ValidationException;

final class BillplzPaymentMethod
{
    public const BILLPLZ = 'BILLPLZ';

    public const DUITNOW_QR = 'DUITNOW_QR';

    public static function assertEnabled(string $paymentMethod): void
    {
        if ($paymentMethod === self::BILLPLZ) {
            return;
        }

        if ($paymentMethod === self::DUITNOW_QR && config('services.billplz.duitnow_qr_enabled')) {
            return;
        }

        throw ValidationException::withMessages([
            'payment_method' => 'This Billplz payment method is not enabled.',
        ]);
    }

    public static function fromPayment(PaymentTransaction $payment): string
    {
        $paymentMethod = data_get($payment->metadata, 'billplz.payment_method', self::BILLPLZ);

        return in_array($paymentMethod, [self::BILLPLZ, self::DUITNOW_QR], true)
            ? $paymentMethod
            : self::BILLPLZ;
    }
}
