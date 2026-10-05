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

        return back()->with('status', "Tempahan {$order->order_id} telah dibatalkan dan rekod dikekalkan.");
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

        return back()->with('status', "Tempahan {$order->order_id} telah diarkibkan dan rekod dikekalkan.");
    }

    /** @return array{reason: string} */
    private function validateReason(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Sila nyatakan sebab tindakan ini.',
            'reason.max' => 'Sebab tindakan tidak boleh melebihi 1000 aksara.',
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
