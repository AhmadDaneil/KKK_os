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
        Order $order,
        DeleteIncompleteOrderService $service
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);

        $orderId = $order->order_id;

        try {
            $service->delete($order);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'order_delete' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', "Order {$orderId} deleted successfully.");
    }
}
