<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderPricingProfile;
use Illuminate\Validation\ValidationException;

class ResolveBookingAmountService
{
    /** @return array{amount: string, currency: string, profile: OrderPricingProfile} */
    public function resolve(Order $order): array
    {
        $profileCode = match ((int) $order->package_count) {
            1 => 'V1_ONE_PACKAGE',
            2 => 'V1_TWO_PACKAGES',
            default => null,
        };

        $profile = $profileCode === null ? null : OrderPricingProfile::query()
            ->where('code', $profileCode)
            ->where('is_active', true)
            ->first();

        if ($profile === null || $profile->deposit_method !== 'FIXED') {
            throw ValidationException::withMessages([
                'payment' => 'Amaun booking aktif tidak dikonfigurasi untuk tempahan ini.',
            ]);
        }

        return [
            'amount' => (string) $profile->deposit_value,
            'currency' => $profile->currency,
            'profile' => $profile,
        ];
    }
}
