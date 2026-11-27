<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\DesignJob;
use App\Models\Order;
use App\Models\PackingJob;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Design\AssignDesignJobService;
use App\Services\Packing\AssignPackingJobService;
use App\Services\Printing\AssignPrintJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffJobAssignmentController extends Controller
{
    public function assignDesign(
        Request $request,
        DesignJob $designJob,
        AssignDesignJobService $service
    ): RedirectResponse|JsonResponse {
        $this->ensureOrderIsAssignable($designJob->order);
        $assignee = $this->validatedAssignee(
            $request,
            User::ROLE_DESIGNER
        );

        $service->assign(
            $designJob,
            $assignee,
            $request->user()
        );

        return $this->assignmentResponse($request, 'Design job assigned successfully.', $assignee);
    }

    public function assignPrinting(
        Request $request,
        PrintJob $printJob,
        AssignPrintJobService $service
    ): RedirectResponse|JsonResponse {
        $this->ensureOrderIsAssignable($printJob->order);
        $assignee = $this->validatedAssignee(
            $request,
            User::ROLE_PRODUCTION
        );

        $service->assign(
            $printJob,
            $assignee,
            $request->user()
        );

        return $this->assignmentResponse($request, 'Production job assigned successfully.', $assignee);
    }

    public function assignPacking(
        Request $request,
        PackingJob $packingJob,
        AssignPackingJobService $service
    ): RedirectResponse|JsonResponse {
        $this->ensureOrderIsAssignable($packingJob->order);
        $assignee = $this->validatedAssignee($request, User::ROLE_OM);

        $service->assign(
            $packingJob,
            $assignee,
            $request->user()
        );

        return $this->assignmentResponse($request, 'Packing job assigned successfully.', $assignee);
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

    private function ensureOrderIsAssignable(?Order $order): void
    {
        abort_if(
            $order?->isAssignmentLocked(),
            422,
            'Completed or closed orders cannot be reassigned.'
        );
    }

    private function assignmentResponse(Request $request, string $message, User $assignee): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'assignee' => ['id' => $assignee->id, 'name' => $assignee->name],
            ]);
        }

        return back()->with('status', $message);
    }
}
