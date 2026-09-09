<?php

namespace App\Services\Orders;

use App\Models\Order;

class BuildFinalReviewService
{
    public function build(Order $order): array
    {
        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
        ]);

        $couple = $order->couples->firstWhere('couple_number', 1);

        return [
            'order_id' => $order->order_id,
            'package_count' => $order->package_count,
            'status' => $order->status,
            'couple' => [
                'groom_name' => $couple?->groom_name,
                'bride_name' => $couple?->bride_name,
            ],
            'package_sides' => $order->packageSides->map(function ($side) {
                return [
                    'side' => $side->side,
                    'design' => [
                        'design_code' => $side->design?->design_code,
                    ],
                    'parents' => [
                        'father_name' => $side->parents?->father_name,
                        'mother_name' => $side->parents?->mother_name,
                    ],
                    'event' => [
                        'event_date' => $side->event?->event_date,
                        'meal_time' => $side->event?->meal_time,
                        'venue_name' => $side->event?->venue_name,
                        'full_address' => $side->event?->full_address,
                        'google_maps_url' => $side->event?->google_maps_url,
                        'contacts' => $side->event?->contacts
                            ?->sortBy('contact_number')
                            ->values()
                            ->map(fn ($contact) => [
                                'contact_number' => $contact->contact_number,
                                'name' => $contact->contact_name,
                                'phone' => $contact->contact_phone,
                            ])->all() ?? [],
                    ],
                ];
            })->values()->all(),
            'fulfilment' => [
                'method' => $order->fulfilment?->method,
                'recipient_name' => $order->fulfilment?->recipient_name,
                'recipient_phone' => $order->fulfilment?->recipient_phone,
                'shipping_address' => $order->fulfilment?->shipping_address,
            ],
        ];
    }
}
