<?php

namespace App\Services\Orders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssignWabotNumberService
{
    public function assign(Request $request): string
    {
        $numbers = config('kingkadkahwin.booking.whatsapp_numbers');

        if (! is_array($numbers) || count($numbers) !== 3) {
            throw new RuntimeException('Three Wabot numbers must be configured.');
        }

        $assigned = $request->session()->get('kkk.wabot_number');

        if (is_string($assigned) && in_array($assigned, $numbers, true)) {
            return $assigned;
        }

        $index = DB::transaction(function () use ($numbers): int {
            $counter = DB::table('wabot_route_counters')->where('id', 1)->lockForUpdate()->first();

            if ($counter === null) {
                throw new RuntimeException('The Wabot routing counter is missing. Run database migrations.');
            }

            DB::table('wabot_route_counters')->where('id', 1)->update([
                'next_index' => ((int) $counter->next_index + 1) % count($numbers),
                'updated_at' => now(),
            ]);

            return (int) $counter->next_index;
        }, 3);

        $assigned = $numbers[$index];
        $request->session()->put('kkk.wabot_number', $assigned);

        return $assigned;
    }
}
