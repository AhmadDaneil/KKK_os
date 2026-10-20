<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateCustomerReferenceService
{
    public function generate(Order $order): string
    {
        if (filled($order->customer_reference)) {
            return (string) $order->customer_reference;
        }

        $prefix = $order->source === 'WABOT'
            ? (string) config('kingkadkahwin.booking.reference_prefixes.'.($order->wabot_channel ?? 'kingkadkahwin'), '')
            : strtoupper((string) config('invoice.business_unit_prefix', 'KKK'));
        if (! preg_match('/^[A-Z]{2,3}$/', $prefix)) {
            throw new RuntimeException('The customer reference prefix is invalid.');
        }

        return DB::transaction(function () use ($order, $prefix): string {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (filled($lockedOrder->customer_reference)) {
                return (string) $lockedOrder->customer_reference;
            }

            $date = now(config('app.display_timezone'))->toDateString();
            DB::table('customer_reference_sequences')->insertOrIgnore([
                'business_unit' => $prefix,
                'sequence_date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('customer_reference_sequences')
                ->where('business_unit', $prefix)
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();
            $number = ((int) $sequence->last_number) + 1;

            if ($number > 999) {
                throw new RuntimeException('The daily customer reference limit has been reached.');
            }

            DB::table('customer_reference_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);

            $reference = $prefix.now(config('app.display_timezone'))->format('ymd').'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $lockedOrder->update(['customer_reference' => $reference]);

            return $reference;
        }, 3);
    }
}
