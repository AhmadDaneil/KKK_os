<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StaffOrderLifecycleController extends Controller
{
    public function cancel(
        Request $request,
        Order $order,
        OrderLifecycleService $service,
    ): RedirectResponse {
        $this->authorizeOperationManagement($request);
        $validated = $this->validateReason($request);

        try {
            $service->cancel(
                $order,
                $validated['reason'],
                $request->user(),
                $this->source($request),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'order_lifecycle' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', "Order {$order->order_id} cancelled. Its record has been retained.");
    }

    public function archive(
        Request $request,
        Order $order,
        OrderLifecycleService $service,
    ): RedirectResponse {
        $this->authorizeOperationManagement($request);
        $validated = $this->validateReason($request);

        try {
            $service->archive(
                $order,
                $validated['reason'],
                $request->user(),
                $this->source($request),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'order_lifecycle' => $exception->getMessage(),
            ]);
        }

        return back()->with('status', "Order {$order->order_id} archived. Its record has been retained.");
    }

    /** @return array{reason: string} */
    private function validateReason(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Please provide a reason for this action.',
            'reason.max' => 'The reason must not exceed 1,000 characters.',
        ]);
    }

    private function authorizeOperationManagement(Request $request): void
    {
        abort_unless($request->user()?->isOperationManagement(), 403);
    }

    private function source(Request $request): string
    {
        return $request->routeIs('admin.*') ? 'ADMIN' : 'STAFF';
    }
}
