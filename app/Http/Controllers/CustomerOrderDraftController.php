<?php

namespace App\Http\Controllers;

use App\Services\Orders\ResolveOrderAccessService;
use App\Services\Orders\SaveOrderDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOrderDraftController extends Controller
{
    public function update(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $access,
        SaveOrderDraftService $saveDraft,
    ): RedirectResponse {
        $plainToken = (string) $request->query('token', '');
        $order = $access->resolve($orderId, $plainToken);

        $validated = $request->validate([
            'couple' => ['sometimes', 'array'],
            'couple.groom_name' => ['nullable', 'string', 'max:255'],
            'couple.groom_abbreviation' => ['nullable', 'string', 'max:100'],
            'couple.bride_name' => ['nullable', 'string', 'max:255'],
            'couple.bride_abbreviation' => ['nullable', 'string', 'max:100'],

            'sides' => ['sometimes', 'array'],

            'sides.LELAKI.design.theme' => ['nullable', 'string', 'max:255'],
            'sides.LELAKI.design.design_code' => ['nullable', 'string', 'max:100'],
            'sides.LELAKI.design.card_title' => ['nullable', 'string', 'max:255'],
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
            'sides.LELAKI.event.contacts.*.contact_phone' => ['nullable', 'string', 'max:50'],

            'sides.PEREMPUAN.design.theme' => ['nullable', 'string', 'max:255'],
            'sides.PEREMPUAN.design.design_code' => ['nullable', 'string', 'max:100'],
            'sides.PEREMPUAN.design.card_title' => ['nullable', 'string', 'max:255'],
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
            'sides.PEREMPUAN.event.contacts.*.contact_phone' => ['nullable', 'string', 'max:50'],

            'fulfilment' => ['sometimes', 'array'],
            'fulfilment.method' => ['nullable', 'in:COURIER,PICKUP'],
            'fulfilment.recipient_name' => ['nullable', 'string', 'max:255'],
            'fulfilment.recipient_phone' => ['nullable', 'string', 'max:50'],
            'fulfilment.shipping_address' => ['nullable', 'string', 'max:2000'],
        ]);

        $saveDraft->save($order, $validated);

        return redirect()
            ->route('orders.dashboard', [
                'orderId' => $order->order_id,
                'token' => $plainToken,
            ])
            ->with('draft_saved', true);
    }
}
