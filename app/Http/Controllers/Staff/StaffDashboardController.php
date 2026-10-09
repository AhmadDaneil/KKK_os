<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\FulfilmentJob;
use App\Models\Order;
use App\Models\PackingJob;
use App\Models\PaymentTransaction;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $attention = [];
        $designerAttention = 0;
        $productionAttention = ['ready' => 0, 'printing' => 0];
        $packingAttention = ['ready' => 0, 'packing' => 0, 'fulfilment' => 0];

        if ($user->canMonitorAllDepartments()) {
            $attention = [
                'pending_payments' => PaymentTransaction::where('status', 'PENDING')->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
                'design_queue' => DesignJob::whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
                'printing_queue' => PrintJob::where('status', 'READY_FOR_PRINT')->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
                'unassigned_packing' => PackingJob::whereNull('assigned_user_id')->where('status', 'READY_FOR_PACKING')->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))->count(),
            ];
        }

        if ($user->hasStaffRole(User::ROLE_DESIGNER)) {
            $designerAttention = DesignJob::where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])
                ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                ->count();
        }

        if ($user->hasStaffRole(User::ROLE_PRODUCTION)) {
            $productionJobs = PrintJob::where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])
                ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                ->select('status')
                ->get();
            $productionAttention = [
                'ready' => $productionJobs->where('status', 'READY_FOR_PRINT')->count(),
                'printing' => $productionJobs->where('status', 'PRINTING')->count(),
            ];
        }

        if ($user->canMonitorAllDepartments()) {
            $packingJobs = PackingJob::whereIn('status', ['READY_FOR_PACKING', 'PACKING'])
                ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                ->select('status')
                ->get();
            $packingAttention = [
                'ready' => $packingJobs->where('status', 'READY_FOR_PACKING')->count(),
                'packing' => $packingJobs->where('status', 'PACKING')->count(),
                'fulfilment' => FulfilmentJob::whereIn('status', ['READY', 'IN_TRANSIT'])
                    ->whereHas('order', fn ($query) => $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                    ->count(),
            ];
        }

        return view('staff.dashboard', compact('attention', 'designerAttention', 'productionAttention', 'packingAttention'));
    }
}
