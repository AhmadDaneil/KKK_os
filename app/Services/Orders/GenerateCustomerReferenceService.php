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
            return $order->displayCustomerReference() ?? (string) $order->customer_reference;
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
                return $lockedOrder->displayCustomerReference() ?? (string) $lockedOrder->customer_reference;
            }

            $sequenceDate = now(config('app.display_timezone'));
            $date = $sequenceDate->toDateString();
            $referenceDate = $sequenceDate->format('ymd');
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
            $number = max((int) $sequence->last_number, $this->legacySequenceMaximum($lockedOrder, $prefix, $referenceDate)) + 1;

            if ($number > 999) {
                throw new RuntimeException('The daily customer reference limit has been reached.');
            }

            DB::table('customer_reference_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $number, 'updated_at' => now()]);

            $reference = $prefix.$referenceDate.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $lockedOrder->update(['customer_reference' => $reference]);

            return $reference;
        }, 3);
    }

    private function legacySequenceMaximum(Order $order, string $prefix, string $referenceDate): int
    {
        if ($order->source !== 'WABOT') {
            return 0;
        }

        $channel = (string) ($order->wabot_channel ?: 'kingkadkahwin');
        $currentPrefix = (string) config("kingkadkahwin.booking.reference_prefixes.{$channel}", '');

        if ($currentPrefix !== $prefix) {
            return 0;
        }

        $legacyPrefixes = (array) config("kingkadkahwin.booking.reference_prefix_aliases.{$channel}", []);
        $maximum = 0;

        foreach ($legacyPrefixes as $legacyPrefix) {
            $references = Order::query()
                ->where('customer_reference', 'like', strtoupper((string) $legacyPrefix).$referenceDate.'-%')
                ->pluck('customer_reference');

            foreach ($references as $reference) {
                if (preg_match('/^'.preg_quote(strtoupper((string) $legacyPrefix), '/').preg_quote($referenceDate, '/').'-(\\d{3})$/', (string) $reference, $matches) === 1) {
                    $maximum = max($maximum, (int) $matches[1]);
                }
            }
        }

        return $maximum;
    }
}
