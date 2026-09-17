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
        $secondCouple = $order->couples->firstWhere('couple_number', 2);

        return [
            'order_id' => $order->order_id,
            'customer_name' => $order->customer_name,
            'package_count' => $order->package_count,
            'status' => $order->status,

            'couple' => [
                'groom_name' => $couple?->groom_name,
                'groom_abbreviation' => $couple?->groom_abbreviation,
                'bride_name' => $couple?->bride_name,
                'bride_abbreviation' => $couple?->bride_abbreviation,
            ],

            'second_couple' => [
                'groom_name' => $secondCouple?->groom_name,
                'groom_abbreviation' => $secondCouple?->groom_abbreviation,
                'bride_name' => $secondCouple?->bride_name,
                'bride_abbreviation' => $secondCouple?->bride_abbreviation,
            ],

            'package_sides' => $order->packageSides
                ->sortBy('side')
                ->map(function ($side) {
                    return [
                        'side' => $side->side,

                        'design' => [
                            'theme' => $side->design?->theme,
                            'design_code' => $side->design?->design_code,
                            'card_title' => $side->design?->card_title,
                            'has_card_image' => filled($side->design?->card_image_path),
                        ],

                        'parents' => [
                            'father_name' => $side->parents?->father_name,
                            'mother_name' => $side->parents?->mother_name,
                        ],

                        'event' => [
                            'day_name' => $side->event?->day_name,
                            'event_date' => $side->event?->event_date?->format('Y-m-d'),
                            'hijri_date' => $side->event?->hijri_date,
                            'meal_time' => $side->event?->meal_time,
                            'bersanding_time' => $side->event?->bersanding_time,
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
                })
                ->values()
                ->all(),

            'fulfilment' => [
                'method' => $order->fulfilment?->method,
                'recipient_name' => $order->fulfilment?->recipient_name,
                'recipient_phone' => $order->fulfilment?->recipient_phone,
                'shipping_address' => $order->fulfilment?->shipping_address,
            ],
        ];
    }
}
