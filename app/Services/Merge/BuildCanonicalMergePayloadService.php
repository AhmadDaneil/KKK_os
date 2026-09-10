<?php

namespace App\Services\Merge;

use App\Models\Order;
use App\Models\OrderPackageSide;
use RuntimeException;

class BuildCanonicalMergePayloadService
{
    public function build(Order $order, OrderPackageSide $packageSide): array
    {
        $order->loadMissing([
            'couples',
            'fulfilment',
        ]);

        $packageSide->loadMissing([
            'design',
            'parents',
            'event.contacts',
        ]);

        $couple = $order->couples->firstWhere('couple_number', 1);

        if (! $couple) {
            throw new RuntimeException('Main couple record is missing.');
        }

        if (! $packageSide->design || ! $packageSide->parents || ! $packageSide->event) {
            throw new RuntimeException(
                "Package structure is incomplete for side {$packageSide->side}."
            );
        }

        $event = $packageSide->event;

        return [
            'schema_version' => 'kkk_merge_internal_v1',

            'source' => [
                'order_id' => $order->order_id,
                'order_database_id' => $order->id,
                'package_side_id' => $packageSide->id,
                'side' => $packageSide->side,
                'details_confirmed_at' => optional($order->details_confirmed_at)?->toISOString(),

                // Option A business rule:
                // one approved card quantity belongs to the whole business order.
                // Both 2-package merge jobs inherit this same value.
                'card_quantity' => $order->card_quantity,
            ],

            'generic' => [
                'namapengantin1' => $couple->groom_name,
                'namapengantin2' => $couple->bride_name,
                'namabapa' => $packageSide->parents->father_name,
                'namaibu' => $packageSide->parents->mother_name,
                'tarikh_iso' => $event->event_date?->format('Y-m-d'),
                'masa_raw' => $event->meal_time,
                'alamat' => $event->full_address,
                'design_code' => $packageSide->design->design_code,
            ],

            'couple' => [
                'groom_name' => $couple->groom_name,
                'groom_abbreviation' => $couple->groom_abbreviation,
                'bride_name' => $couple->bride_name,
                'bride_abbreviation' => $couple->bride_abbreviation,
            ],

            'design' => [
                'theme' => $packageSide->design->theme,
                'design_code' => $packageSide->design->design_code,
                'card_title' => $packageSide->design->card_title,
                'card_image_path' => $packageSide->design->card_image_path,
            ],

            'parents' => [
                'father_name' => $packageSide->parents->father_name,
                'mother_name' => $packageSide->parents->mother_name,
            ],

            'event' => [
                'day_name' => $event->day_name,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'hijri_date' => $event->hijri_date,
                'meal_time' => $event->meal_time,
                'bersanding_time' => $event->bersanding_time,
                'venue_name' => $event->venue_name,
                'full_address' => $event->full_address,
                'google_maps_url' => $event->google_maps_url,
                'contacts' => $event->contacts
                    ->sortBy('contact_number')
                    ->values()
                    ->map(fn ($contact) => [
                        'contact_number' => $contact->contact_number,
                        'contact_name' => $contact->contact_name,
                        'contact_phone' => $contact->contact_phone,
                    ])
                    ->all(),
            ],

            'fulfilment' => [
                'method' => $order->fulfilment?->method,
                'recipient_name' => $order->fulfilment?->recipient_name,
                'recipient_phone' => $order->fulfilment?->recipient_phone,
                'shipping_address' => $order->fulfilment?->shipping_address,
            ],
        ];
    }
}
