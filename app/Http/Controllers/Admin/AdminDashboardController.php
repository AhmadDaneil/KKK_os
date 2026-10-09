<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Models\PackingJob;
use App\Models\PaymentTransaction;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Admin\BuildSalesAnalysisService;
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
        $order = Order::query()
            ->where('order_id', $orderId)
            ->where('status', '!=', 'DETAILS_INCOMPLETE')
            ->first();

        if (! $order) {
            return back()
                ->withErrors(['order_id' => 'Order ID tidak dijumpai.'])
                ->withInput();
        }

        return redirect()->route('admin.orders.show', $order->order_id);
    }

    public function index(BuildSalesAnalysisService $salesAnalysis): View
    {
        $statistics = [
            'orders_total' => Order::where('status', '!=', 'DETAILS_INCOMPLETE')->count(),
            'orders_active' => Order::where('status', '!=', 'DETAILS_INCOMPLETE')
                ->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES)
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
            'design' => DesignJob::whereIn('status', ['READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS', 'CORRECTION_REQUESTED'])->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            'printing' => PrintJob::whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            'packing' => PackingJob::whereIn('status', ['READY_FOR_PACKING', 'PACKING'])->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
        ];

        $attention = [
            'pending_payments' => PaymentTransaction::where('status', 'PENDING')->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            'design_queue' => DesignJob::whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            'printing_queue' => PrintJob::where('status', 'READY_FOR_PRINT')->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            'unassigned_packing' => PackingJob::whereNull('assigned_user_id')
                ->whereIn('status', ['READY_FOR_PACKING'])
                ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                ->count(),
        ];

        $teamCounts = User::query()
            ->whereIn('role', User::STAFF_ROLES)
            ->where('is_active', true)
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $recentOrders = Order::query()
            ->where('status', '!=', 'DETAILS_INCOMPLETE')
            ->latest()
            ->limit(8)
            ->get();

        $sales = $salesAnalysis->build();

        return view('admin.dashboard', compact('statistics', 'queues', 'attention', 'teamCounts', 'recentOrders', 'sales'));
    }
}
