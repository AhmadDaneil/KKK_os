<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\DeleteIncompleteOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StaffOrderDeletionController extends Controller
{
    public function destroy(
        Request $request,
        string $publicOrderId,
        DeleteIncompleteOrderService $service
    ): RedirectResponse {
        abort_unless($request->user()?->isOperationManagement(), 403);

        $order = Order::query()->where('order_id', $publicOrderId)->firstOrFail();

        $orderId = $order->order_id;

        try {
            $service->delete($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'order_delete' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', "Order {$orderId} berjaya dipadam.");
    }
}
