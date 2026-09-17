<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GenerateOrderAccessLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function create(): View
    {
        return view('public.start-order');
    }

    public function store(
        Request $request,
        CreateOrderService $createOrder,
        GenerateOrderAccessLinkService $accessLink,
    ): RedirectResponse {
        $validated = $request->validate([
            'package_count' => ['required', 'integer', 'in:1,2'],
            'side' => ['nullable', 'string', 'in:LELAKI,PEREMPUAN', 'required_if:package_count,1'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
        ]);

        $order = $createOrder->create($validated);

        return redirect()->away($accessLink->generate($order));
    }

    public function progress(): View
    {
        return view('public.order-progress');
    }

    public function lookupProgress(
        Request $request,
        BuildCustomerProgressService $customerProgress,
    ): View|RedirectResponse {
        $request->merge([
            'order_id' => strtoupper(trim((string) $request->input('order_id'))),
        ]);

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:32', 'regex:/^KKK-[A-Za-z0-9-]+$/'],
        ], [
            'order_id.required' => 'Sila masukkan Order ID anda.',
            'order_id.regex' => 'Format Order ID tidak sah.',
        ]);

        $orderId = $validated['order_id'];
        $order = Order::query()->where('order_id', $orderId)->first();

        if (! $order) {
            return back()
                ->withInput()
                ->withErrors(['order_id' => 'Order ID tidak ditemui. Sila semak dan cuba lagi.']);
        }

        return view('public.order-progress', [
            'orderId' => $order->order_id,
            'progress' => $customerProgress->build($order),
        ]);
    }
}
