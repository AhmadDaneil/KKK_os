<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccessLog;
use App\Models\Order;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\NormalizeCustomerPhoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class CustomerDashboardAccessController extends Controller
{
    public function create(Request $request): View
    {
        $orderId = strtoupper(trim((string) $request->query('order_id', '')));

        return view('orders.access', [
            'prefilledOrderId' => preg_match('/^KKK-\d{6}-\d{4}$/', $orderId) === 1
                ? $orderId
                : null,
        ]);
    }

    public function store(
        Request $request,
        NormalizeCustomerPhoneService $phones,
        CustomerOrderSessionAccessService $access,
    ): RedirectResponse {
        $request->merge([
            'customer_reference' => strtoupper(trim((string) $request->input('customer_reference'))),
        ]);
        $validated = $request->validate([
            'customer_reference' => [
                'required',
                'string',
                'max:32',
                'regex:/^(?:KKK-\d{6}-\d{4}|[A-Z]{2,3}\d{6}-\d{3})$/',
            ],
            'customer_phone' => ['required', 'string', 'max:32'],
        ], [
            'customer_reference.required' => 'Sila masukkan ID Tempahan atau Rujukan Pelanggan.',
            'customer_reference.regex' => 'Format ID Tempahan atau Rujukan Pelanggan tidak sah.',
            'customer_phone.required' => 'Sila masukkan nombor telefon yang digunakan semasa tempahan.',
        ]);
        $identifier = $validated['customer_reference'];
        $key = 'customer-dashboard:'.hash('sha256', $identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->audit($request, null, $identifier, null, 'LOCKED');

            return back()->withErrors([
                'customer_reference' => 'Too many attempts. Please try again after 15 minutes.',
            ]);
        }

        $phone = $phones->normalize($validated['customer_phone']);
        $order = Order::query()
            ->where(function ($query) use ($identifier): void {
                $query->where('customer_reference', $identifier)
                    ->orWhere('order_id', $identifier);
            })
            ->first();
        $matches = $order !== null
            && filled($order->customer_phone)
            && hash_equals($phone, $phones->normalize((string) $order->customer_phone));

        if (! $matches) {
            RateLimiter::hit($key, 900);
            $this->audit($request, $order, $identifier, $phone, 'FAILED');

            return back()->withInput(['customer_reference' => $reference])->withErrors([
                'customer_reference' => 'The customer reference and phone number do not match.',
            ]);
        }

        RateLimiter::clear($key);
        $this->audit($request, $order, $identifier, $phone, 'SUCCESS');
        $access->establishFromVerifiedOrder($request, $order);

        return redirect()->route('orders.dashboard', ['orderId' => $order->order_id]);
    }

    public function destroy(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $access,
    ): RedirectResponse {
        $order = $access->resolve($request, $orderId);
        $access->revoke($request, $order);

        return redirect()->route('orders.access.create');
    }

    private function audit(
        Request $request,
        ?Order $order,
        string $identifier,
        ?string $phone,
        string $result,
    ): void {
        CustomerAccessLog::query()->create([
            'order_id' => $order?->id,
            'attempted_reference' => $identifier,
            'phone_fingerprint' => $phone === null ? null : hash('sha256', $phone),
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'occurred_at' => now(),
        ]);
    }
}
