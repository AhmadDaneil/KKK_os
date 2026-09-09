<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\DB;

class GenerateOrderIdService
{
    public function generate(): string
    {
        $date = now()->toDateString();
        $datePart = now()->format('ymd');

        return DB::transaction(function () use ($date, $datePart) {
            $sequence = DB::table('order_number_sequences')
                ->where('sequence_date', $date)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    DB::table('order_number_sequences')->insert([
                        'sequence_date' => $date,
                        'last_number' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $next = 1;
                } catch (\Throwable $e) {
                    // Another request may have created today's row concurrently.
                    $sequence = DB::table('order_number_sequences')
                        ->where('sequence_date', $date)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $next = $sequence->last_number + 1;

                    DB::table('order_number_sequences')
                        ->where('sequence_date', $date)
                        ->update([
                            'last_number' => $next,
                            'updated_at' => now(),
                        ]);
                }
            } else {
                $next = $sequence->last_number + 1;

                DB::table('order_number_sequences')
                    ->where('sequence_date', $date)
                    ->update([
                        'last_number' => $next,
                        'updated_at' => now(),
                    ]);
            }

            return sprintf('KKK-%s-%04d', $datePart, $next);
        }, 3);
    }
}
