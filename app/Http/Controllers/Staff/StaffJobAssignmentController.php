<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\PackingJob;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Design\AssignDesignJobService;
use App\Services\Packing\AssignPackingJobService;
use App\Services\Printing\AssignPrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffJobAssignmentController extends Controller
{
    public function assignDesign(
        Request $request,
        DesignJob $designJob,
        AssignDesignJobService $service
    ): RedirectResponse {
        $assignee = $this->validatedAssignee(
            $request,
            User::ROLE_DESIGNER
        );

        $service->assign(
            $designJob,
            $assignee,
            $request->user()
        );

        return back()->with('status', 'Design job assigned successfully.');
    }

    public function assignPrinting(
        Request $request,
        PrintJob $printJob,
        AssignPrintJobService $service
    ): RedirectResponse {
        $assignee = $this->validatedAssignee(
            $request,
            User::ROLE_PRINTING
        );

        $service->assign(
            $printJob,
            $assignee,
            $request->user()
        );

        return back()->with('status', 'Print job assigned successfully.');
    }

    public function assignPacking(
        Request $request,
        PackingJob $packingJob,
        AssignPackingJobService $service
    ): RedirectResponse {
        $assignee = $this->validatedAssignee(
        $request,
        [
            User::ROLE_PACKING,
            User::ROLE_OM,
        ]
    );

        $service->assign(
            $packingJob,
            $assignee,
            $request->user()
        );

        return back()->with('status', 'Packing job assigned successfully.');
    }

    private function validatedAssignee(
        Request $request,
        string|array $roles
    ): User {
        $roles = (array) $roles;

        $validated = $request->validate([
            'assigned_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->whereIn('role', $roles)
                        ->where('is_active', true)
                ),
            ],
        ]);

        return User::query()->findOrFail(
            $validated['assigned_user_id']
        );
    }
}