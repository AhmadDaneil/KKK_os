<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\AssignOrderProductionStaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffOrderProductionAssignmentController extends Controller
{
    public function assignPrinting(
        Request $request,
        Order $order,
        AssignOrderProductionStaffService $service
    ): RedirectResponse {
        $this->authorizeManager($request);
        $this->ensureDesignIsApproved($order);
        $assignee = $this->validatedAssignee($request, User::ROLE_PRINTING);

        $service->assignPrinting($order, $assignee, $request->user());

        return back()->with('status', 'Printing staff assigned successfully.');
    }

    public function assignPackingAndFulfilment(
        Request $request,
        Order $order,
        AssignOrderProductionStaffService $service
    ): RedirectResponse {
        $this->authorizeManager($request);
        $this->ensureDesignIsApproved($order);
        $assignee = $this->validatedAssignee($request, User::ROLE_PACKING);

        $service->assignPackingAndFulfilment($order, $assignee, $request->user());

        return back()->with('status', 'Packing and fulfilment staff assigned successfully.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->isOperationManagement(), 403);
    }

    private function ensureDesignIsApproved(Order $order): void
    {
        $statuses = $order->designJobs()->pluck('status');

        abort_unless(
            $statuses->isNotEmpty()
            && $statuses->every(fn (string $status): bool => $status === 'DESIGN_APPROVED'),
            422,
            'Production staff can only be assigned after every artwork is approved.'
        );
    }

    private function validatedAssignee(Request $request, string $role): User
    {
        $validated = $request->validate([
            'assigned_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('role', $role)
                        ->where('is_active', true)
                ),
            ],
        ]);

        return User::query()->findOrFail($validated['assigned_user_id']);
    }
}
