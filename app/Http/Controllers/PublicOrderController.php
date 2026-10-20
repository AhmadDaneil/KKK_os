<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        $channels = config('kingkadkahwin.booking.whatsapp_channels');
        $channel = $channels[$request->route('brand', 'kingkadkahwin')] ?? null;
        abort_if($channel === null, 404);

        $number = $channel['number'];
        $brand = $channel['name'];
        $orderId = strtoupper(trim((string) $request->query('order_id', '')));
        $isSupport = $request->query('purpose') === 'support';
        $message = $isSupport
            ? (preg_match('/^KKK-\d{6}-\d{4}$/', $orderId) === 1
                ? "Hello {$brand}, I need help with order {$orderId}."
                : "Hello {$brand}, I would like help with a wedding card order.")
            : "Hello {$brand}, I would like to book a wedding card package.";

        return redirect()->away('https://wa.me/'.$number.'?text='.rawurlencode($message));
    }

    public function progress(
        Request $request,
        BuildCustomerProgressService $customerProgress,
        CustomerOrderSessionAccessService $sessionAccess,
    ): View|RedirectResponse {
        if ($request->filled('order_id')) {
            return $this->lookupProgress($request, $customerProgress, $sessionAccess);
        }

        return view('public.order-progress');
    }

    public function lookupProgress(
        Request $request,
        BuildCustomerProgressService $customerProgress,
        CustomerOrderSessionAccessService $sessionAccess,
    ): View|RedirectResponse {
        $request->merge([
            'order_id' => strtoupper(trim((string) $request->input('order_id'))),
        ]);

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:32', 'regex:/^KKK-[A-Za-z0-9-]+$/'],
        ], [
            'order_id.required' => 'Please enter your order ID.',
            'order_id.regex' => 'Invalid order ID format.',
        ]);

        $orderId = $validated['order_id'];
        $order = Order::query()->where('order_id', $orderId)->first();

        if (! $order || ($order->source === 'WABOT' && $order->booking_payment_status !== 'PAID')) {
            return back()
                ->withInput()
                ->withErrors(['order_id' => 'Order ID not found. Please check it and try again.']);
        }

        $sessionAccess->establishFromProgressLookup($request, $order);

        $order->load(['fulfilmentJob', 'payments', 'designJobs.artworkVersions', 'pricingSnapshot']);
        $deposit = $order->payments->firstWhere('payment_type', 'BOOKING_DEPOSIT');
        $balance = $order->payments->where('payment_type', 'BALANCE')->sortByDesc('id')->first();
        $artworkReady = in_array($order->status, [
            'DESIGN_READY',
            'CORRECTION_REQUESTED',
            'DESIGN_APPROVED',
            'BALANCE_PENDING',
            'PAID',
            'READY_FOR_PRINT',
            'PRINTING',
            'PRINTED',
            'READY_FOR_PACKING',
            'PACKING',
            'PACKED',
            'READY_FOR_FULFILMENT',
            'COMPLETED',
        ], true);

        return view('public.order-progress', [
            'orderId' => $order->order_id,
            'orderStatus' => $order->status,
            'progress' => $customerProgress->build($order),
            'shipment' => $order->fulfilmentJob && $order->fulfilmentJob->method === 'COURIER'
                ? [
                    'courier_provider' => $order->fulfilmentJob->courier_provider,
                    'tracking_number' => $order->fulfilmentJob->tracking_number,
                    'shipped_at' => $order->fulfilmentJob->shipped_at,
                ]
                : null,
            'deposit' => $deposit ? [
                'status' => $deposit->status,
                'reason' => $deposit->metadata['rejection_reason'] ?? null,
            ] : null,
            'balance' => $balance ? [
                'status' => $balance->status,
                'amount' => $balance->amount,
                'reason' => $balance->metadata['rejection_reason'] ?? null,
                'provider' => $balance->provider,
                'checkout_url' => $sessionAccess->hasAccess($request, $order)
                    && $balance->status === 'PENDING'
                    && $balance->provider === 'BILLPLZ'
                    ? ($balance->metadata['billplz']['checkout_url'] ?? null)
                    : null,
            ] : null,
            'balanceAmount' => (string) ($order->pricingSnapshot?->outstanding_amount ?? config('kingkadkahwin.balance.amount', '0.00')),
            'balanceBillplzAmount' => $order->pricingSnapshot?->outstanding_amount,
            'balanceQrImage' => (string) config('kingkadkahwin.balance.qr_image'),
            'artworkReady' => $artworkReady,
        ]);
    }

    public function proofing(
        Request $request,
        string $orderId,
        BuildCustomerProgressService $customerProgress,
        CustomerOrderSessionAccessService $sessionAccess,
    ): View {
        $order = $sessionAccess->resolveProofing($request, $orderId);
        $order->load([
            'designJobs.artworkVersions',
            'progressUpdates' => fn ($query) => $query
                ->customerVisible()
                ->with('uploader:id,name')
                ->latest(),
        ]);

        return view('public.order-proofing', [
            'order' => $order,
            'progress' => $customerProgress->build($order),
        ]);
    }
}
