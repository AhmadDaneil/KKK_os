<?php

namespace App\Services\Invoices;

use App\Models\Order;
use RuntimeException;

class GenerateInvoiceNumberService
{
    public function generate(Order $order, string $customerReference): string
    {
        $channel = $order->source === 'WABOT'
            ? (string) ($order->wabot_channel ?: 'kingkadkahwin')
            : 'kingkadkahwin';
        $referencePrefix = $order->source === 'WABOT'
            ? (string) config("kingkadkahwin.booking.reference_prefixes.{$channel}", '')
            : (string) config('invoice.business_unit_prefix', 'KKK');
        $configuredPhone = (string) config("kingkadkahwin.booking.whatsapp_channels.{$channel}.number", '');
        $servicePhone = preg_replace('/\\D+/', '', (string) ($order->serving_phone_number ?: $configuredPhone));
        $invoicePrefix = strtoupper((string) config("invoice.service_phone_prefixes.{$servicePhone}", ''));

        if (
            ! preg_match('/^[A-Z]{2,3}$/', $invoicePrefix)
            || ! preg_match('/^[A-Z]{2,3}$/', $referencePrefix)
            || ! str_starts_with($customerReference, $referencePrefix)
        ) {
            throw new RuntimeException('The invoice service phone does not map to a prefix matching the customer reference.');
        }

        return $invoicePrefix.substr($customerReference, strlen($referencePrefix));
    }
}
