<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Design\ResumeDesignAfterCorrectionService;
use App\Services\Design\StartDesignJobService;
use App\Services\Design\SyncOrderDesignStatusService;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Photoshop\GeneratePhotoshopCsvForOrdersService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

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

        if ($user->hasStaffRole(User::ROLE_DESIGNER)) {
            $query->with(['designJobs' => fn (HasMany $jobQuery) => $jobQuery
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_DESIGN', 'DESIGN_IN_PROGRESS', 'CORRECTION_REQUESTED'])
                ->select(['id', 'order_id', 'status', 'assigned_at', 'started_at'])]);
        } elseif ($user->hasStaffRole(User::ROLE_PRODUCTION)) {
            $query->with(['printJobs' => fn (HasMany $jobQuery) => $jobQuery
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', ['READY_FOR_PRINT', 'PRINTING'])
                ->select(['id', 'order_id', 'status', 'assigned_at', 'started_at'])]);
        }

        $perPage = 10;

        $orders = $query
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $statusOptions = Order::query()->whereNotNull('status');
        $statusOptions->where('status', '!=', 'DESIGN_READY');

        if (! $user->canMonitorAllDepartments()) {
            $statusOptions->where('status', '!=', 'DETAILS_INCOMPLETE');
        }

        return view('staff.orders.index', [
            'orders' => $orders,
            'workstream' => $workstream,
            'statusOptions' => $statusOptions
                ->distinct()
                ->orderBy('status')
                ->pluck('status'),
        ]);
    }

    public function downloadDesignQueueCsv(
        Request $request,
        GeneratePhotoshopCsvForOrdersService $csvService,
        StartDesignJobService $startDesign,
        ResumeDesignAfterCorrectionService $resumeDesign,
        SyncOrderDesignStatusService $syncOrderStatus
    ): BinaryFileResponse|RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasStaffRole(User::ROLE_DESIGNER), 403);

        $validated = $request->validate([
            'workstream' => ['required', 'in:design'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'attention' => ['nullable', 'string', 'max:100'],
        ]);

        $workstream = $this->allowedWorkstream($user, (string) $validated['workstream']);
        abort_unless($workstream === 'design', 403);

        $query = $this->visibleOrdersFor($user);
        $this->applyWorkstream($query, $workstream);
        $this->applyFilters($query, $request, $user);

        $page = max(1, (int) ($validated['page'] ?? 1));
        $orders = $query
            ->latest('id')
            ->paginate(10, ['*'], 'page', $page)
            ->getCollection();

        if ($orders->isEmpty()) {
            return back()->withErrors([
                'photoshop' => 'There are no customer orders on this page to export.',
            ]);
        }

        $directory = storage_path('app/private/photoshop-downloads');
        $downloadName = sprintf(
            'DESIGN_QUEUE_PAGE_%d_%d_CUSTOMERS_READY_TO_MERGE.csv',
            $page,
            $orders->count()
        );
        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.csv';

        try {
            $csvService->generate($orders, $path);

            DB::transaction(function () use (
                $orders,
                $user,
                $startDesign,
                $resumeDesign,
                $syncOrderStatus
            ): void {
                foreach ($orders as $selectedOrder) {
                    $order = Order::query()
                        ->whereKey($selectedOrder->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $designJobs = $order->designJobs()
                        ->where('assigned_user_id', $user->id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($designJobs as $designJob) {
                        if ($designJob->status === 'READY_FOR_DESIGN') {
                            $startDesign->start($designJob, $user);
                        } elseif ($designJob->status === 'CORRECTION_REQUESTED') {
                            $resumeDesign->resume($designJob, $user);
                        }
                    }

                    $syncOrderStatus->sync($order);
                }
            });
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            if ($exception instanceof \RuntimeException) {
                return back()->withErrors([
                    'photoshop' => $exception->getMessage(),
                ]);
            }

            report($exception);

            return back()->withErrors([
                'photoshop' => 'The customer CSV could not be prepared. Please try again.',
            ]);
        }

        return response()
            ->download($path, $downloadName, ['Content-Type' => 'text/csv; charset=UTF-8'])
            ->deleteFileAfterSend(true);
    }

    public function show(Request $request, string $orderId, BuildCustomerProgressService $progressBuilder): View
    {
        /** @var User $user */
        $user = $request->user();

        $order = $this->visibleOrdersFor($user)
            ->where('order_id', $orderId)
            ->with([
                'packageSides.design',
                'packageSides.parents',
                'packageSides.event.contacts',
                'couples',
                'fulfilment',
                'payments',
                'invoice',
                'confirmation',
                'fulfilmentJob.events.actor',
                'printingAssignedUser',
                'packingAssignedUser',
                'statusEvents' => fn ($query) => $query
                    ->with('actor')
                    ->latest('occurred_at')
                    ->latest('id'),
            ])
            ->firstOrFail();

        $order->unsetRelation('designJobs');
        $order->unsetRelation('printJobs');
        $order->unsetRelation('packingJob');

        if ($user->canMonitorAllDepartments()) {
            $order->load([
                'designJobs.assignedUser',
                'designJobs.artworkVersions',
                'designJobs.reviewActions',
                'printJobs.assignedUser',
                'packingJob.assignedUser',
                'packingJob.items',
                'packingJob.proofs',
                'designJobs.events.actor',
                'printJobs.events.actor',
                'packingJob.events.actor',
            ]);
        } elseif ($user->hasStaffRole(User::ROLE_DESIGNER)) {
            $order->load([
                'designJobs' => fn ($query) => $query
                    ->where('assigned_user_id', $user->id)
                    ->with([
                        'assignedUser',
                        'artworkVersions',
                        'reviewActions',
                    ]),
            ]);
        } elseif ($user->hasStaffRole(User::ROLE_PRODUCTION)) {
            $order->load([
                'printJobs' => fn ($query) => $query
                    ->where('assigned_user_id', $user->id)
                    ->with('assignedUser'),
            ]);
        }

        if ($user->canViewAllProgressUpdates()) {
            $order->load([
                'progressUpdates' => fn ($query) => $query
                    ->with('uploader:id,name')
                    ->latest(),
            ]);
        } elseif (in_array($user->role, [User::ROLE_DESIGNER, User::ROLE_DIGITAL_DESIGNER, User::ROLE_PRODUCTION], true)) {
            $order->load([
                'progressUpdates' => fn ($query) => $query
                    ->where('uploader_user_id', $user->id)
                    ->with('uploader:id,name')
                    ->latest(),
            ]);
        }

        $timelineEvents = collect();

        if ($user->canMonitorAllDepartments()) {
            $timelineEvents = collect([
                ...$order->statusEvents->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor,
                    'occurred_at' => $event->occurred_at,
                    'reason' => $event->reason,
                ]),
                ...$order->designJobs->flatMap(fn ($job) => $job->events->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor,
                    'occurred_at' => $event->occurred_at,
                    'reason' => null,
                ])),
                ...$order->printJobs->flatMap(fn ($job) => $job->events->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor,
                    'occurred_at' => $event->occurred_at,
                    'reason' => null,
                ])),
                ...($order->packingJob?->events ?? collect())->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor,
                    'occurred_at' => $event->occurred_at,
                    'reason' => null,
                ]),
                ...($order->fulfilmentJob?->events ?? collect())->map(fn ($event) => [
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'actor' => $event->actor,
                    'occurred_at' => $event->occurred_at,
                    'reason' => null,
                ]),
            ])->sortByDesc('occurred_at')->values();
        }

        $assignmentOptions = [
            'designStaff' => collect(),
            'productionStaff' => collect(),
            'packingStaff' => collect(),
        ];

        if ($user->isOperationManagement() && ! $request->attributes->get('staff_overview_mode', false)) {
            $assignmentOptions = [
                'designStaff' => User::query()
                    ->whereIn('role', User::DESIGNER_ROLES)
                    ->where('is_active', true)
                    ->orderByDesc('is_design_lead')
                    ->orderBy('name')
                    ->get(['id', 'name', 'is_design_lead']),
                'productionStaff' => User::query()
                    ->where('role', User::ROLE_PRODUCTION)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'packingStaff' => User::query()
                    ->where('role', User::ROLE_OM)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ];
        }

        return view('staff.orders.show', [
            'order' => $order,
            'orderProgress' => $progressBuilder->build($order),
            'timelineEvents' => $timelineEvents,
            'canAssignProduction' => $order->designJobs()->exists()
                && $order->designJobs()
                    ->where('status', '!=', 'DESIGN_APPROVED')
                    ->doesntExist(),
            ...$assignmentOptions,
        ]);
    }

    public function downloadCustomerPhoto(Request $request, string $orderId, string $side)
    {
        /** @var User $user */
        $user = $request->user();

        $order = $this->visibleOrdersFor($user)
            ->where('order_id', $orderId)
            ->with('packageSides.design')
            ->firstOrFail();

        $packageSide = $order->packageSides
            ->firstWhere('side', strtoupper($side));
        $photoPath = $packageSide?->design?->card_image_path;

        abort_unless(
            is_string($photoPath)
                && $photoPath !== ''
                && Storage::disk('local')->exists($photoPath),
            404
        );

        return Storage::disk('local')->download(
            $photoPath,
            'gambar-pengantin-'.$order->order_id.'-'.strtolower($packageSide->side).'.jpg'
        );
    }

    private function visibleOrdersFor(User $user): Builder
    {
        $query = Order::query();

        if ($user->canMonitorAllDepartments()) {
            return $query;
        }

        if ($user->role === User::ROLE_DIGITAL_DESIGNER) {
            return $query->where(fn (Builder $digitalQuery) => $digitalQuery
                ->where(fn (Builder $paidQuery) => $paidQuery
                    ->where('booking_payment_status', 'PAID')
                    ->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES))
                ->orWhere(fn (Builder $assignedQuery) => $assignedQuery
                    ->where('status', '!=', 'DETAILS_INCOMPLETE')
                    ->whereHas('designJobs', fn (Builder $jobQuery) => $jobQuery
                        ->where('assigned_user_id', $user->id))));
        }

        // Incomplete entries are customer drafts, not operational orders.
        $query->where('status', '!=', 'DETAILS_INCOMPLETE');

        return match ($user->role) {
            User::ROLE_ADMIN => $query,

            User::ROLE_DESIGNER => $query->whereHas(
                'designJobs',
                fn (Builder $jobQuery) => $jobQuery
                    ->where('assigned_user_id', $user->id)
            ),

            User::ROLE_PRODUCTION => $query->where(
                fn (Builder $printingQuery) => $printingQuery
                    ->where('printing_assigned_user_id', $user->id)
                    ->orWhereHas(
                        'printJobs',
                        fn (Builder $jobQuery) => $jobQuery
                            ->where('assigned_user_id', $user->id)
                    )
            ),

            User::ROLE_OM => $query->where(
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
        $allowed = $user->canMonitorAllDepartments()
            ? ['design', 'printing', 'packing', 'fulfilment']
            : match ($user->role) {
                User::ROLE_DESIGNER, User::ROLE_DIGITAL_DESIGNER => ['design'],
                User::ROLE_PRODUCTION => ['printing'],
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
            $orderIdSearch = str_replace(['-', ' '], '', $search);

            $query->where(function (Builder $builder) use ($search, $orderIdSearch): void {
                $builder
                    ->where('order_id', 'like', "%{$search}%")
                    ->orWhere('customer_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");

                foreach (Order::customerReferenceLookupCandidates($search) as $referenceCandidate) {
                    $builder->orWhere('customer_reference', 'like', "%{$referenceCandidate}%");
                }

                if ($orderIdSearch !== '') {
                    $builder->orWhereRaw(
                        "REPLACE(REPLACE(order_id, '-', ''), ' ', '') like ?",
                        ["%{$orderIdSearch}%"]
                    );
                }
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === Order::STATUS_FILTER_NOT_COMPLETED) {
                $query->where('status', '!=', 'COMPLETED');
            } else {
                $query->where('status', $status);
            }
        }

        if (! $user->canMonitorAllDepartments()) {
            return;
        }

        if ($request->filled('attention')) {
            $query->whereNotIn('status', Order::TERMINAL_OPERATIONAL_STATUSES);
        }

        match ($request->string('attention')->toString()) {
            'pending_payment' => $query->whereHas(
                'payments',
                fn (Builder $payment) => $payment->where('status', 'PENDING')
            ),
            'pending_orders' => $query->awaitingDesignerAssignment(),
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
            'packing_fulfilment' => $query->where(
                fn (Builder $attentionQuery) => $attentionQuery
                    ->whereHas(
                        'packingJob',
                        fn (Builder $job) => $job->whereIn('status', ['READY_FOR_PACKING', 'PACKING'])
                    )
                    ->orWhereHas(
                        'fulfilmentJob',
                        fn (Builder $job) => $job->whereIn('status', ['READY', 'IN_TRANSIT'])
                    )
            ),
            default => null,
        };
    }
}
