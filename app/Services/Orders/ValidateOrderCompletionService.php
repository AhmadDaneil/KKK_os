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

        if ((int) $order->package_count === 2 && blank($order->jumlah_hantaran)) {
            $missing[] = 'Gift Amount';
        }

        if (! $order->card_quantity || (int) $order->card_quantity < 1) {
            $missing[] = 'Card quantity';
        }

        $couple = $order->couples->firstWhere('couple_number', 1);

        if (! $couple?->groom_name) {
            $missing[] = 'Groom name';
        }

        if (! $couple?->bride_name) {
            $missing[] = 'Bride name';
        }

        foreach ($order->packageSides as $packageSide) {
            $label = $packageSide->side === 'LELAKI'
                ? "Groom's package"
                : "Bride's package";

            $design = $packageSide->design;
            $parents = $packageSide->parents;
            $event = $packageSide->event;

            if (! $design?->design_code) {
                $missing[] = "{$label}: Design code";
            }

            if (! $parents?->father_name) {
                $missing[] = "{$label}: Father's name";
            }

            if (! $parents?->mother_name) {
                $missing[] = "{$label}: Mother's name";
            }

            if (! $event?->event_date) {
                $missing[] = "{$label}: Event date";
            }

            if (! $event?->meal_time) {
                $missing[] = "{$label}: Event / meal time";
            }

            if (! $event?->venue_name) {
                $missing[] = "{$label}: Venue name";
            }

            if (! $event?->full_address) {
                $missing[] = "{$label}: Full address";
            }

            $contacts = $event?->contacts ?? collect();

            foreach ([1, 2, 3] as $contactNumber) {
                $contact = $contacts->firstWhere('contact_number', $contactNumber);

                if (! $contact?->contact_name) {
                    $missing[] = "{$label}: Contact {$contactNumber} - name";
                }

                if (! $contact?->contact_phone) {
                    $missing[] = "{$label}: Contact {$contactNumber} - phone";
                }
            }
        }

        $fulfilment = $order->fulfilment;

        if (! $fulfilment?->method) {
            $missing[] = 'Fulfilment method';
        } elseif ($fulfilment->method === 'COURIER') {
            if (! $fulfilment->recipient_name) {
                $missing[] = 'Courier: Recipient name';
            }

            if (! $fulfilment->recipient_phone) {
                $missing[] = 'Courier: Recipient phone';
            }

            if (! $fulfilment->shipping_address) {
                $missing[] = 'Courier: Shipping address';
            }
        }

        return [
            'complete' => count($missing) === 0,
            'missing' => $missing,
        ];
    }
}
