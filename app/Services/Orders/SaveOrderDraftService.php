<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveOrderDraftService
{
    public function save(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order->load([
                'couples',
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'fulfilment',
            ]);

            if (array_key_exists('card_quantity', $data)) {
                $order->forceFill([
                'card_quantity' => $data['card_quantity'] === null
                ? null
                : (int) $data['card_quantity'],
                ])->save();
            }

            if (array_key_exists('couple', $data)) {
                $couple = $order->couples->firstWhere('couple_number', 1);

                if (! $couple) {
                    throw ValidationException::withMessages([
                        'couple' => 'Main couple structure is missing for this order.',
                    ]);
                }

                $couple->update([
                    'groom_name' => $this->cleanString(Arr::get($data, 'couple.groom_name')),
                    'groom_abbreviation' => $this->cleanString(Arr::get($data, 'couple.groom_abbreviation')),
                    'bride_name' => $this->cleanString(Arr::get($data, 'couple.bride_name')),
                    'bride_abbreviation' => $this->cleanString(Arr::get($data, 'couple.bride_abbreviation')),
                ]);
            }

            foreach ((array) ($data['sides'] ?? []) as $sideName => $sideData) {
                if (! in_array($sideName, ['LELAKI', 'PEREMPUAN'], true)) {
                    throw ValidationException::withMessages([
                        "sides.$sideName" => 'Unsupported package side.',
                    ]);
                }

                $packageSide = $order->packageSides->firstWhere('side', $sideName);

                if (! $packageSide) {
                    throw ValidationException::withMessages([
                        "sides.$sideName" => 'This package side does not belong to the order.',
                    ]);
                }

                if (array_key_exists('design', $sideData)) {
                    $packageSide->design->update([
                        'theme' => $this->cleanString(Arr::get($sideData, 'design.theme')),
                        'design_code' => $this->normalizeDesignCode(Arr::get($sideData, 'design.design_code')),
                        'card_title' => $this->cleanString(Arr::get($sideData, 'design.card_title')),
                    ]);
                }

                if (array_key_exists('parents', $sideData)) {
                    $packageSide->parents->update([
                        'father_name' => $this->cleanString(Arr::get($sideData, 'parents.father_name')),
                        'mother_name' => $this->cleanString(Arr::get($sideData, 'parents.mother_name')),
                    ]);
                }

                if (array_key_exists('event', $sideData)) {
                    $event = $packageSide->event;

                    $event->update([
                        'day_name' => $this->cleanString(Arr::get($sideData, 'event.day_name')),
                        'event_date' => $this->emptyToNull(Arr::get($sideData, 'event.event_date')),
                        'hijri_date' => $this->cleanString(Arr::get($sideData, 'event.hijri_date')),
                        'meal_time' => $this->emptyToNull(Arr::get($sideData, 'event.meal_time')),
                        'bersanding_time' => $this->emptyToNull(Arr::get($sideData, 'event.bersanding_time')),
                        'venue_name' => $this->cleanString(Arr::get($sideData, 'event.venue_name')),
                        'full_address' => $this->cleanMultiline(Arr::get($sideData, 'event.full_address')),
                        'google_maps_url' => $this->cleanString(Arr::get($sideData, 'event.google_maps_url')),
                    ]);

                    foreach ((array) Arr::get($sideData, 'event.contacts', []) as $contactNumber => $contactData) {
                        $contactNumber = (int) $contactNumber;

                        if (! in_array($contactNumber, [1, 2, 3], true)) {
                            throw ValidationException::withMessages([
                                "sides.$sideName.event.contacts.$contactNumber" => 'Only contact persons 1 to 3 are supported.',
                            ]);
                        }

                        $contact = $event->contacts->firstWhere('contact_number', $contactNumber);

                        if (! $contact) {
                            throw ValidationException::withMessages([
                                "sides.$sideName.event.contacts.$contactNumber" => 'Contact slot is missing.',
                            ]);
                        }

                        $contact->update([
                            'contact_name' => $this->cleanString($contactData['contact_name'] ?? null),
                            'contact_phone' => $this->normalizePhone($contactData['contact_phone'] ?? null),
                        ]);
                    }
                }
            }

            if (array_key_exists('fulfilment', $data)) {
                $method = Arr::get($data, 'fulfilment.method');
                $method = $method === '' ? null : $method;

                $fulfilment = $order->fulfilment;

                if (! $fulfilment) {
                    throw ValidationException::withMessages([
                        'fulfilment' => 'Fulfilment structure is missing for this order.',
                    ]);
                }

                $fulfilment->update([
                    'method' => $method,
                    'recipient_name' => $method === 'COURIER'
                        ? $this->cleanString(Arr::get($data, 'fulfilment.recipient_name'))
                        : null,
                    'recipient_phone' => $method === 'COURIER'
                        ? $this->normalizePhone(Arr::get($data, 'fulfilment.recipient_phone'))
                        : null,
                    'shipping_address' => $method === 'COURIER'
                        ? $this->cleanMultiline(Arr::get($data, 'fulfilment.shipping_address'))
                        : null,
                ]);
            }

            // Save Draft never confirms the order.
            $order->forceFill([
                'status' => 'DETAILS_INCOMPLETE',
                'details_confirmed_at' => null,
            ])->save();

            return $order->fresh([
                'couples',
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'fulfilment',
            ]);
        });
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function cleanMultiline(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        $lines = array_map(
            fn ($line) => preg_replace('/[ \t]+/u', ' ', trim($line)),
            explode("\n", $value)
        );
        $lines = array_values(array_filter($lines, fn ($line) => $line !== ''));

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function normalizeDesignCode(mixed $value): ?string
    {
        $value = $this->cleanString($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    private function normalizePhone(mixed $value): ?string
    {
        $value = $this->cleanString($value);

        if ($value === null) {
            return null;
        }

        // Safe V1 normalization: remove common visual separators only.
        return preg_replace('/[\s\-()]+/', '', $value);
    }

    private function emptyToNull(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
