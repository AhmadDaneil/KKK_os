<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\InitializeOrderStructureService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerOrderDraftController extends Controller
{
    public function update(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
        InitializeOrderStructureService $initializeOrderStructure,
        SaveOrderDraftService $saveDraft,
    ): RedirectResponse|JsonResponse {
        $order = $access->resolve($request, $orderId);
        $order->loadMissing('packageSides');
        $needsSinglePackageSide = (int) $order->package_count === 1
            && $order->packageSides->isEmpty();

        $validated = $request->validate([
            'card_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'couple' => ['sometimes', 'array'],
            'couple.groom_name' => ['nullable', 'string', 'max:255'],
            'couple.groom_abbreviation' => ['nullable', 'string', 'max:100'],
            'couple.bride_name' => ['nullable', 'string', 'max:255'],
            'couple.bride_abbreviation' => ['nullable', 'string', 'max:100'],

            'second_couple' => ['sometimes', 'array'],
            'second_couple.groom_name' => ['nullable', 'string', 'max:255'],
            'second_couple.groom_abbreviation' => ['nullable', 'string', 'max:100'],
            'second_couple.bride_name' => ['nullable', 'string', 'max:255'],
            'second_couple.bride_abbreviation' => ['nullable', 'string', 'max:100'],

            'sides' => ['sometimes', 'array'],
            'single_package_side' => [
                $needsSinglePackageSide ? 'required' : ((int) $order->package_count === 1 ? 'sometimes' : 'prohibited'),
                'nullable',
                'in:LELAKI,PEREMPUAN',
            ],
            'package_format' => ['sometimes', 'string', 'in:SEPARATE,FOLDED'],
            'first_event_side' => ['sometimes', 'string', 'in:LELAKI,PEREMPUAN'],
            'jumlah_hantaran' => [(int) $order->package_count === 2 ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:255'],

            'sides.LELAKI.design.theme' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.design.design_code' => ['nullable', 'string', 'max:100'],
            'sides.LELAKI.design.card_title' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.design.card_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'sides.LELAKI.additional_products' => ['sometimes', 'array'],
            'sides.LELAKI.additional_products.*.enabled' => ['nullable', 'boolean'],
            'sides.LELAKI.additional_products.*.size' => ['nullable', 'string', 'max:100'],
            'sides.LELAKI.additional_products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sides.LELAKI.additional_products.*.material' => ['nullable', 'string', 'max:100'],
            'sides.LELAKI.additional_products.*.orientation' => ['nullable', 'in:LANDSCAPE,PORTRAIT'],
            'sides.LELAKI.additional_products.*.instructions' => ['nullable', 'string', 'max:1000'],
            'sides.LELAKI.parents.father_name' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.parents.mother_name' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.event.day_name' => ['nullable', 'string', 'max:50'],
            'sides.LELAKI.event.event_date' => ['nullable', 'date'],
            'sides.LELAKI.event.hijri_date' => ['nullable', 'string', 'max:100'],
            'sides.LELAKI.event.meal_time' => ['nullable', 'date_format:H:i'],
            'sides.LELAKI.event.bersanding_time' => ['nullable', 'date_format:H:i'],
            'sides.LELAKI.event.venue_name' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.event.full_address' => ['nullable', 'string', 'max:2000'],
            'sides.LELAKI.event.google_maps_url' => ['nullable', 'url:http,https', 'max:2000'],
            'sides.LELAKI.event.contacts' => ['sometimes', 'array'],
            'sides.LELAKI.event.contacts.*.contact_name' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.event.contacts.*.contact_phone' => ['nullable', 'string', 'regex:/^01\d{8,9}$/'],
            'sides.LELAKI.card_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'sides.PEREMPUAN.design.theme' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.design.design_code' => ['nullable', 'string', 'max:100'],
            'sides.PEREMPUAN.design.card_title' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.design.card_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'sides.PEREMPUAN.additional_products' => ['sometimes', 'array'],
            'sides.PEREMPUAN.additional_products.*.enabled' => ['nullable', 'boolean'],
            'sides.PEREMPUAN.additional_products.*.size' => ['nullable', 'string', 'max:100'],
            'sides.PEREMPUAN.additional_products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sides.PEREMPUAN.additional_products.*.material' => ['nullable', 'string', 'max:100'],
            'sides.PEREMPUAN.additional_products.*.orientation' => ['nullable', 'in:LANDSCAPE,PORTRAIT'],
            'sides.PEREMPUAN.additional_products.*.instructions' => ['nullable', 'string', 'max:1000'],
            'sides.PEREMPUAN.parents.father_name' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.parents.mother_name' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.event.day_name' => ['nullable', 'string', 'max:50'],
            'sides.PEREMPUAN.event.event_date' => ['nullable', 'date'],
            'sides.PEREMPUAN.event.hijri_date' => ['nullable', 'string', 'max:100'],
            'sides.PEREMPUAN.event.meal_time' => ['nullable', 'date_format:H:i'],
            'sides.PEREMPUAN.event.bersanding_time' => ['nullable', 'date_format:H:i'],
            'sides.PEREMPUAN.event.venue_name' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.event.full_address' => ['nullable', 'string', 'max:2000'],
            'sides.PEREMPUAN.event.google_maps_url' => ['nullable', 'url:http,https', 'max:2000'],
            'sides.PEREMPUAN.event.contacts' => ['sometimes', 'array'],
            'sides.PEREMPUAN.event.contacts.*.contact_name' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.event.contacts.*.contact_phone' => ['nullable', 'string', 'regex:/^01\d{8,9}$/'],
            'sides.PEREMPUAN.card_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'fulfilment' => ['sometimes', 'array'],
            'fulfilment.method' => ['nullable', 'in:COURIER,PICKUP'],
            'fulfilment.recipient_name' => ['nullable', 'string', 'max:255'],
            'fulfilment.recipient_phone' => ['nullable', 'string', 'regex:/^01\d{8,9}$/'],
            'fulfilment.shipping_address' => ['nullable', 'string', 'max:2000'],
        ], [
            'sides.*.event.contacts.*.contact_phone.regex' => 'The phone number must contain 10 or 11 digits and start with 01.',
            'fulfilment.recipient_phone.regex' => 'The phone number must contain 10 or 11 digits and start with 01.',
        ]);

        if ($needsSinglePackageSide) {
            $selectedSide = $validated['single_package_side'];
            $initializeOrderStructure->initialize($order, $selectedSide);

            if ($selectedSide === 'PEREMPUAN' && isset($validated['sides']['LELAKI'])) {
                $validated['sides']['PEREMPUAN'] = $validated['sides']['LELAKI'];
                unset($validated['sides']['LELAKI']);
            }

            unset($validated['single_package_side']);
        } elseif (isset($validated['single_package_side'])) {
            $selectedSide = $validated['single_package_side'];
            $currentPackageSide = $order->packageSides->first();

            if ($currentPackageSide?->side !== $selectedSide) {
                if ($order->packageSides->count() !== 1 || $order->status !== 'DETAILS_INCOMPLETE') {
                    throw ValidationException::withMessages([
                        'single_package_side' => 'The selected side does not match this order.',
                    ]);
                }

                $currentPackageSide->update(['side' => $selectedSide]);
            }

            unset($validated['single_package_side']);
        }

        $saveDraft->save($order, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('orders.dashboard', ['orderId' => $order->order_id])
            ->with('draft_saved', true);
    }
}
