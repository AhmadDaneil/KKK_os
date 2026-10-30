@php
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $logoutRoute = $isAdminPortal ? 'admin.logout' : 'staff.logout';
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $operationRoutePrefix = $isAdminPortal ? 'admin.' : 'staff.';
    $hasFullQueueView = auth()->user()->canMonitorAllDepartments();
    $customerWhatsappNumber = preg_replace('/\D+/', '', (string) $order->customer_phone);
    if (str_starts_with($customerWhatsappNumber, '0')) {
        $customerWhatsappNumber = '6'.$customerWhatsappNumber;
    }
    $customerFeedbackMessage = "Hello {$order->customer_name}, thank you for choosing KingKadKahwin. We would appreciate your feedback and are happy to help with any questions about order {$order->order_id}.";
    $customerPaymentReminder = "Hello {$order->customer_name}, this is a friendly reminder from KingKadKahwin about the outstanding balance or charges for order {$order->order_id}. Please contact us if you need help.";
    $hasDesignJobs = $order->relationLoaded('designJobs');
    $batchArtworkJobs = $hasDesignJobs
        ? $order->designJobs->filter(fn ($job) =>
            auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
            && $job->assigned_user_id === auth()->id()
            && $job->status === 'DESIGN_IN_PROGRESS'
        )
        : collect();
    $usesBatchArtworkUpload = $batchArtworkJobs->count() > 1;
    $hasTwoBatchArtworkJobs = $batchArtworkJobs->count() === 2;
    $canUsePhotoshop = auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
        && $hasDesignJobs
        && $order->designJobs->contains('assigned_user_id', auth()->id());
    $canUploadProgressUpdate = match (auth()->user()->role) {
        \App\Models\User::ROLE_DESIGNER => $hasDesignJobs
            && $order->designJobs->contains('assigned_user_id', auth()->id()),
        \App\Models\User::ROLE_PRODUCTION => $order->relationLoaded('printJobs')
            && $order->printJobs->contains('assigned_user_id', auth()->id()),
        \App\Models\User::ROLE_OM => ($order->relationLoaded('packingJob') && $order->packingJob)
            || ($order->relationLoaded('fulfilmentJob') && $order->fulfilmentJob),
        default => false,
    };
    $progressRoleLabels = [
        \App\Models\User::ROLE_DESIGNER => 'Designer',
        \App\Models\User::ROLE_PRODUCTION => 'Production',
        \App\Models\User::ROLE_OM => 'OM',
    ];
    $statusLabels = [
        'DETAILS_INCOMPLETE' => 'Details incomplete', 'READY_FOR_DESIGN' => 'Ready for design',
        'DESIGN_IN_PROGRESS' => 'Design in progress', 'DESIGN_READY' => 'Awaiting customer review',
        'CORRECTION_REQUESTED' => 'Correction required', 'DESIGN_APPROVED' => 'Design approved',
        'BALANCE_PENDING' => 'Awaiting balance payment', 'READY_FOR_PRINT' => 'Ready for production',
        'PRINTING' => 'Production in progress', 'PRINTED' => 'Production complete',
        'READY_FOR_PACKING' => 'Ready for packing', 'PACKING' => 'Packing in progress',
        'PACKED' => 'Packing complete', 'READY_FOR_PICKUP' => 'Ready for pickup',
        'SHIPPED' => 'Shipped', 'COMPLETED' => 'Order complete',
        'CANCELLED' => 'CANCELLED', 'ARCHIVED' => 'Order archived',
    ];
    $statusTone = static fn (string $status): string => match ($status) {
        'DETAILS_INCOMPLETE', 'CORRECTION_REQUESTED' => 'attention',
        'DESIGN_READY', 'BALANCE_PENDING', 'READY_FOR_PICKUP', 'SHIPPED' => 'waiting',
        'COMPLETED', 'ARCHIVED' => 'complete', 'CANCELLED' => 'cancelled', default => 'active',
    };
    $nextActions = [
        'DETAILS_INCOMPLETE' => 'Collect missing customer details',
        'READY_FOR_DESIGN' => 'Start or assign design work', 'DESIGN_IN_PROGRESS' => 'Continue design work',
        'DESIGN_READY' => 'Review the completed design', 'CORRECTION_REQUESTED' => 'Complete design corrections',
        'DESIGN_APPROVED' => 'Review balance payment', 'BALANCE_PENDING' => 'Review balance payment',
        'READY_FOR_PRINT' => 'Start production', 'PRINTING' => 'Update or complete production',
        'PRINTED' => 'Start packing', 'READY_FOR_PACKING' => 'Start packing',
        'PACKING' => 'Verify items and complete packing', 'PACKED' => 'Arrange handover or pickup',
        'READY_FOR_PICKUP' => 'Notify customer about pickup', 'SHIPPED' => 'Track delivery',
        'COMPLETED' => 'No further action required',
        'CANCELLED' => 'No operational action; record retained for audit',
        'ARCHIVED' => 'No further action; record retained in archive',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $order->order_id }} - KKK OS Staff</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-compact.css') }}?v={{ filemtime(public_path('css/dashboard-compact.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/operations-theme.css') }}?v={{ filemtime(public_path('css/operations-theme.css')) }}">
    @include('staff.partials.theme-init')
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? __('ui.admin_operations') : __('ui.staff_operations') }} · {{ __('ui.order_detail') }}</p><h1>{{ $order->order_id }}</h1></div>
                <div class="staff-topbar-actions">
                    @include('staff.partials.theme-toggle')
                    <form class="js-logout-form staff-logout-profile" method="POST" action="{{ route($logoutRoute) }}">@csrf<button type="submit"><span class="staff-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
                </div>
            </header>

        <main class="staff-main">
            @if (session('status'))
    <div class="staff-alert staff-alert-success">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="staff-alert staff-alert-error">
        <strong>The action could not be completed.</strong>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
            <div class="staff-page-header">
                <div>
                    <a href="{{ route($ordersIndexRoute) }}" class="staff-back-link">
                        &larr; Order
                    </a>

                    <div class="staff-detail-heading">
                        <h1>{{ $order->order_id }}</h1>

                        <span class="staff-status staff-status--{{ $statusTone($order->status) }}">
                            {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>

                    <p>
                        {{ $order->customer_name ?: 'Customer name unavailable' }}
                    </p>
                </div>

                <div class="staff-page-header-actions">
                    <div class="staff-page-count">
                        {{ $order->package_count }}
                        {{ $order->package_count === 1 ? 'package' : 'packages' }}
                    </div>
                    @if ($order->invoice && (auth()->user()->isAdmin() || auth()->user()->hasStaffRole(\App\Models\User::ROLE_OM)))
                        <a class="staff-button staff-button-primary" href="{{ route(request()->routeIs('admin.orders.*') ? 'admin.invoices.download' : 'staff.invoices.download', $order->invoice) }}">
                            Download Invoice
                        </a>
                    @endif
                    @if ($order->status === 'DETAILS_INCOMPLETE' && auth()->user()->isOperationManagement())
                        <form
                            method="POST"
                            action="{{ route($operationRoutePrefix.'orders.destroy', $order) }}"
                            class="js-staff-confirmation-form"
                            data-confirm-title="Padam tempahan {{ $order->order_id }}?"
                            data-confirm-message="Semua maklumat tempahan ini akan dipadam secara kekal. Tindakan ini tidak boleh dibuat asal."
                            data-confirm-button="Ya, padam tempahan"
                            data-confirm-tone="danger"
                        >
                            @csrf
                            @method('DELETE')
                            <button class="staff-button staff-button-danger" type="submit">Delete order</button>
                        </form>
                    @endif
                </div>
            </div>

            <section class="staff-order-brief" aria-label="Order actions summary">
                <div><span>Current status</span><strong>{{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}</strong></div>
                <div><span>Next action</span><strong>{{ $nextActions[$order->status] ?? 'Open each section for review' }}</strong></div>
            </section>

            @if (auth()->user()->isOperationManagement() && ! $order->isTerminalOperationalStatus())
                <section class="staff-card" aria-labelledby="order-lifecycle-heading">
                    <h2 id="order-lifecycle-heading">Close Order Without Deleting Records</h2>
                    <p>Use this action only after an operational review. Order and audit records will be retained.</p>

                    <div class="staff-payment-actions">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.cancel', $order) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Cancel order {{ $order->order_id }}?" data-confirm-message="The order record will be retained with CANCELLED status and cannot be reactivated." data-confirm-button="Yes, cancel order" data-confirm-tone="danger">
                            @csrf
                            <label for="cancel-reason">Cancellation reason</label>
                            <textarea id="cancel-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>
                            <button class="staff-button staff-button-danger" type="submit">Cancel Order</button>
                        </form>

                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.archive', $order) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Archive order {{ $order->order_id }}?" data-confirm-message="The order record will be retained with ARCHIVED status and cannot be reactivated." data-confirm-button="Yes, archive order">
                            @csrf
                            <label for="archive-reason">Archive reason</label>
                            <textarea id="archive-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>
                            <button class="staff-button staff-button-secondary" type="submit">Archive Order</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView)
                <nav class="staff-order-sections" aria-label="Order sections">
                    <a href="#customer-data">Customer</a><a href="#design-work">Design</a><a href="#payment">Payments</a><a href="#production">Production</a><a href="#packing">Packing</a><a href="#fulfilment">Order Fulfilment</a>
                </nav>
            @endif

            @if (! auth()->user()->isOperationManagement())
                <p class="staff-work-message">These order details are read-only.</p>
            @endif

            @if ($hasFullQueueView)
            @php
                $confirmedPreview = data_get($order->confirmation?->confirmed_snapshot, 'live_preview');
            @endphp
            <div id="customer-data" class="staff-detail-grid staff-department-group">
                <section class="staff-card">
                    <h2>Order Summary</h2>

                    <dl class="staff-detail-list">
                        <div>
                            <dt>Status</dt>
                            <dd>{{ str_replace('_', ' ', $order->status) }}</dd>
                        </div>

                        <div>
                            <dt>Package</dt>
                            <dd>{{ $order->package_count }}</dd>
                        </div>

                        <div>
                            <dt>Total Cards</dt>
                            <dd>{{ $order->card_quantity ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Order Payment</dt>
                            <dd>{{ $order->booking_payment_status ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Customer Reference</dt>
                            <dd>{{ $order->customer_reference ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Order Date</dt>
                            <dd><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->timezone(config('app.display_timezone'))->format('d/m/Y') }}</time></dd>
                        </div>
                    </dl>
                </section>

                <section class="staff-card">
                    <h2>Customer</h2>

                    <dl class="staff-detail-list">
                        <div>
                            <dt>Name</dt>
                            <dd>{{ $order->customer_name ?: '-' }}</dd>
                        </div>

                        <div>
                            <dt>Email</dt>
                            <dd>{{ $order->customer_email ?: '-' }}</dd>
                        </div>

                        <div>
                            <dt>Phone</dt>
                            <dd>{{ $order->customer_phone ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Order Time</dt>
                            <dd><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->timezone(config('app.display_timezone'))->format('H:i') }}</time></dd>
                        </div>
                    </dl>

                    @if (auth()->user()->isCustomerService() && $customerWhatsappNumber !== '')
                        <div class="staff-customer-contact-actions">
                            <a class="staff-button staff-button-primary" target="_blank" rel="noopener" href="https://wa.me/{{ $customerWhatsappNumber }}?text={{ rawurlencode($customerFeedbackMessage) }}">Message</a>
                            <a class="staff-button" target="_blank" rel="noopener" href="https://wa.me/{{ $customerWhatsappNumber }}?text={{ rawurlencode($customerPaymentReminder) }}">Payment Reminder</a>
                        </div>
                    @endif
                </section>
            </div>

            @if (is_array($confirmedPreview))
                <section class="staff-card staff-confirmed-preview">
                    <h2>Confirmed Live Preview</h2>
                    <p>Customer reference record from final confirmation. This is not print-ready artwork or a source file.</p>
                    <dl class="staff-detail-list">
                        @foreach (($confirmedPreview['package_sides'] ?? []) as $previewSide)
                            <div>
                                <dt>{{ $previewSide['side'] ?? '-' }}</dt>
                                <dd>{{ data_get($previewSide, 'design.design_code', '-') }} · {{ data_get($previewSide, 'design.theme', '-') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <section class="staff-section staff-timeline-section">
                <div class="staff-lifecycle-heading">
                    <div><p class="staff-kicker">Order journey</p><h2>Order progress</h2></div>
                    <span>The current stage is highlighted</span>
                </div>

                <ol class="staff-lifecycle-list" aria-label="Order stages">
                    @foreach ($orderProgress['stages'] as $stage)
                        <li class="is-{{ $stage['state'] }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                            <span class="staff-lifecycle-marker" aria-hidden="true">{{ $stage['state'] === 'complete' ? '✓' : $stage['number'] }}</span>
                            <strong>{{ $stage['label'] }}</strong>
                            <small>{{ $stage['description'] }}</small>
                        </li>
                    @endforeach
                </ol>

                @if ($timelineEvents->isNotEmpty())
                    <details class="staff-activity-history">
                        <summary>View detailed activity log ({{ $timelineEvents->count() }})</summary>
                        <ol class="staff-timeline">
                            @foreach ($timelineEvents as $event)
                                <li class="staff-timeline-item">
                                    <span class="staff-timeline-marker" aria-hidden="true"></span>
                                    <div class="staff-timeline-content">
                                        <div class="staff-timeline-heading">
                                            <strong>{{ str_replace('_', ' ', $event['to_status'] ?: $event['event_type']) }}</strong>
                                            <time datetime="{{ $event['occurred_at']?->toIso8601String() }}">{{ $event['occurred_at']?->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') ?? '-' }}</time>
                                        </div>
                                        <p>{{ $event['actor']?->name ?? 'System' }} @if ($event['from_status'] && $event['from_status'] !== $event['to_status'])<span>· {{ str_replace('_', ' ', $event['from_status']) }} → {{ str_replace('_', ' ', $event['to_status']) }}</span>@endif</p>
                                        @if ($event['reason'])<small>{{ $event['reason'] }}</small>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            </section>

            @if ($order->relationLoaded('progressUpdates') || $canUploadProgressUpdate)
                <section class="staff-section staff-progress-updates" id="progress-updates">
                    <div class="staff-lifecycle-heading">
                        <div><p class="staff-kicker">Per-order record</p><h2>Progress updates</h2></div>
                        <span>Operational proof files remain private; only updates marked customer-visible are shown to customers.</span>
                    </div>

                    @if ($order->relationLoaded('progressUpdates') && $order->progressUpdates->isNotEmpty())
                        <ol class="staff-progress-update-list">
                            @foreach ($order->progressUpdates as $update)
                                <li>
                                    <div class="staff-progress-update-heading">
                                        <strong>{{ $update->uploader?->name ?? 'Staff account deleted' }}</strong>
                                        <span>{{ $progressRoleLabels[$update->uploader_role] ?? $update->uploader_role }}</span>
                                        <time datetime="{{ $update->created_at->toIso8601String() }}">{{ $update->created_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</time>
                                    </div>
                                    <p>{{ $update->message ?: 'Progress attachment uploaded.' }}</p>
                                    <div class="staff-progress-update-meta">
                                        <span>Status: {{ str_replace('_', ' ', $update->order_status) }}</span>
                                        <span class="is-{{ strtolower($update->visibility) }}">{{ $update->visibility === \App\Models\OrderProgressUpdate::VISIBILITY_CUSTOMER ? 'Customer-visible' : 'Internal / private' }}</span>
                                        @if ($update->hasAttachment())
                                            <a href="{{ route($operationRoutePrefix.'progress-updates.attachment', $update) }}" target="_blank" rel="noopener">View attachment</a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="staff-muted-text">No order-specific progress updates yet.</p>
                    @endif

                    @if ($canUploadProgressUpdate && ! $order->isAssignmentLocked())
                        <form method="POST" enctype="multipart/form-data" action="{{ route('staff.orders.progress-updates.store', $order) }}" class="staff-progress-update-form">
                            @csrf
                            <label for="progress-message">Progress note</label>
                            <textarea id="progress-message" name="message" maxlength="2000" rows="4" placeholder="Write an update suitable for internal records or customers.">{{ old('message') }}</textarea>
                            <label for="progress-attachment">Optional attachment</label>
                            <input id="progress-attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                            <label class="staff-progress-visibility">
                                <input type="checkbox" name="customer_visible" value="1" @checked(old('customer_visible'))>
                                <span>Show this update on the Customer Dashboard</span>
                            </label>
                            <p class="staff-muted-text">Updates are private by default. Do not publish operational instructions, other customer data, shipping labels or private work files.</p>
                            @error('progress_update')<p class="staff-upload-notice is-error">{{ $message }}</p>@enderror
                            <button type="submit" class="staff-button staff-button-primary">Save Update</button>
                        </form>
                    @endif
                </section>
            @endif
            @endif

            <section class="staff-section">
                <h2 class="staff-section-title">Package</h2>

                <div class="staff-package-grid">
                    @foreach ($order->packageSides as $packageSide)
                        <article class="staff-card staff-package-card">
                            <div class="staff-card-heading">
                                <h3>{{ $packageSide->side === 'LELAKI' ? 'Groom' : 'Bride' }}</h3>

                                @if ($packageSide->design?->design_code)
                                    <span class="staff-status">
                                        {{ $packageSide->design->design_code }}
                                    </span>
                                @endif
                            </div>

                            @if ($packageSide->design)
                                <div class="staff-detail-group">
                                    <h4>Design</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Card Theme</dt>
                                            <dd>{{ $packageSide->design->theme ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Design Code</dt>
                                            <dd>{{ $packageSide->design->design_code ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Card Title</dt>
                                            <dd>{{ match ($packageSide->design->card_title) { 'Walimatul Urus' => 'Wedding Reception', 'Majlis Perkahwinan' => 'Wedding Ceremony', 'Kenduri Kesyukuran' => 'Thanksgiving Celebration', default => $packageSide->design->card_title ?? '-' } }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @php
                                $packageCouple = $order->couples->firstWhere('couple_number', 1);
                            @endphp
                            <div class="staff-detail-group">
                                <h4>Couple's Short Names</h4>
                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Groom</dt>
                                        <dd>{{ $packageCouple?->groom_abbreviation ?: $packageCouple?->groom_name ?: '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Bride</dt>
                                        <dd>{{ $packageCouple?->bride_abbreviation ?: $packageCouple?->bride_name ?: '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            @if ($packageSide->parents)
                                <div class="staff-detail-group">
                                    <h4>Parents</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Father's Name</dt>
                                            <dd>{{ $packageSide->parents->father_name ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Mother's Name</dt>
                                            <dd>{{ $packageSide->parents->mother_name ?? '-' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @if ($packageSide->event)
                                <div class="staff-detail-group">
                                    <h4>Event</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Day</dt>
                                            <dd>{{ ['Ahad' => 'Sunday', 'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday'][$packageSide->event->day_name ?? ''] ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Date</dt>
                                            <dd>{{ $packageSide->event->event_date?->format('Y-m-d') ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Hijri Date</dt>
                                            <dd>{{ $packageSide->event->hijri_date ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Meal Time</dt>
                                            <dd>{{ $packageSide->event->meal_time ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Wedding Reception Time</dt>
                                            <dd>{{ $packageSide->event->bersanding_time ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Event Venue</dt>
                                            <dd>{{ $packageSide->event->venue_name ?? '-' }}</dd>
                                        </div>

                                        <div class="staff-detail-wide">
                                            <dt>Address</dt>
                                            <dd>{{ $packageSide->event->full_address ?? '-' }}</dd>
                                        </div>
                                    </dl>

                                    @if ($packageSide->event->google_maps_url)
                                        <div class="staff-inline-action">
                                            <a
                                                href="{{ $packageSide->event->google_maps_url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="staff-button staff-button-small"
                                            >
                                                Open Google Maps
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                @if ($packageSide->event->contacts->isNotEmpty())
                                    <div class="staff-detail-group">
                                        <h4>Contact Persons</h4>

                                        <div class="staff-contact-list">
                                            @foreach ($packageSide->event->contacts as $contact)
                                                <div class="staff-contact-row">
                                                    <span>{{ $contact->contact_name ?? '-' }}</span>
                                                    <strong>{{ $contact->contact_phone ?? '-' }}</strong>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($hasDesignJobs)
                <section id="design-work" class="staff-section">
                    <h2 class="staff-section-title">Design Work</h2>

                    @if ($canUsePhotoshop)
                        <div class="staff-design-tools">
                            <div>
                                <p class="staff-kicker">Photoshop Auto Merge V35</p>
                                <h3>Prepare this customer order in Photoshop</h3>
                                <ol>
                                    <li>Download the customer order CSV file.</li>
                                    <li>Open Photoshop on a Windows workstation and run the approved V35 JavaScript.</li>
                                    <li>Select the MASTER folder, then choose the downloaded CSV file.</li>
                                </ol>
                                <p class="staff-design-tools-note">
                                    KKK OS provides the customer CSV for the Photoshop Auto Merge workflow.
                                </p>
                            </div>

                            <div class="staff-design-tools-actions">
                                <a
                                    href="{{ route('staff.orders.photoshop.csv', $order) }}"
                                    class="staff-button"
                                >
                                    Download Customer CSV
                                </a>
                            </div>
                        </div>
                    @endif

                    <div class="staff-work-grid">
                        @forelse ($order->designJobs as $job)
                            <article class="staff-card" data-design-job-id="{{ $job->id }}">
                                <div class="staff-card-heading">
                                    <h3>{{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}</h3>

                                    <span class="staff-status">
                                        {{ str_replace('_', ' ', $job->status) }}
                                    </span>
                                </div>

                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Assigned</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Not assigned yet' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Started</dt>
                                        <dd>{{ $job->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Ready</dt>
                                        <dd>{{ $job->design_ready_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Design Versions</dt>
                                        <dd>{{ $job->artworkVersions->count() }}</dd>
                                    </div>
                                </dl>
                                @php
                                    $latestProofArtwork = $job->artworkVersions->sortByDesc('version_number')->first();
                                    $latestProofFiles = collect($latestProofArtwork?->preview_files ?: array_filter([
                                        $latestProofArtwork?->preview_storage_path ? ['path' => $latestProofArtwork->preview_storage_path] : null,
                                    ]))->values();
                                    $cardPreviewNumber = 0;
                                    $proofPreviewOptions = $latestProofFiles
                                        ->map(function ($file, $fileIndex) use (&$cardPreviewNumber) {
                                            $path = data_get($file, 'watermarked_path') ?: data_get($file, 'path');
                                            $filename = data_get($file, 'original_name') ?: $path;
                                            $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

                                            if (! filled($path) || ! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                                                return null;
                                            }

                                            $artworkType = strtoupper((string) data_get($file, 'artwork_type', 'CARD'));
                                            $label = match ($artworkType) {
                                                'BANNER' => 'Banner',
                                                'BANTING' => 'Bunting',
                                                default => match (++$cardPreviewNumber) {
                                                    1 => 'Kad Depan',
                                                    2 => 'Kad Belakang',
                                                    default => 'Kad '.$cardPreviewNumber,
                                                },
                                            };

                                            return [
                                                'index' => $fileIndex,
                                                'label' => $label,
                                            ];
                                        })
                                        ->filter()
                                        ->values();
                                    $hasDesignProof = $proofPreviewOptions->isNotEmpty();
                                    $proofPreviewSelectId = 'designer-proof-preview-'.$job->id;
                                    $latestCorrectionAction = $job->reviewActions
                                        ->where('action', 'CORRECTION_REQUESTED')
                                        ->sortByDesc('acted_at')
                                        ->first();
                                    $latestCorrectionPayment = $order->payments
                                        ->where('payment_type', 'ARTWORK_CORRECTION')
                                        ->filter(fn ($correctionPayment) => (int) ($correctionPayment->metadata['design_job_id'] ?? 0) === $job->id)
                                        ->sortByDesc('id')
                                        ->first();
                                    $correctionComment = $latestCorrectionAction?->customer_comment
                                        ?: ($latestCorrectionPayment?->metadata['correction_comment'] ?? null);
                                    $correctionSubmittedAt = $latestCorrectionAction?->acted_at
                                        ?: $latestCorrectionPayment?->created_at;
                                    $correctionAssets = $latestCorrectionAction?->affected_assets
                                        ?: ($latestCorrectionPayment?->metadata['affected_assets'] ?? ['CARD']);
                                @endphp

                                @if (auth()->user()->isOperationManagement() && $hasDesignProof)
                                    <div class="staff-proof-status">
                                        <strong>Designer Proof · Version {{ $latestProofArtwork->version_number }}</strong>
                                        <label class="staff-proof-status-label" for="{{ $proofPreviewSelectId }}">Preview</label>
                                        <select id="{{ $proofPreviewSelectId }}" class="staff-proof-preview-select" data-proof-preview-select>
                                            @foreach ($proofPreviewOptions as $previewOption)
                                                <option
                                                    value="{{ $previewOption['index'] }}"
                                                    data-proof-url="{{ route($operationRoutePrefix.'design-jobs.proof.show', ['designJob' => $job, 'file' => $previewOption['index']]) }}"
                                                >{{ $previewOption['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <a
                                            href="{{ route($operationRoutePrefix.'design-jobs.proof.show', ['designJob' => $job, 'file' => $proofPreviewOptions->first()['index']]) }}"
                                            class="staff-button staff-button-small js-proof-preview-link"
                                            target="_blank"
                                            rel="noopener"
                                        >View Proof</a>
                                    </div>
                                @endif

                                @if (filled($correctionComment))
                                    <aside class="staff-correction-note" aria-label="Customer correction instructions">
                                        <div class="staff-correction-note-heading">
                                            <span class="staff-correction-note-icon" aria-hidden="true">✎</span>
                                            <div>
                                                <span>Customer comment</span>
                                                <h4>Artwork Correction Instructions</h4>
                                            </div>
                                            <span class="staff-correction-note-state">
                                                {{ $latestCorrectionPayment?->status === 'PENDING' ? 'Awaiting payment review' : 'For designer action' }}
                                            </span>
                                        </div>
                                        <p><strong>Affected items:</strong> {{ collect($correctionAssets)->map(fn ($asset) => ['CARD' => 'Wedding Card', 'BANNER' => 'Banner', 'BANTING' => 'Bunting'][$asset] ?? $asset)->join(', ') }}</p>
                                        <blockquote>{{ $correctionComment }}</blockquote>
                                        <footer>
                                            <span>Package {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}</span>
                                            @if ($correctionSubmittedAt)
                                                <time datetime="{{ $correctionSubmittedAt->toIso8601String() }}">
                                                    Submitted {{ $correctionSubmittedAt->timezone(config('app.display_timezone'))->format('d/m/Y, h:i A') }}
                                                </time>
                                            @endif
                                        </footer>
                                    </aside>
                                @endif
                                @if (auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
                                    && $job->assigned_user_id === auth()->id())
                                    <div class="staff-design-actions">
                                        @if ($job->status === 'READY_FOR_DESIGN')
                                            <form
                                                method="POST"
                                                action="{{ route($operationRoutePrefix.'design-jobs.start', $job) }}"
                                                class="js-staff-confirmation-form"
                                                data-confirm-title="Start design work {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}?"
                                                data-confirm-message="This package will be marked as in design and the start time recorded."
                                                data-confirm-button="Yes, start design"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="staff-button staff-button-primary"
                                                >
                                                    Start Design
                                                </button>
                                            </form>
                                        @elseif ($job->status === 'CORRECTION_REQUESTED')
                                            <form
                                                method="POST"
                                                action="{{ route($operationRoutePrefix.'design-jobs.resume-correction', $job) }}"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="staff-button staff-button-primary"
                                                >
                                                    Resume Corrections
                                                </button>
                                            </form>
                                        @elseif ($job->status === 'DESIGN_IN_PROGRESS')
                                            @php
                                                $isBatchArtworkJob = $usesBatchArtworkUpload
                                                    && $batchArtworkJobs->contains('id', $job->id);
                                            @endphp
                                            <div class="staff-design-upload">
                                                <div class="staff-design-upload-heading">
                                                    <h4>Upload Design Version</h4>

                                                    <p>
                                                        Upload editable source files and
                                                        customer previews.
                                                    </p>
                                                </div>

                                                @if ($isBatchArtworkJob)
                                                    <div class="staff-artwork-form">
                                                @else
                                                    <form
                                                        method="POST"
                                                        action="{{ route($operationRoutePrefix.'design-jobs.artwork.store', $job) }}"
                                                        enctype="multipart/form-data"
                                                        class="staff-artwork-form js-staff-confirmation-form js-async-artwork-upload"
                                                        data-confirm-title="Upload design {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}?"
                                                        data-confirm-message="Check that the selected source files and customer previews are correct. They will be saved as a new design version."
                                                        data-confirm-button="Yes, upload"
                                                    >
                                                        @csrf
                                                @endif

                                                    <div class="staff-field">
                                                        <label for="source-artwork-{{ $job->id }}">
                                                            Design Source Files
                                                        </label>

                                                        <div class="staff-multi-file-picker" data-file-kind="source artwork">
                                                        <input
                                                            id="source-artwork-{{ $job->id }}"
                                                            type="file"
                                                            name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][source_artwork][]' : 'source_artwork[]' }}"
                                                            @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif
                                                            accept=".psd,.pdf,image/vnd.adobe.photoshop,application/photoshop,application/x-photoshop,application/pdf"
                                                            multiple
                                                            required
                                                        >
                                                            <button type="button" class="staff-file-add" aria-controls="source-artwork-{{ $job->id }}">+ Add Files</button>
                                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            PSD or PDF. Maximum 100 MB.
                                                        </span>
                                                    </div>

                                                    <div class="staff-supplementary-artworks">
                                                        <div class="staff-supplementary-heading">
                                                            <h4>Wedding Card Preview</h4>
                                                            <p>Upload both sides of the card for customer review.</p>
                                                        </div>
                                                        <div class="staff-supplementary-grid">
                                                            <div class="staff-field">
                                                                <label for="card-front-preview-{{ $job->id }}">Front Side <span class="staff-optional">(required)</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--card" aria-hidden="true">2 : 3</span>
                                                                <input id="card-front-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][customer_preview][]' : 'customer_preview[]' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                                                <span class="staff-field-help">Portrait 2:3 format, such as 800 × 1200 px.</span>
                                                            </div>
                                                            <div class="staff-field">
                                                                <label for="card-back-preview-{{ $job->id }}">Back Side <span class="staff-optional">(required)</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--card" aria-hidden="true">2 : 3</span>
                                                                <input id="card-back-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][customer_preview][]' : 'customer_preview[]' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                                                <span class="staff-field-help">Portrait 2:3 format, such as 800 × 1200 px.</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @php
                                                        $jobPackageSide = $order->packageSides
                                                            ->firstWhere('id', $job->order_package_side_id);
                                                        $jobTheme = strtoupper((string) $jobPackageSide?->design?->theme);
                                                    @endphp
                                                    <div class="staff-supplementary-artworks">
                                                        <div class="staff-supplementary-heading">
                                                            <h4>Card Package Preview</h4>
                                                            <p>Banner and bunting are included with every wedding card package. Use the same short names and card design theme.</p>
                                                            @if ($jobTheme === 'PORTRAIT' && $jobPackageSide?->design?->card_image_path)
                                                                <p class="staff-field-help"><strong>PORTRAIT theme:</strong> use the uploaded couple photo for banner and bunting artwork. <a href="{{ route($operationRoutePrefix.'orders.customer-photo', ['orderId' => $order->order_id, 'side' => $jobPackageSide->side]) }}">Download couple photo</a></p>
                                                            @else
                                                                <p class="staff-field-help"><strong>Theme {{ $jobTheme ?: '-' }}:</strong> prepare the banner and bunting using the design theme only, without a couple photo.</p>
                                                            @endif
                                                        </div>
                                                        <div class="staff-supplementary-grid">
                                                            <div class="staff-field">
                                                                <label for="banner-preview-{{ $job->id }}">Preview banner <span class="staff-optional">(required)</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--banner" aria-hidden="true">2 : 1</span>
                                                                <input id="banner-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][banner_preview]' : 'banner_preview' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                                                <span class="staff-field-help">Landscape 2:1 format, such as 1280 × 640 px. Use the short names and card theme.</span>
                                                            </div>
                                                            <div class="staff-field">
                                                                <label for="banting-preview-{{ $job->id }}">Bunting preview <span class="staff-optional">(required)</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--banting" aria-hidden="true">1 : 2</span>
                                                                <input id="banting-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][banting_preview]' : 'banting_preview' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                                                                <span class="staff-field-help">Portrait 1:2 format, such as 640 × 1280 px. Use the short names and card theme.</span>
                                                            </div>
                                                        </div>
                                                    </div>


                                                    <div class="staff-field">
                                                        <label for="internal-note-{{ $job->id }}">
                                                            Internal Note
                                                            <span class="staff-optional">(optional)</span>
                                                        </label>

                                                        <textarea
                                                            id="internal-note-{{ $job->id }}"
                                                            name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][internal_note]' : 'internal_note' }}"
                                                            @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif
                                                            rows="3"
                                                            maxlength="5000"
                                                        >{{ old($isBatchArtworkJob ? 'artworks.'.$job->id.'.internal_note' : 'internal_note') }}</textarea>
                                                    </div>

                                                    @if ($isBatchArtworkJob)
                                                        </div>
                                                    @else
                                                        <button
                                                            type="submit"
                                                            class="staff-button staff-button-primary"
                                                        >
                                                            Upload Design
                                                        </button>
                                                        <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                                                        </form>
                                                    @endif

                                                <div class="staff-artwork-ready-panel" data-artwork-ready-panel @if ($job->artworkVersions->isEmpty()) hidden @endif>
                                                        <div>
                                                            <strong>Design uploaded</strong>
                                                            <p>
                                                                Version <span data-artwork-version>{{ $job->artworkVersions->max('version_number') }}</span> is the latest version.
                                                                Send to the customer after checking the previews.
                                                            </p>
                                                        </div>

                                                        <form
                                                            method="POST"
                                                            action="{{ route($operationRoutePrefix.'design-jobs.mark-ready', $job) }}"
                                                            class="js-staff-confirmation-form"
                                                            data-confirm-title="Send design {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }} to the customer?"
                                                            data-confirm-message="Version {{ $job->artworkVersions->max('version_number') }} will be sent for customer review. Confirm that the previews are correct."
                                                            data-confirm-button="Yes, send for review"
                                                        >
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="staff-button staff-button-primary"
                                                            >
                                                                Send for Customer Review
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <p class="staff-work-message" data-artwork-upload-hint @if ($job->artworkVersions->isNotEmpty()) hidden @endif>
                                                        Upload source files and customer previews first.
                                                        The button to send the design to the customer will then appear.
                                                    </p>
                                            </div>
                                        @elseif ($job->status === 'DESIGN_READY')
                                            <p class="staff-work-message">
                                                Design is ready for customer review.
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                No design tasks available.
                            </div>
                        @endforelse
                    </div>

                    @if ($usesBatchArtworkUpload)
                        <form
                            id="batch-artwork-upload"
                            method="POST"
                            action="{{ route('staff.orders.design-artworks.store', $order) }}"
                            enctype="multipart/form-data"
                            class="staff-batch-artwork-form js-staff-confirmation-form js-async-artwork-upload"
                            data-confirm-title="Upload both design files?"
                            data-confirm-message="Check the source files and customer previews for the groom's and bride's packages. Both will be saved as new design versions."
                            data-confirm-button="Yes, upload both"
                        >
                            @csrf
                            <button type="submit" class="staff-button staff-button-primary">
                                Upload Both Designs
                            </button>
                            <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                        </form>
                    @endif

                    @if ($hasTwoBatchArtworkJobs)
                        <form
                            method="POST"
                            action="{{ route('staff.orders.design-jobs.mark-ready', $order) }}"
                            class="staff-batch-review-form js-staff-confirmation-form"
                            data-confirm-title="Send both designs for customer review?"
                            data-confirm-message="Both packages will be sent together. Check that the card, banner and bunting previews for each package have been uploaded and reviewed."
                            data-confirm-button="Yes, send both"
                        >
                            @csrf
                            <button type="submit" class="staff-button staff-button-primary">
                                Send Both for Customer Review
                            </button>
                        </form>
                    @endif
                </section>
            @endif

            @if ($hasFullQueueView)
            <section id="payment" class="staff-section">
                <h2 class="staff-section-title">Payments</h2>

                <div class="staff-work-grid">
                    @forelse ($order->payments as $payment)
                        <article class="staff-card">
                            <div class="staff-card-heading">
                                <h3>{{ $payment->payment_type }}</h3>

                                <span class="staff-status">
                                    {{ str_replace('_', ' ', $payment->status) }}
                                </span>
                            </div>

                            <dl class="staff-detail-list">
                                <div>
                                    <dt>Total</dt>
                                    <dd>{{ $payment->currency }} {{ $payment->amount }}</dd>
                                </div>

                                <div>
                                    <dt>Method</dt>
                                    <dd>{{ $payment->provider === 'BILLPLZ' && data_get($payment->metadata, 'billplz.payment_method') === 'DUITNOW_QR' ? 'BILLPLZ DUITNOW QR' : ($payment->provider ?: '-') }}</dd>
                                </div>

                                <div>
                                    <dt>Payment Date</dt>
                                    <dd>{{ $payment->paid_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                </div>
                            </dl>

                            @if (in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE', 'ARTWORK_CORRECTION'], true) && ! empty($payment->metadata['receipt_path']))
                                <div class="staff-payment-actions">
                                    @if (auth()->user()->isOperationManagement())
                                        <div class="staff-receipt-action-row">
                                            <div><strong>Payment proof</strong><small>Review the receipt before confirming payment.</small></div>
                                            <a class="staff-button staff-receipt-button" target="_blank" rel="noopener" href="{{ route($operationRoutePrefix.'payments.receipt', $payment) }}" title="Open payment receipt in a new tab"><span aria-hidden="true">▤</span> View Receipt <small aria-hidden="true">↗</small></a>
                                        </div>
                                    @endif
                                    @if ($payment->payment_type === 'ARTWORK_CORRECTION')
                                        <aside class="staff-correction-note staff-correction-note--payment" aria-label="Customer correction comment">
                                            <div class="staff-correction-note-heading">
                                                <span class="staff-correction-note-icon" aria-hidden="true">✎</span>
                                                <div><span>Customer comment</span><h4>Artwork Correction Instructions</h4></div>
                                            </div>
                                            <blockquote>{{ $payment->metadata['correction_comment'] ?? 'No comment provided.' }}</blockquote>
                                            <footer><span>RM10 correction charge</span></footer>
                                        </aside>
                                        @if (auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                            <form method="POST" action="{{ route($operationRoutePrefix.'payments.correction.approve', $payment) }}">
                                                @csrf
                                                <button class="staff-button staff-button-primary" type="submit">Confirm RM10 Correction Payment</button>
                                            </form>
                                            <form method="POST" action="{{ route($operationRoutePrefix.'payments.correction.reject', $payment) }}" class="staff-reject-payment-form">
                                                @csrf
                                                <label for="correction-rejection-{{ $payment->id }}">Rejection reason</label>
                                                <textarea id="correction-rejection-{{ $payment->id }}" name="rejection_reason" maxlength="1000" required></textarea>
                                                <button class="staff-button staff-button-danger" type="submit">Reject Correction Receipt</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if ($payment->payment_type === 'BOOKING_DEPOSIT' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.approve', $payment) }}" class="staff-field js-staff-confirmation-form" data-confirm-title="Confirm deposit payment?" data-confirm-message="Ensure the receipt amount is entered correctly. Once confirmed, the order will proceed to the next step." data-confirm-button="Yes, confirm deposit">
                                            @csrf
                                            <label for="payment-amount-{{ $payment->id }}">Receipt amount (RM)</label>
                                            <input id="payment-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" placeholder="Example: 100.00" required>
                                            <button class="staff-button staff-button-primary" type="submit">Confirm Deposit</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Reject deposit payment?" data-confirm-message="The customer must resubmit payment proof if the deposit is rejected. Clearly state the reason." data-confirm-button="Yes, reject deposit" data-confirm-tone="danger">
                                            @csrf
                                            <label for="rejection-reason-{{ $payment->id }}">Rejection reason</label>
                                            <textarea id="rejection-reason-{{ $payment->id }}" name="rejection_reason" rows="2" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="staff-button staff-button-danger" type="submit">Reject Deposit</button>
                                        </form>
                                    @endif
                                    @if ($payment->payment_type === 'BALANCE' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.balance.approve', $payment) }}" class="staff-field js-staff-confirmation-form" data-confirm-title="Confirm full payment?" data-confirm-message="Ensure the receipt amount is entered correctly. Once confirmed, the order will proceed to printing." data-confirm-button="Yes, confirm payment">
                                            @csrf
                                            <label for="payment-amount-{{ $payment->id }}">Receipt amount (RM)</label>
                                            <input id="payment-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" placeholder="Example: 100.00" required>
                                            <button class="staff-button staff-button-primary" type="submit">Confirm Full Payment</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.balance.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Reject full payment?" data-confirm-message="The customer must resubmit payment proof. Clearly state the reason." data-confirm-button="Yes, reject payment" data-confirm-tone="danger">
                                            @csrf
                                            <label for="balance-rejection-reason-{{ $payment->id }}">Rejection reason</label>
                                            <textarea id="balance-rejection-reason-{{ $payment->id }}" name="rejection_reason" rows="2" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="staff-button staff-button-danger" type="submit">Reject Payment</button>
                                        </form>
                                    @endif
                                    @if ($payment->status === 'FAILED' && ! empty($payment->metadata['rejection_reason']))
                                        <p class="staff-payment-rejection"><strong>Reason for rejection:</strong> {{ $payment->metadata['rejection_reason'] }}</p>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="staff-empty">
                            No payment transactions.
                        </div>
                    @endforelse
                </div>
            </section>
            @endif

            @if (($canAssignProduction ?? false) && ! $order->isAssignmentLocked() && auth()->user()->isOperationManagement() && ! request()->attributes->get('staff_overview_mode', false))
                <section class="staff-section">
                    <h2 class="staff-section-title">Production Assignment</h2>

                    <div class="staff-work-grid">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-printing', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form js-async-assignment" data-confirm-assignment="Production Staff" data-confirm-mode="{{ $order->printing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>Production Staff</h3>
                                <span class="staff-status">{{ $order->printingAssignedUser ? 'ASSIGNED' : 'UNASSIGNED' }}</span>
                            </div>
                            <label for="order-printing-assignee">{{ $order->printing_assigned_user_id ? 'Reassign Production Staff' : 'Assign Production Staff' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-printing-assignee" name="assigned_user_id" required>
                                    <option value="">Select Production Staff</option>
                                    @foreach ($productionStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected($order->printing_assigned_user_id === $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="staff-button staff-button-small">{{ $order->printing_assigned_user_id ? 'Reassign' : 'Assign' }}</button>
                            </div>
                        </form>
                    </div>

                    <h2 class="staff-section-title">Assign Operations Management to Packing</h2>

                    <div class="staff-work-grid">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-packing-fulfilment', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form js-async-assignment" data-confirm-assignment="OM (Packing & Order Fulfilment)" data-confirm-mode="{{ $order->packing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>OM: Packing &amp; Order Fulfilment</h3>
                                <span class="staff-status">{{ $order->packingAssignedUser ? 'ASSIGNED' : 'UNASSIGNED' }}</span>
                            </div>
                            <label for="order-packing-assignee">{{ $order->packing_assigned_user_id ? 'Reassign OM' : 'Assign OM' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-packing-assignee" name="assigned_user_id" required>
                                    <option value="">Select OM (Packing &amp; Order Fulfilment)</option>
                                    @foreach ($packingStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected($order->packing_assigned_user_id === $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="staff-button staff-button-small">{{ $order->packing_assigned_user_id ? 'Reassign' : 'Assign' }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            @if ($order->relationLoaded('printJobs'))
                <section id="production" class="staff-section">
                    <h2 class="staff-section-title">Production</h2>

                    <div class="staff-work-grid">
                        @forelse ($order->printJobs as $job)
                            <article class="staff-card">
                                <div class="staff-card-heading">
                                    <h3>{{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}</h3>

                                    <span class="staff-status">
                                        {{ str_replace('_', ' ', $job->status) }}
                                    </span>
                                </div>

                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Quantity</dt>
                                        <dd>{{ $job->quantity ?? $order->card_quantity ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Couple Names</dt>
                                        <dd>
                                            @php($couple = $order->couples->firstWhere('couple_number', 1))
                                            {{ collect([
                                                $couple?->groom_abbreviation ?: $couple?->groom_name,
                                                $couple?->bride_abbreviation ?: $couple?->bride_name,
                                            ])->filter()->join(' & ') ?: '-' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Hanger Label</dt>
                                        <dd>{{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Assigned</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Not assigned yet' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Started</dt>
                                        <dd>{{ $job->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Printed</dt>
                                        <dd>{{ $job->printed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Progress Updated</dt>
                                        <dd>{{ $job->progress_updated_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>
                                </dl>

                                @if (filled($job->progress_files))
                                    <div class="staff-detail-group">
                                        <h4>Production Progress Files</h4>

                                        <div class="staff-contact-list">
                                            @foreach ($job->progress_files as $file)
                                                <div class="staff-contact-row">
                                                    <span>{{ $file['original_name'] ?? 'Progress file' }}</span>
                                                    <div class="staff-contact-actions">
                                                        <a href="{{ route($operationRoutePrefix.'print-jobs.progress-files.show', ['printJob' => $job, 'file' => $loop->index]) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">
                                                            View · {{ isset($file['uploaded_at']) ? \Illuminate\Support\Carbon::parse($file['uploaded_at'])->timezone(config('app.display_timezone'))->format('Y-m-d H:i') : '-' }}
                                                        </a>
                                                        @if (($file['customer_visible'] ?? false) === true)
                                                            <span class="staff-muted-text">Shared with customer</span>
                                                        @elseif (auth()->user()->isOperationManagement() || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRODUCTION) && $job->assigned_user_id === auth()->id()))
                                                            <form method="POST" action="{{ route($operationRoutePrefix.'print-jobs.progress-files.visibility', ['printJob' => $job, 'file' => $loop->index]) }}">
                                                                @csrf
                                                                <input type="hidden" name="customer_visible" value="1">
                                                                <button type="submit" class="staff-button staff-button-small staff-button-primary">Share with customer</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRODUCTION) && $job->assigned_user_id === auth()->id())
                                    @if ($job->status === 'READY_FOR_PRINT')
                                        <form
                                            method="POST"
                                            action="{{ route($operationRoutePrefix.'print-jobs.start', $job) }}"
                                            class="staff-workflow-form js-staff-confirmation-form"
                                            data-confirm-title="Start printing {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}?"
                                            data-confirm-message="The print quantity is {{ $job->quantity ?? $order->card_quantity ?? '-' }} cards. Confirm the package and quantity are correct."
                                            data-confirm-button="Yes, start printing"
                                        >
                                            @csrf
                                            <button type="submit" class="staff-button staff-button-primary">Start Printing</button>
                                        </form>
                                    @elseif ($job->status === 'WAITING_FOR_PAYMENT')
                                        <p class="staff-work-message">Awaiting full payment confirmation before printing can begin.</p>
                                    @elseif ($job->status === 'PRINTING')
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('staff.print-jobs.progress-files.store', $job) }}" class="staff-packing-complete-form js-staff-confirmation-form js-async-progress-upload" data-confirm-title="Upload printing progress {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }}?" data-confirm-message="The selected image or PDF will be saved as proof of current printing progress." data-confirm-button="Yes, upload progress">
                                            @csrf
                                            <label for="print-progress-{{ $job->id }}">Upload production progress</label>
                                            <div class="staff-file-picker">
                                                <input id="print-progress-{{ $job->id }}" type="file" name="progress_files[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" multiple required>
                                                <button type="button" class="staff-file-cancel" hidden aria-controls="print-progress-{{ $job->id }}">Cancel</button>
                                            </div>
                                            <p class="staff-muted-text">Upload images or PDFs showing production progress. Maximum 10 files, 20 MB each.</p>
                                            <label class="staff-progress-visibility">
                                                <input type="checkbox" name="customer_visible" value="1">
                                                Share these production proofs with the customer
                                            </label>
                                            <button type="submit" class="staff-button staff-button-secondary">Upload Progress</button>
                                            <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'print-jobs.mark-printed', $job) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Mark printing {{ $job->side === 'LELAKI' ? 'Groom' : 'Bride' }} as complete?" data-confirm-message="Make sure all {{ $job->quantity ?? $order->card_quantity ?? '-' }} cards have been printed and checked before continuing." data-confirm-button="Yes, mark complete" data-confirm-tone="danger">@csrf<button type="submit" class="staff-button staff-button-primary">Mark Printing Complete</button></form>
                                    @elseif ($job->status === 'PRINTED')
                                        <p class="staff-work-message">Printing for this package is complete.</p>
                                    @endif
                                @endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                No printing tasks available.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView && $order->relationLoaded('packingJob') && $order->packingJob)
                <section id="packing" class="staff-section">
                    <h2 class="staff-section-title">Packing</h2>

                    <article class="staff-card">
                        <div class="staff-card-heading">
                            <h3>Packing Work</h3>

                            <span class="staff-status">
                                {{ str_replace('_', ' ', $order->packingJob->status) }}
                            </span>
                        </div>

                        <dl class="staff-detail-list">
                            <div>
                                <dt>Assigned</dt>
                                <dd>{{ $order->packingJob->assignedUser?->name ?? 'Not assigned yet' }}</dd>
                            </div>

                            <div>
                                <dt>Started</dt>
                                <dd>{{ $order->packingJob->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Packed</dt>
                                <dd>{{ $order->packingJob->packed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>
                        </dl>
                        @if ($order->packingJob->items->isNotEmpty())
                            <div class="staff-detail-group">
                                <h4>Packing Item List</h4>

                                <div class="staff-contact-list staff-packing-checklist">
                                    @foreach ($order->packingJob->items as $item)
                                        <div @class(['staff-contact-row', 'is-verified' => $item->verified_present])>
                                            <div class="staff-packing-item-name">
                                                <span>Package {{ $item->side === 'LELAKI' ? 'Groom' : 'Bride' }}</span>
                                                <small>{{ $item->verified_present ? 'Item checked and confirmed complete' : 'Not yet checked' }}</small>
                                            </div>

                                            @if ($item->verified_present)
                                                <strong class="staff-verified-badge"><span aria-hidden="true">✓</span> Confirmed</strong>
                                            @elseif (auth()->user()->isOperationManagement() && $order->packingJob->status === 'PACKING')
                                                <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.items.verify', ['packingJob' => $order->packingJob, 'packingItem' => $item]) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Verify item {{ $item->side === 'LELAKI' ? 'Groom' : 'Bride' }}?" data-confirm-message="Make sure all cards for the package {{ $item->side === 'LELAKI' ? 'Groom' : 'Bride' }} are present, complete and in good condition before confirming." data-confirm-button="Yes, verify item">
                                                    @csrf
                                                    <button type="submit" class="staff-button staff-verify-button"><span aria-hidden="true">✓</span> Verify Item</button>
                                                </form>
                                            @else
                                                <strong class="staff-unverified-badge">Not yet verified</strong>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($order->packingJob->proofs->isNotEmpty())
                            <div class="staff-proof-status">
                                <strong>Packing photos:</strong>

                                @if (auth()->user()->isOperationManagement())
                                    @foreach ($order->packingJob->proofs as $packingProof)
                                        <a href="{{ route($operationRoutePrefix.'packing-jobs.proof-files.show', ['packingJob' => $order->packingJob, 'packingJobProof' => $packingProof]) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">View {{ $packingProof->original_name }}</a>
                                    @endforeach
                                @endif
                            </div>
                        @elseif ($order->packingJob->proof_storage_path)
                            <div class="staff-proof-status">
                                <strong>Packing proof:</strong> {{ $order->packingJob->proof_original_name ?? 'Uploaded' }}

                                @if (auth()->user()->isOperationManagement())
                                    <a href="{{ route($operationRoutePrefix.'packing-jobs.proof.show', $order->packingJob) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">View</a>
                                @endif
                            </div>
                        @endif

                        @if (auth()->user()->isOperationManagement())
                            @if ($order->packingJob->status === 'READY_FOR_PACKING')
                                <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.start', $order->packingJob) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Start packing?" data-confirm-message="The packing start time will be recorded. Make sure all printed items are ready to check and pack." data-confirm-button="Yes, start packing">@csrf<button type="submit" class="staff-button staff-button-primary">Start Packing</button></form>
                            @endif
                            @if ($order->packingJob->status === 'PACKING')
                                @if ($order->packingJob->items->every(fn ($item) => $item->verified_present))
                                    <form method="POST" enctype="multipart/form-data" action="{{ route($operationRoutePrefix.'packing-jobs.mark-packed', $order->packingJob) }}" class="staff-packing-complete-form js-staff-confirmation-form" data-confirm-title="Upload proof and mark packing complete?" data-confirm-message="{{ $order->fulfilment?->method === 'COURIER' ? 'Check that the packing photo, courier name and tracking number are correct. Packing will be marked complete after dispatch.' : 'Check that the photo shows all items packed. Packing will be marked complete for self pickup.' }}" data-confirm-button="Yes, mark packing complete">
                                        @csrf
                                        <label for="packing-proof">Photo proof of packed items</label>
                                        <div class="staff-multi-file-picker" data-file-kind="packing proof photos">
                                            <input id="packing-proof" type="file" name="packing_proofs[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required>
                                            <button type="button" class="staff-file-add" aria-controls="packing-proof">+ Add Photos</button>
                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                        </div>
                                        <p class="staff-field-help">Upload up to 10 photos, maximum 10 MB each.</p>
                                        @if ($order->fulfilment?->method === 'COURIER')
                                            <label for="courier-provider">Courier Name</label><input id="courier-provider" name="courier_provider" value="{{ old('courier_provider') }}" placeholder="Example: Pos Laju" required>
                                            <label for="tracking-number">Tracking Number</label><input id="tracking-number" name="tracking_number" value="{{ old('tracking_number') }}" required>
                                        @endif
                                        <button type="submit" class="staff-button staff-button-primary">Upload Proof & Complete Packing</button>
                                    </form>
                                @endif
                            @endif
                        @endif
                    </article>
                </section>
            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_OM) && $order->packing_assigned_user_id === auth()->id())
                <section id="packing" class="staff-section">
                    <h2 class="staff-section-title">Packing</h2>
                    <div class="staff-empty">
                        Packing work will be created automatically after all printing is complete.
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView)
            <section id="fulfilment" class="staff-section">
                <h2 class="staff-section-title">Order Fulfilment</h2>

                <article class="staff-card">
                    @if ($order->fulfilment)
                        <dl class="staff-detail-list">
                            <div>
                                <dt>Method</dt>
                                <dd>{{ $order->fulfilment->method }}</dd>
                            </div>

                            <div>
                                <dt>Recipient</dt>
                                <dd>{{ $order->fulfilment->recipient_name ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Phone</dt>
                                <dd>{{ $order->fulfilment->recipient_phone ?? '-' }}</dd>
                            </div>

                            <div class="staff-detail-wide">
                                <dt>Delivery Address</dt>
                                <dd>{{ $order->fulfilment->shipping_address ?? '-' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="staff-muted-text">
                            No fulfilment details available.
                        </p>
                    @endif

                    @if ($order->fulfilmentJob)
                        <div class="staff-detail-group">
                            <h4>Order Fulfilment Work</h4>

                            <dl class="staff-detail-list">
                                <div>
                                    <dt>Job Status</dt>
                                    <dd>{{ $order->fulfilmentJob->status }}</dd>
                                </div>

                                <div>
                                    <dt>Courier</dt>
                                    <dd>{{ $order->fulfilmentJob->courier_provider ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Tracking Number</dt>
                                    <dd>{{ $order->fulfilmentJob->tracking_number ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Completion Reference</dt>
                                    <dd>{{ $order->fulfilmentJob->completion_reference ?? '-' }}</dd>
                                </div>
                            </dl>
                            @if (
                                auth()->user()->isOperationManagement()
                                && $order->fulfilmentJob->method === 'COURIER'
                                && $order->fulfilmentJob->status === 'COMPLETED'
                                && $order->packingJob?->proof_storage_path
                            )
                                <div class="staff-proof-status">
                                    <strong>Courier Proof:</strong> {{ $order->packingJob->proof_original_name ?? 'Uploaded' }}
                                    <a href="{{ route($operationRoutePrefix.'packing-jobs.proof.show', $order->packingJob) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">View Proof</a>
                                </div>
                            @endif
                            @if (
                                $order->packingJob
                                && $order->packingJob->assigned_user_id === auth()->id()
                                && $order->fulfilmentJob->status === 'READY'
                            )
                                <div class="staff-detail-group">
                                    @if ($order->fulfilmentJob->method === 'PICKUP')
                                        <h4>Pickup Completion</h4>

                                        <form
                                            method="POST"
                                            action="{{ route($operationRoutePrefix.'packing-jobs.collect-pickup', $order->packingJob) }}"
                                            class="staff-workflow-form"
                                        >
                                            @csrf

                                            <label for="completion-reference">
                                                Completion Reference
                                            </label>

                                            <input
                                                id="completion-reference"
                                                type="text"
                                                name="completion_reference"
                                                value="{{ old('completion_reference') }}"
                                                maxlength="255"
                                                placeholder="Contoh: PICKUP-001"
                                            >

                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="complete"
                                                    value="1"
                                                    required
                                                >
                                                COMPLETE - Cards have been collected by the customer
                                            </label>

                                            <button
                                                type="submit"
                                                class="staff-button staff-button-primary"
                                            >
                                                Mark Order Collected
                                            </button>
                                        </form>

                                    @elseif ($order->fulfilmentJob->method === 'COURIER')
                                        <h4>Courier Handover</h4>

                                        <form
                                            method="POST"
                                            enctype="multipart/form-data"
                                            action="{{ route($operationRoutePrefix.'packing-jobs.complete-courier', $order->packingJob) }}"
                                            class="staff-packing-complete-form js-staff-confirmation-form"
                                            data-confirm-courier
                                            data-confirm-tone="danger"
                                        >
                                            @csrf

                                            <label for="courier-packing-proof">
                                                Photo of parcel with tracking label
                                            </label>

                                            <div class="staff-file-picker">
                                            <input
                                                id="courier-packing-proof"
                                                type="file"
                                                name="packing_proof"
                                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                required
                                            >
                                                <button type="button" class="staff-file-cancel" hidden aria-controls="courier-packing-proof">Cancel</button>
                                            </div>

                                            <label for="courier-provider">
                                                Courier Name
                                            </label>

                                            <input
                                                id="courier-provider"
                                                type="text"
                                                name="courier_provider"
                                                value="{{ old('courier_provider', $order->fulfilmentJob->courier_provider) }}"
                                                placeholder="Example: Pos Laju"
                                                required
                                            >

                                            <label for="tracking-number">
                                                Tracking Number
                                            </label>

                                            <input
                                                id="tracking-number"
                                                type="text"
                                                name="tracking_number"
                                                value="{{ old('tracking_number', $order->fulfilmentJob->tracking_number) }}"
                                                required
                                            >

                                            <label class="staff-courier-confirmation">
                                                <input
                                                    type="checkbox"
                                                    name="complete"
                                                    value="1"
                                                    required
                                                >
                                                COMPLETE - Parcel has been handed to the courier
                                            </label>

                                            <button
                                                type="submit"
                                                class="staff-button staff-button-primary"
                                            >
                                                Confirm Courier Handover
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            </section>
            @endif
        </main>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-proof-preview-select]').forEach(function (select) {
            const link = select.closest('.staff-proof-status')?.querySelector('.js-proof-preview-link');

            if (!link) {
                return;
            }

            select.addEventListener('change', function () {
                const selectedOption = select.selectedOptions[0];

                if (selectedOption?.dataset.proofUrl) {
                    link.href = selectedOption.dataset.proofUrl;
                }
            });
        });

        document.querySelectorAll('.staff-multi-file-picker').forEach(function (picker) {
            const input = picker.querySelector('input[type="file"]');
            const addButton = picker.querySelector('.staff-file-add');
            const list = picker.querySelector('.staff-selected-files');
            let selectedFiles = [];

            function fileKey(file) {
                return [file.name, file.size, file.lastModified].join(':');
            }

            function syncInput() {
                const transfer = new DataTransfer();
                selectedFiles.forEach(function (file) {
                    transfer.items.add(file);
                });
                input.files = transfer.files;
            }

            function renderFiles() {
                list.replaceChildren();

                selectedFiles.forEach(function (file, index) {
                    const item = document.createElement('li');
                    const details = document.createElement('span');
                    const name = document.createElement('strong');
                    const size = document.createElement('small');
                    const remove = document.createElement('button');

                    name.textContent = file.name;
                    size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                    details.append(name, size);

                    remove.type = 'button';
                    remove.className = 'staff-file-delete';
                    remove.textContent = 'Remove';
                    remove.setAttribute('aria-label', 'Remove ' + file.name);
                    remove.addEventListener('click', function () {
                        selectedFiles.splice(index, 1);
                        syncInput();
                        renderFiles();
                    });

                    item.append(details, remove);
                    list.append(item);
                });

                picker.classList.toggle('has-files', selectedFiles.length > 0);
            }

            addButton.addEventListener('click', function () {
                input.click();
            });

            input.addEventListener('change', function () {
                const knownFiles = new Set(selectedFiles.map(fileKey));

                Array.from(input.files).forEach(function (file) {
                    if (!knownFiles.has(fileKey(file))) {
                        selectedFiles.push(file);
                        knownFiles.add(fileKey(file));
                    }
                });

                syncInput();
                renderFiles();
            });

            renderFiles();
        });

        document.querySelectorAll('.staff-file-picker').forEach(function (picker) {
            const input = picker.querySelector('input[type="file"]');
            const cancelButton = picker.querySelector('.staff-file-cancel');

            function updateCancelButton() {
                cancelButton.hidden = input.files.length === 0;
            }

            input.addEventListener('change', updateCancelButton);
            cancelButton.addEventListener('click', function () {
                input.value = '';
                updateCancelButton();
                input.focus();
            });

            updateCancelButton();
        });
    });
</script>
@include('staff.partials.logout-confirmation')
<dialog id="staff-action-confirmation-dialog" class="logout-confirmation-dialog" aria-labelledby="staff-action-confirmation-title">
    <div class="logout-confirmation-icon" aria-hidden="true">?</div>
    <h2 id="staff-action-confirmation-title">Confirm action?</h2>
    <p id="staff-action-confirmation-message"></p>
    <div class="logout-confirmation-actions">
        <button id="cancel-staff-action" type="button">No, go back</button>
        <button id="confirm-staff-action" type="button">Yes, continue</button>
    </div>
</dialog>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('.js-staff-confirmation-form');
        const dialog = document.getElementById('staff-action-confirmation-dialog');
        const title = document.getElementById('staff-action-confirmation-title');
        const message = document.getElementById('staff-action-confirmation-message');
        const confirmButton = document.getElementById('confirm-staff-action');
        const cancelButton = document.getElementById('cancel-staff-action');
        let pendingForm = null;

        if (!forms.length || !dialog || !title || !message || !confirmButton || !cancelButton) {
            return;
        }

        forms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                pendingForm = form;

                if (form.dataset.confirmCourier !== undefined) {
                    const courierName = form.querySelector('[name="courier_provider"]').value.trim();
                    const trackingNumber = form.querySelector('[name="tracking_number"]').value.trim();

                    title.textContent = 'Confirm handover to ' + courierName + '?';
                    message.textContent = 'Tracking number ' + trackingNumber + ' will be shown to the customer and the parcel marked as handed over. Check that the proof and tracking details are correct.';
                    confirmButton.textContent = 'Yes, confirm handover';
                } else if (form.dataset.confirmAssignment) {
                    const assignee = form.querySelector('[name="assigned_user_id"]');
                    const assigneeName = assignee.options[assignee.selectedIndex].text.trim();
                    const isReassignment = form.dataset.confirmMode === 'reassign';
                    const actionLabel = isReassignment ? 'Change' : 'Assign';

                    title.textContent = actionLabel + ' staff ' + form.dataset.confirmAssignment + '?';
                    message.textContent = 'This task will be assigned to ' + assigneeName + '. Make sure the selected staff member is correct.';
                    confirmButton.textContent = 'Ya, ' + actionLabel.toLowerCase() + ' staff';
                } else {
                    title.textContent = form.dataset.confirmTitle;
                    message.textContent = form.dataset.confirmMessage;
                    confirmButton.textContent = form.dataset.confirmButton;
                }

                confirmButton.classList.toggle('is-danger', form.dataset.confirmTone === 'danger');
                confirmButton.classList.toggle('is-primary', form.dataset.confirmTone !== 'danger');
                dialog.showModal();
            });
        });

        cancelButton.addEventListener('click', function () {
            pendingForm = null;
            dialog.close();
        });

        function showAssignmentNotice(form, message, isError) {
            let notice = form.querySelector('.staff-assignment-notice');

            if (!notice) {
                notice = document.createElement('p');
                notice.className = 'staff-assignment-notice';
                notice.setAttribute('role', 'status');
                form.appendChild(notice);
            }

            notice.textContent = message;
            notice.classList.toggle('is-error', isError);
        }

        async function submitAssignment(form) {
            const submitButton = form.querySelector('[type="submit"]');
            const originalButtonText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
                    throw new Error(errors || payload.message || 'The assignment could not be saved.');
                }

                form.dataset.confirmMode = 'reassign';
                const label = form.querySelector('label');
                const status = form.querySelector('.staff-status');

                if (label) {
                        label.textContent = label.textContent.replace(/^Assign\b/i, 'Change assignment');
                }

                if (status) {
                    status.textContent = 'ASSIGNED';
                }

                    submitButton.textContent = 'Change Assignment';
                showAssignmentNotice(form, payload.message || 'Staff member assigned successfully.', false);
            } catch (error) {
                submitButton.textContent = originalButtonText;
                showAssignmentNotice(form, error.message || 'The assignment could not be saved.', true);
            } finally {
                submitButton.disabled = false;
            }
        }

        function showUploadNotice(form, message, isError) {
            const notice = form.querySelector('.staff-upload-notice');

            if (!notice) {
                return;
            }

            notice.hidden = false;
            notice.textContent = message;
            notice.classList.toggle('is-error', isError);
        }

        async function submitProgressUpload(form) {
            const submitButton = form.querySelector('[type="submit"]');
            const fileInput = form.querySelector('[name="progress_files[]"]');
            const cancelButton = form.querySelector('.staff-file-cancel');
            const originalButtonText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Uploading...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
                    throw new Error(errors || payload.message || 'Could not upload progress.');
                }

                const fileNames = (payload.files || []).map(function (file) {
                    return file.original_name;
                }).filter(Boolean);

                if (fileInput) {
                    fileInput.value = '';
                }
                if (cancelButton) {
                    cancelButton.hidden = true;
                }

                showUploadNotice(
                    form,
                    (payload.message || 'Production progress uploaded successfully.')
                        + (fileNames.length ? ' ' + fileNames.join(', ') : ''),
                    false
                );
            } catch (error) {
                showUploadNotice(form, error.message || 'Could not upload progress.', true);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }

        async function submitArtworkUpload(form) {
            const submitButton = form.querySelector('[type="submit"]');
            const originalButtonText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Uploading...';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(function () { return {}; });

                if (!response.ok) {
                    const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
                    throw new Error(errors || payload.message || 'Could not upload the design.');
                }

                const selector = form.id
                    ? 'input[type="file"][form="' + form.id + '"]'
                    : '';
                const inputs = [
                    ...form.querySelectorAll('input[type="file"]'),
                    ...(selector ? document.querySelectorAll(selector) : []),
                ];

                inputs.forEach(function (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('change'));
                });

                const artworkJobs = payload.jobs || (payload.job_ids || (payload.job_id ? [payload.job_id] : []))
                    .map(function (jobId) {
                        return { id: jobId, version_number: payload.version_number };
                    });

                artworkJobs.forEach(function (artworkJob) {
                    const jobCard = document.querySelector('[data-design-job-id="' + artworkJob.id + '"]');

                    if (!jobCard) {
                        return;
                    }

                    const reviewPanel = jobCard.querySelector('[data-artwork-ready-panel]');
                    const uploadHint = jobCard.querySelector('[data-artwork-upload-hint]');
                    const version = reviewPanel?.querySelector('[data-artwork-version]');
                    const reviewForm = reviewPanel?.querySelector('.js-staff-confirmation-form');

                    if (version && artworkJob.version_number) {
                        version.textContent = artworkJob.version_number;
                    }

                    if (reviewForm && artworkJob.version_number) {
                        reviewForm.dataset.confirmMessage = 'Version ' + artworkJob.version_number
                            + ' will be sent for customer review. Make sure the previews have been checked and are correct.';
                    }

                    if (reviewPanel) {
                        reviewPanel.hidden = false;
                    }
                    if (uploadHint) {
                        uploadHint.hidden = true;
                    }
                });

                showUploadNotice(form, payload.message || 'Design uploaded successfully.', false);
            } catch (error) {
                showUploadNotice(form, error.message || 'Could not upload the design.', true);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }

        confirmButton.addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.textContent = 'Processing...';

            if (pendingForm.classList.contains('js-async-assignment')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Yes, continue';
                submitAssignment(form);
                return;
            }

            if (pendingForm.classList.contains('js-async-progress-upload')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Yes, continue';
                submitProgressUpload(form);
                return;
            }

            if (pendingForm.classList.contains('js-async-artwork-upload')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Yes, continue';
                submitArtworkUpload(form);
                return;
            }

            pendingForm.submit();
        });
    });
</script>
</body>
</html>
