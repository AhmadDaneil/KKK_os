<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\PackingJob;
use App\Models\PaymentTransaction;
use App\Models\PrintJob;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $attention = [];

        if ($user->isOperationManagement()) {
            $attention = [
                'pending_payments' => PaymentTransaction::where('status', 'PENDING')->count(),
                'unassigned_design' => DesignJob::whereNull('assigned_user_id')->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])->count(),
                'unassigned_printing' => PrintJob::whereNull('assigned_user_id')->where('status', 'READY_FOR_PRINT')->count(),
                'unassigned_packing' => PackingJob::whereNull('assigned_user_id')->where('status', 'READY_FOR_PACKING')->count(),
            ];
        }

        return view('staff.dashboard', compact('attention'));
    }
}
