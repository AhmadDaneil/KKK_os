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

        $workstream = $this->allowedWorkstream($user, $request->string('workstream')->toString());

        $query = $this->visibleOrdersFor($user);
        $this->applyWorkstream($query, $workstream);
        $this->applyFilters($query, $request, $user);

        $orders = $query
            ->with([
                'designJobs.assignedUser',
                'printJobs.assignedUser',
                'packingJob.assignedUser',
                'fulfilmentJob',
            ])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('staff.orders.index', [
            'orders' => $orders,
            'workstream' => $workstream,
            'statusOptions' => Order::query()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
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
                'printingAssignedUser',
                'packingAssignedUser',
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
        } elseif ($user->hasStaffRole(User::ROLE_OM)) {
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

        if ($user->isOperationManagement() && ! $request->attributes->get('staff_overview_mode', false)) {
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
            'canAssignProduction' => $order->designJobs()->exists()
                && $order->designJobs()
                    ->where('status', '!=', 'DESIGN_APPROVED')
                    ->doesntExist(),
            ...$assignmentOptions,
        ]);
    }

    private function visibleOrdersFor(User $user): Builder
    {
        $query = Order::query();

        if ($user->isOperationManagement()) {
            return $query;
        }

        return match ($user->role) {
            User::ROLE_DESIGNER => $query->whereHas(
                'designJobs',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('assigned_user_id', $user->id)
            ),

            User::ROLE_PRINTING => $query->where(
                fn (Builder $printingQuery) => $printingQuery
                    ->where('printing_assigned_user_id', $user->id)
                    ->orWhereHas(
                        'printJobs',
                        fn (Builder $jobQuery) => $jobQuery
                            ->where('assigned_user_id', $user->id)
                    )
            ),

            User::ROLE_PACKING => $query->where(
                fn (Builder $packingQuery) => $packingQuery
                    ->where('packing_assigned_user_id', $user->id)
                    ->orWhereHas(
                        'packingJob',
                        fn (Builder $jobQuery) => $jobQuery
                            ->where('assigned_user_id', $user->id)
                    )
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }

    private function allowedWorkstream(User $user, string $requested): ?string
    {
        $allowed = $user->isAdmin()
            ? ['design', 'printing', 'packing', 'fulfilment']
            : match ($user->role) {
                User::ROLE_DESIGNER => ['design'],
                User::ROLE_PRINTING => ['printing'],
                User::ROLE_PACKING => ['packing'],
                User::ROLE_OM => ['packing'],
                default => [],
            };

        return in_array($requested, $allowed, true) ? $requested : null;
    }

    private function applyWorkstream(Builder $query, ?string $workstream): void
    {
        if ($workstream !== null) {
            $query->whereNotIn('status', [
                'COMPLETED',
                ...Order::TERMINAL_OPERATIONAL_STATUSES,
            ]);
        }

        match ($workstream) {
            'design' => $query->whereHas(
                'designJobs',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('status', '!=', 'DESIGN_APPROVED')
            ),
            'printing' => $query->where(
                fn (Builder $printingQuery) => $printingQuery
                    ->where(fn (Builder $assignedQuery) => $assignedQuery
                        ->whereNotNull('printing_assigned_user_id')
                        ->whereDoesntHave('printJobs'))
                    ->orWhereHas(
                        'printJobs',
                        fn (Builder $jobQuery) => $jobQuery
                            ->where('status', '!=', 'PRINTED')
                    )
            ),
            'packing' => $query->where(
                fn (Builder $packingQuery) => $packingQuery
                    ->where(fn (Builder $assignedQuery) => $assignedQuery
                        ->whereNotNull('packing_assigned_user_id')
                        ->whereDoesntHave('packingJob'))
                    ->orWhereHas(
                        'packingJob',
                        fn (Builder $jobQuery) => $jobQuery
                            ->where('status', '!=', 'PACKED')
                    )
            ),
            'fulfilment' => $query->whereHas(
                'fulfilmentJob',
                fn (Builder $jobQuery) => $jobQuery
                    ->whereNotIn('status', ['COMPLETED', 'COLLECTED'])
            ),
            default => null,
        };
    }

    private function applyFilters(Builder $query, Request $request, User $user): void
    {
        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn (Builder $builder) => $builder
                ->where('order_id', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === Order::STATUS_FILTER_NOT_COMPLETED) {
                $query->where('status', '!=', 'COMPLETED');
            } else {
                $query->where('status', $status);
            }
        }

        if (! $user->isAdmin()) {
            return;
        }

        match ($request->string('attention')->toString()) {
            'pending_payment' => $query->whereHas(
                'payments',
                fn (Builder $payment) => $payment->where('status', 'PENDING')
            ),
            'unassigned_design' => $query->whereHas(
                'designJobs',
                fn (Builder $job) => $job
                    ->whereNull('assigned_user_id')
                    ->whereIn('status', ['READY_FOR_DESIGN', 'CORRECTION_REQUESTED'])
            ),
            'unassigned_printing' => $query->whereHas(
                'printJobs',
                fn (Builder $job) => $job
                    ->whereNull('assigned_user_id')
                    ->where('status', 'READY_FOR_PRINT')
            ),
            'unassigned_packing' => $query->whereHas(
                'packingJob',
                fn (Builder $job) => $job
                    ->whereNull('assigned_user_id')
                    ->where('status', 'READY_FOR_PACKING')
            ),
            default => null,
        };
    }
}
