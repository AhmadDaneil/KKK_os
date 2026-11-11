<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\NormalizeCustomerPhoneService;
use App\Services\Payments\CreateBillplzBookingPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function landing(Request $request, CustomerOrderSessionAccessService $sessionAccess): Response
    {
        $activeDraft = $sessionAccess->unfinishedDraft($request);

        if ($activeDraft && ! $sessionAccess->hasAccess($request, $activeDraft)) {
            $activeDraft = null;
        }

        return response()->view('public.landing', [
            'activeDraft' => $activeDraft,
            'canTestOrder' => $this->sandboxCheckoutEnabled(),
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function testOrder(): Response
    {
        abort_unless($this->sandboxCheckoutEnabled(), 404);

        return response()->view('public.test-order')
            ->header('Cache-Control', 'private, no-store');
    }

    public function storeTestOrder(
        Request $request,
        CreateOrderService $createOrder,
        NormalizeCustomerPhoneService $phones,
        CreateBillplzBookingPaymentService $bookingPayment,
        CustomerOrderSessionAccessService $sessionAccess,
    ): RedirectResponse {
        abort_unless($this->sandboxCheckoutEnabled(), 404);

        $validated = $request->validate([
            'package_count' => ['required', 'integer', 'in:1,2'],
            'side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,1'],
            'package_type' => ['required', 'string', 'in:PUTERI_MANIS,JELATA'],
            'payment_plan' => ['required', 'in:DEPOSIT,FULL'],
            'package_format' => ['nullable', 'string', 'in:SEPARATE,FOLDED', 'required_if:package_count,2'],
            'first_event_side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,2'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^01\\d{8,9}$/'],
        ]);

        $validated['customer_phone'] = $phones->normalize($validated['customer_phone']);
        $validated['source'] = 'TEST';

        $order = $createOrder->create($validated);

        if ($validated['payment_plan'] === 'FULL') {
            $sessionAccess->establishForTestOrderCompletion($request, $order);

            return redirect()->route('orders.dashboard', [
                'orderId' => $order->order_id,
            ]);
        }

        $checkout = $bookingPayment->create($order);

        return redirect()->away($checkout['payment_url']);
    }

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
            'order_id' => ['required', 'string', 'max:32', 'regex:/^(?:KKK-\d{6}-\d{4}|[A-Z]{2,3}\d{6}-\d{3})$/'],
        ], [
            'order_id.required' => 'Please enter your order ID.',
            'order_id.regex' => 'Invalid order ID format.',
        ]);

        $orderId = $validated['order_id'];
        $referenceCandidates = Order::customerReferenceLookupCandidates($orderId);
        $order = Order::query()
            ->where('order_id', $orderId)
            ->orWhereIn('customer_reference', $referenceCandidates)
            ->first();

        if (! $order || ($order->source === 'WABOT' && $order->booking_payment_status !== 'PAID')) {
            return back()
                ->withInput()
                ->withErrors(['order_id' => 'Order ID or customer reference not found. Please check it and try again.']);
        }

        $sessionAccess->establishFromProgressLookup($request, $order);

        $order->load(['fulfilmentJob', 'payments', 'designJobs.artworkVersions', 'pricingSnapshot']);
        $deposit = $order->payments->firstWhere('payment_type', 'BOOKING_DEPOSIT');
        $balance = $order->payments->where('payment_type', 'BALANCE')->sortByDesc('id')->first();
        $progressStatus = $customerProgress->resolveStatus($order);

        return view('public.order-progress', [
            'orderId' => $order->order_id,
            'customerReference' => $order->displayCustomerReference(),
            'orderStatus' => $progressStatus,
            'paymentPlan' => strtoupper((string) data_get($order->pricingSnapshot?->calculation_snapshot, 'payment_plan', $order->payment_plan ?? 'DEPOSIT')),
            'progress' => $customerProgress->build($order, $progressStatus),
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
                'payment_method' => data_get($balance->metadata, 'billplz.payment_method', 'BILLPLZ'),
                'checkout_url' => $sessionAccess->hasAccess($request, $order)
                    && $balance->status === 'PENDING'
                    && $balance->provider === 'BILLPLZ'
                    ? ($balance->metadata['billplz']['checkout_url'] ?? null)
                    : null,
            ] : null,
            'balanceAmount' => (string) ($order->pricingSnapshot?->outstanding_amount ?? config('kingkadkahwin.balance.amount', '0.00')),
            'balanceBillplzAmount' => $order->pricingSnapshot?->outstanding_amount,
            'balanceQrImage' => (string) config('kingkadkahwin.balance.qr_image'),
        ]);
    }

    private function sandboxCheckoutEnabled(): bool
    {
        return (bool) config('services.billplz.enabled')
            && parse_url((string) config('services.billplz.base_url', ''), PHP_URL_HOST) === 'www.billplz-sandbox.com';
    }
}

