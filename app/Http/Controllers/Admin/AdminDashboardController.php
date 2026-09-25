<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Models\PackingJob;
use App\Models\PaymentTransaction;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function findOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:32'],
        ], [
            'order_id.required' => 'Sila masukkan Order ID.',
            'order_id.max' => 'Order ID tidak boleh melebihi 32 aksara.',
        ]);

        $orderId = strtoupper(trim($validated['order_id']));
        $order = Order::query()->where('order_id', $orderId)->first();

        if (! $order) {
            return back()
                ->withErrors(['order_id' => 'Order ID tidak dijumpai.'])
                ->withInput();
        }

        return redirect()->route('admin.orders.show', $order->order_id);
    }

    public function index(): View
    {
        $statistics = [
            'orders_total' => Order::count(),
            'orders_active' => Order::whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES)
                ->whereNotIn('status', ['COMPLETED'])
                ->count(),
            'pending_deposits' => PaymentTransaction::where('payment_type', 'BOOKING_DEPOSIT')
                ->where('status', 'PENDING')
                ->count(),
            'pending_balances' => PaymentTransaction::where('payment_type', 'BALANCE')
                ->where('status', 'PENDING')
                ->count(),
            'orders_completed' => Order::where('status', 'COMPLETED')->count(),
        ];

        $queues = [
            'design' => DesignJob::whereIn('status', ['READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS', 'CORRECTION_REQUESTED'])->count(),
            'printing' => PrintJob::whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])->count(),
            'packing' => PackingJob::whereIn('status', ['READY_FOR_PACKING', 'PACKING'])->count(),
        ];

        $attention = [
            'pending_payments' => PaymentTransaction::where('status', 'PENDING')->count(),
            'unassigned_design' => DesignJob::whereNull('assigned_user_id')
                ->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])
                ->count(),
            'unassigned_printing' => PrintJob::whereNull('assigned_user_id')
                ->whereIn('status', ['READY_FOR_PRINT'])
                ->count(),
            'unassigned_packing' => PackingJob::whereNull('assigned_user_id')
                ->whereIn('status', ['READY_FOR_PACKING'])
                ->count(),
        ];

        $teamCounts = User::query()
            ->whereIn('role', User::STAFF_ROLES)
            ->where('is_active', true)
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $recentOrders = Order::query()->latest()->limit(8)->get();

        return view('admin.dashboard', compact('statistics', 'queues', 'attention', 'teamCounts', 'recentOrders'));
    }
}
