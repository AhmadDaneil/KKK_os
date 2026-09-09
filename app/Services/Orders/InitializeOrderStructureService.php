<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InitializeOrderStructureService
{
    public function initialize(Order $order, ?string $singlePackageSide = null): Order
    {
        return DB::transaction(function () use ($order, $singlePackageSide) {
            if ($order->package_count === 1) {
                if (! in_array($singlePackageSide, ['LELAKI', 'PEREMPUAN'], true)) {
                    throw new InvalidArgumentException('singlePackageSide must be LELAKI or PEREMPUAN for a 1-package order.');
                }
                $sides = [$singlePackageSide];
            } elseif ($order->package_count === 2) {
                $sides = ['LELAKI', 'PEREMPUAN'];
            } else {
                throw new InvalidArgumentException('Unsupported package_count.');
            }

            $order->couples()->firstOrCreate(['couple_number' => 1]);
            $order->fulfilment()->firstOrCreate([]);

            foreach ($sides as $side) {
                $packageSide = $order->packageSides()->firstOrCreate(['side' => $side]);
                $packageSide->design()->firstOrCreate([]);
                $packageSide->parents()->firstOrCreate([]);
                $event = $packageSide->event()->firstOrCreate([]);

                foreach ([1, 2, 3] as $contactNumber) {
                    $event->contacts()->firstOrCreate(['contact_number' => $contactNumber]);
                }
            }

            return $order->fresh([
                'couples', 'packageSides.design', 'packageSides.parents', 'packageSides.event.contacts', 'fulfilment',
            ]);
        });
    }
}
