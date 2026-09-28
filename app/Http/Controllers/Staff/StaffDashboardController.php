<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\FulfilmentJob;
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

        if ($user->isOperationManagement()) {
            $attention = [
                'pending_payments' => PaymentTransaction::where('status', 'PENDING')->count(),
                'unassigned_design' => DesignJob::whereNull('assigned_user_id')->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])->count(),
                'unassigned_printing' => PrintJob::whereNull('assigned_user_id')->where('status', 'READY_FOR_PRINT')->count(),
                'unassigned_packing' => PackingJob::whereNull('assigned_user_id')->where('status', 'READY_FOR_PACKING')->count(),
            ];
        }

        if ($user->hasStaffRole(User::ROLE_DESIGNER)) {
            $designerAttention = DesignJob::where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])
                ->count();
        }

        if ($user->hasStaffRole(User::ROLE_PRODUCTION)) {
            $productionJobs = PrintJob::where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])
                ->select('status')
                ->get();
            $productionAttention = [
                'ready' => $productionJobs->where('status', 'READY_FOR_PRINT')->count(),
                'printing' => $productionJobs->where('status', 'PRINTING')->count(),
            ];
        }

        if ($user->isOperationManagement()) {
            $packingJobs = PackingJob::where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_PACKING', 'PACKING'])
                ->select('status')
                ->get();
            $packingAttention = [
                'ready' => $packingJobs->where('status', 'READY_FOR_PACKING')->count(),
                'packing' => $packingJobs->where('status', 'PACKING')->count(),
                'fulfilment' => FulfilmentJob::where('status', 'READY')
                    ->whereHas('packingJob', fn ($query) => $query->where('assigned_user_id', $user->id))
                    ->count(),
            ];
        }

        return view('staff.dashboard', compact('attention', 'designerAttention', 'productionAttention', 'packingAttention'));
    }
}
