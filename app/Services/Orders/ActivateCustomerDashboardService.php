<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Crypt;

class ActivateCustomerDashboardService
{
    public function __construct(
        private GenerateCustomerReferenceService $references,
        private GenerateOrderAccessLinkService $accessLinks,
    ) {}

    public function activate(Order $order, PaymentTransaction $payment): string
    {
        $reference = $this->references->generate($order);
        $metadata = $payment->metadata ?? [];
        $encryptedUrl = $metadata['customer_access']['dashboard_url_encrypted'] ?? null;

        if (is_string($encryptedUrl) && $encryptedUrl !== '') {
            $dashboardUrl = Crypt::decryptString($encryptedUrl);
        } else {
            $dashboardUrl = $this->accessLinks->generate($order->fresh(), null);
            $metadata['customer_access'] = [
                'dashboard_url_encrypted' => Crypt::encryptString($dashboardUrl),
                'activated_at' => now()->toIso8601String(),
            ];
            $payment->update(['metadata' => $metadata]);
        }

        $order->update([
            'customer_reference' => $reference,
            'dashboard_activated_at' => $order->dashboard_activated_at ?? now(),
        ]);

        return $dashboardUrl;
    }
}
