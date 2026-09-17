<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffOrderController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $this->visibleOrdersFor($user)
            ->with([
                'designJobs.assignedUser',
                'printJobs.assignedUser',
                'packingJob.assignedUser',
            ])
            ->latest('id')
            ->paginate(25);

        return view('staff.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, string $orderId): View
    {
        /** @var User $user */
        $user = $request->user();

        $order = $this->visibleOrdersFor($user)
            ->where('order_id', $orderId)
            ->with([
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'fulfilment',
                'payments',
                'fulfilmentJob',
            ])
            ->firstOrFail();

        $order->unsetRelation('designJobs');
        $order->unsetRelation('printJobs');
        $order->unsetRelation('packingJob');

        if ($user->isAdmin()) {
            $order->load([
                'designJobs.assignedUser',
                'designJobs.artworkVersions',
                'printJobs.assignedUser',
                'packingJob.assignedUser',
                'packingJob.items',
            ]);
        } elseif ($user->hasStaffRole(User::ROLE_DESIGNER)) {
            $order->load([
                'designJobs' => fn ($query) => $query
                    ->where('assigned_user_id', $user->id)
                    ->with([
                        'assignedUser',
                        'artworkVersions',
                    ]),
            ]);
        } elseif ($user->hasStaffRole(User::ROLE_PRINTING)) {
            $order->load([
                'printJobs' => fn ($query) => $query
                    ->where('assigned_user_id', $user->id)
                    ->with('assignedUser'),
            ]);
        } elseif ($user->hasStaffRole(User::ROLE_PACKING)) {
            $order->load([
                'packingJob' => fn ($query) => $query
                    ->where('assigned_user_id', $user->id)
                    ->with([
                        'assignedUser',
                        'items',
                    ]),
            ]);
        }

        $assignmentOptions = [
    'designers' => collect(),
    'printingStaff' => collect(),
    'packingStaff' => collect(),
];

if ($user->isAdmin()) {
    $assignmentOptions = [
        'designers' => User::query()
            ->where('role', User::ROLE_DESIGNER)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']),

        'printingStaff' => User::query()
            ->where('role', User::ROLE_PRINTING)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']),

        'packingStaff' => User::query()
            ->where('role', User::ROLE_PACKING)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']),
    ];
}
        
        return view('staff.orders.show', [
    'order' => $order,
    ...$assignmentOptions,
]);
    }

    private function visibleOrdersFor(User $user): Builder
    {
        $query = Order::query();

        if ($user->isAdmin()) {
            return $query;
        }

        return match ($user->role) {
            User::ROLE_DESIGNER => $query->whereHas(
                'designJobs',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('assigned_user_id', $user->id)
            ),

            User::ROLE_PRINTING => $query->whereHas(
                'printJobs',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('assigned_user_id', $user->id)
            ),

            User::ROLE_PACKING => $query->whereHas(
                'packingJob',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('assigned_user_id', $user->id)
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}