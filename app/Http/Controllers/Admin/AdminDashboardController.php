<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Models\PackingJob;
use App\Models\PaymentTransaction;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
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
