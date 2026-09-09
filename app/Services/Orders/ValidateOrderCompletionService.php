<?php

namespace App\Services\Orders;

use App\Models\Order;

class ValidateOrderCompletionService
{
    public function validate(Order $order): array
    {
        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
        ]);

        $missing = [];

        $couple = $order->couples->firstWhere('couple_number', 1);

        if (! $couple?->groom_name) {
            $missing[] = 'Nama pengantin lelaki';
        }

        if (! $couple?->bride_name) {
            $missing[] = 'Nama pengantin perempuan';
        }

        foreach ($order->packageSides as $packageSide) {
            $label = $packageSide->side === 'LELAKI'
                ? 'Pakej Lelaki'
                : 'Pakej Perempuan';

            $design = $packageSide->design;
            $parents = $packageSide->parents;
            $event = $packageSide->event;

            if (! $design?->design_code) {
                $missing[] = "{$label}: Kod design";
            }

            if (! $parents?->father_name) {
                $missing[] = "{$label}: Nama bapa";
            }

            if (! $parents?->mother_name) {
                $missing[] = "{$label}: Nama ibu";
            }

            if (! $event?->event_date) {
                $missing[] = "{$label}: Tarikh majlis";
            }

            if (! $event?->meal_time) {
                $missing[] = "{$label}: Masa majlis / jamuan";
            }

            if (! $event?->venue_name) {
                $missing[] = "{$label}: Nama tempat majlis";
            }

            if (! $event?->full_address) {
                $missing[] = "{$label}: Alamat penuh";
            }

            $contacts = $event?->contacts ?? collect();

            foreach ([1, 2, 3] as $contactNumber) {
                $contact = $contacts->firstWhere('contact_number', $contactNumber);

                if (! $contact?->contact_name) {
                    $missing[] = "{$label}: Contact {$contactNumber} - nama";
                }

                if (! $contact?->contact_phone) {
                    $missing[] = "{$label}: Contact {$contactNumber} - telefon";
                }
            }
        }

        $fulfilment = $order->fulfilment;

        if (! $fulfilment?->method) {
            $missing[] = 'Kaedah fulfilment';
        } elseif ($fulfilment->method === 'COURIER') {
            if (! $fulfilment->recipient_name) {
                $missing[] = 'Courier: Nama penerima';
            }

            if (! $fulfilment->recipient_phone) {
                $missing[] = 'Courier: Telefon penerima';
            }

            if (! $fulfilment->shipping_address) {
                $missing[] = 'Courier: Alamat penghantaran';
            }
        }

        return [
            'complete' => count($missing) === 0,
            'missing' => $missing,
        ];
    }
}
