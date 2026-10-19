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

        $expectedAmount = match ((int) $order->package_count) {
            1 => '50.00',
            2 => '100.00',
            default => null,
        };

        if ($profile === null
            || $profile->deposit_method !== 'FIXED'
            || (float) $profile->deposit_value !== (float) $expectedAmount
            || $profile->currency !== 'MYR') {
            throw ValidationException::withMessages([
                'payment' => 'The required RM50 or RM100 booking amount is not configured for this order.',
            ]);
        }

        return [
            'amount' => $expectedAmount,
            'currency' => $profile->currency,
            'profile' => $profile,
        ];
    }
}
