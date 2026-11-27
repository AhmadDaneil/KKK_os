<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\AddStaffOrderAddOnService;
use App\Services\Orders\OrderAddOnCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffOrderAddOnController extends Controller
{
    public function store(Request $request, Order $order, OrderAddOnCatalogService $catalog, AddStaffOrderAddOnService $addOns): RedirectResponse
    {
        abort_unless($request->user()->isOperationManagement() || $request->user()->isCustomerService(), 403);
        $validated = $request->validate([
            'code' => ['required', Rule::in($catalog->codes())],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:80'],
            'request_id' => ['required', 'uuid'],
        ]);
        $option = $catalog->find($validated['code']);
        $request->validate(['quantity' => ['integer', 'min:'.$option['min_quantity']]], [
            'quantity.min' => 'The minimum quantity for this item is '.$option['min_quantity'].'.',
        ]);
        $addOns->add($order, $request->user(), $validated['code'], (int) $validated['quantity'], $validated['notes'] ?? null, $validated['request_id']);

        return back()->with('status', 'Add-On added to the order.');
    }

    public function destroy(Request $request, Order $order, OrderItem $orderItem, AddStaffOrderAddOnService $addOns): RedirectResponse
    {
        abort_unless($request->user()->isOperationManagement() || $request->user()->isCustomerService(), 403);
        $addOns->remove($order, $orderItem, $request->user());

        return back()->with('status', 'Add-On removed. The invoice and remaining balance have been updated.');
    }
}
