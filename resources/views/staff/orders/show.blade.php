<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $order->order_id }} - KKK OS Staff</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : 'Staff Operations' }} · Order Detail</p><h1>{{ $order->order_id }}</h1></div>
                <form method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit" class="staff-button staff-button-small">Log Keluar</button></form>
            </header>

        <main class="staff-main">
            @if (session('status'))
    <div class="staff-alert staff-alert-success">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="staff-alert staff-alert-error">
        <strong>Assignment could not be saved.</strong>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
            <div class="staff-page-header">
                <div>
                    <a href="{{ route('staff.orders.index') }}" class="staff-back-link">
                        &larr; Orders
                    </a>

                    <div class="staff-detail-heading">
                        <h1>{{ $order->order_id }}</h1>

                        <span class="staff-status">
                            {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>

                    <p>
                        {{ $order->customer_name ?: 'Customer name unavailable' }}
                    </p>
                </div>

                <div class="staff-page-count">
                    {{ $order->package_count }}
                    {{ $order->package_count == 1 ? 'package' : 'packages' }}
                </div>
            </div>

            <div class="staff-detail-grid">
                <section class="staff-card">
                    <h2>Order Summary</h2>

                    <dl class="staff-detail-list">
                        <div>
                            <dt>Status</dt>
                            <dd>{{ str_replace('_', ' ', $order->status) }}</dd>
                        </div>

                        <div>
                            <dt>Packages</dt>
                            <dd>{{ $order->package_count }}</dd>
                        </div>

                        <div>
                            <dt>Card Quantity</dt>
                            <dd>{{ $order->card_quantity ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Booking Payment</dt>
                            <dd>{{ $order->booking_payment_status ?? '-' }}</dd>
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
                    </dl>
                </section>
            </div>

            <section class="staff-section">
                <h2 class="staff-section-title">Packages</h2>

                <div class="staff-package-grid">
                    @foreach ($order->packageSides as $packageSide)
                        <article class="staff-card staff-package-card">
                            <div class="staff-card-heading">
                                <h3>{{ $packageSide->side }}</h3>

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
                                            <dt>Theme</dt>
                                            <dd>{{ $packageSide->design->theme ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Design Code</dt>
                                            <dd>{{ $packageSide->design->design_code ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Card Title</dt>
                                            <dd>{{ $packageSide->design->card_title ?? '-' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @if ($packageSide->parents)
                                <div class="staff-detail-group">
                                    <h4>Parents</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Father</dt>
                                            <dd>{{ $packageSide->parents->father_name ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Mother</dt>
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
                                            <dd>{{ $packageSide->event->day_name ?? '-' }}</dd>
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
                                            <dt>Bersanding</dt>
                                            <dd>{{ $packageSide->event->bersanding_time ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Venue</dt>
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
                                        <h4>Contacts</h4>

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

            @if ($order->relationLoaded('designJobs'))
                <section class="staff-section">
                    <h2 class="staff-section-title">Design Work</h2>

                    <div class="staff-work-grid">
                        @forelse ($order->designJobs as $job)
                            <article class="staff-card">
                                <div class="staff-card-heading">
                                    <h3>{{ $job->side }}</h3>

                                    <span class="staff-status">
                                        {{ str_replace('_', ' ', $job->status) }}
                                    </span>
                                </div>

                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Assigned</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Unassigned' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Started</dt>
                                        <dd>{{ $job->started_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Ready</dt>
                                        <dd>{{ $job->design_ready_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Artwork Versions</dt>
                                        <dd>{{ $job->artworkVersions->count() }}</dd>
                                    </div>
                                </dl>
                                @if (auth()->user()->isAdmin()
                                    || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
                                    && $job->assigned_user_id === auth()->id())
                                    )
                                    <div class="staff-design-actions">
                                        @if ($job->status === 'READY_FOR_DESIGN')
                                            <form
                                                method="POST"
                                                action="{{ route('staff.design-jobs.start', $job) }}"
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
                                                action="{{ route('staff.design-jobs.resume-correction', $job) }}"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="staff-button staff-button-primary"
                                                >
                                                    Resume Correction
                                                </button>
                                            </form>
                                        @elseif ($job->status === 'DESIGN_IN_PROGRESS')
                                            <div class="staff-design-upload">
                                                <div class="staff-design-upload-heading">
                                                    <h4>Upload Artwork Version</h4>

                                                    <p>
                                                        Upload the editable source artwork and a separate
                                                        customer preview.
                                                    </p>
                                                </div>

                                                <form
                                                    method="POST"
                                                    action="{{ route('staff.design-jobs.artwork.store', $job) }}"
                                                    enctype="multipart/form-data"
                                                    class="staff-artwork-form"
                                                >
                                                    @csrf

                                                    <div class="staff-field">
                                                        <label for="source-artwork-{{ $job->id }}">
                                                            Source Artwork
                                                        </label>

                                                        <div class="staff-artwork-picker">
                                                        <input
                                                            id="source-artwork-{{ $job->id }}"
                                                            type="file"
                                                            name="source_artwork"
                                                            accept=".psd,.pdf"
                                                            required
                                                        >
                                                            <button type="button" class="staff-artwork-cancel" hidden aria-controls="source-artwork-{{ $job->id }}">Batal</button>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            PSD or PDF. Maximum 100 MB.
                                                        </span>
                                                    </div>

                                                    <div class="staff-field">
                                                        <label for="customer-preview-{{ $job->id }}">
                                                            Customer Preview
                                                        </label>

                                                        <div class="staff-artwork-picker">
                                                        <input
                                                            id="customer-preview-{{ $job->id }}"
                                                            type="file"
                                                            name="customer_preview"
                                                            accept=".jpg,.jpeg,.png,.pdf"
                                                            required
                                                        >
                                                            <button type="button" class="staff-artwork-cancel" hidden aria-controls="customer-preview-{{ $job->id }}">Batal</button>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            JPG, PNG or PDF. Maximum 20 MB.
                                                        </span>
                                                    </div>

                                                    <div class="staff-field">
                                                        <label for="internal-note-{{ $job->id }}">
                                                            Internal Note
                                                            <span class="staff-optional">(optional)</span>
                                                        </label>

                                                        <textarea
                                                            id="internal-note-{{ $job->id }}"
                                                            name="internal_note"
                                                            rows="3"
                                                            maxlength="5000"
                                                        >{{ old('internal_note') }}</textarea>
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        class="staff-button staff-button-primary"
                                                    >
                                                        Upload Artwork
                                                    </button>
                                                </form>

                                                @if ($job->artworkVersions->isNotEmpty())
                                                    <div class="staff-artwork-ready-panel">
                                                        <div>
                                                            <strong>Artwork telah dimuat naik</strong>
                                                            <p>
                                                                Versi {{ $job->artworkVersions->max('version_number') }} ialah versi terkini.
                                                                Hantar kepada customer apabila preview sudah diperiksa.
                                                            </p>
                                                        </div>

                                                        <form
                                                            method="POST"
                                                            action="{{ route('staff.design-jobs.mark-ready', $job) }}"
                                                        >
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="staff-button staff-button-primary"
                                                            >
                                                                Hantar Untuk Semakan Customer
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <p class="staff-work-message">
                                                        Muat naik source artwork dan customer preview terlebih dahulu.
                                                        Selepas itu, butang untuk menghantar artwork kepada customer akan dipaparkan.
                                                    </p>
                                                @endif
                                            </div>
                                        @elseif ($job->status === 'DESIGN_READY')
                                            <p class="staff-work-message">
                                                Artwork is ready for customer review.
                                            </p>
                                        @endif
                                    </div>
                                @endif
                                @if (auth()->user()->isAdmin() && ! request()->attributes->get('staff_overview_mode', false))
    <form
        method="POST"
        action="{{ route('staff.design-jobs.assign', $job) }}"
        class="staff-assignment-form"
    >
        @csrf

        <label for="design-assignee-{{ $job->id }}">
            {{ $job->assigned_user_id ? 'Reassign Designer' : 'Assign Designer' }}
        </label>

        <div class="staff-assignment-controls">
            <select
                id="design-assignee-{{ $job->id }}"
                name="assigned_user_id"
                required
            >
                <option value="">Select designer</option>

                @foreach ($designers as $designer)
                    <option
                        value="{{ $designer->id }}"
                        @selected($job->assigned_user_id === $designer->id)
                    >
                        {{ $designer->name }}
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="staff-button staff-button-small"
            >
                {{ $job->assigned_user_id ? 'Reassign' : 'Assign' }}
            </button>
        </div>
    </form>
@endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                No design jobs available.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="staff-section">
                <h2 class="staff-section-title">Payment</h2>

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
                                    <dt>Amount</dt>
                                    <dd>{{ $payment->currency }} {{ $payment->amount }}</dd>
                                </div>

                                <div>
                                    <dt>Provider</dt>
                                    <dd>{{ $payment->provider ?: '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Paid At</dt>
                                    <dd>{{ $payment->paid_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                </div>
                            </dl>

                            @if (in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE'], true) && ! empty($payment->metadata['receipt_path']))
                                <div class="staff-payment-actions">
                                    <a class="staff-button staff-button-small" target="_blank" rel="noopener" href="{{ route('staff.payments.receipt', $payment) }}">Lihat Resit</a>
                                    @if ($payment->payment_type === 'BOOKING_DEPOSIT' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route('staff.payments.deposit.approve', $payment) }}">@csrf<button class="staff-button staff-button-primary" type="submit">Sahkan Deposit</button></form>
                                        <form method="POST" action="{{ route('staff.payments.deposit.reject', $payment) }}" class="staff-reject-payment-form">
                                            @csrf
                                            <label for="rejection-reason-{{ $payment->id }}">Sebab penolakan</label>
                                            <textarea id="rejection-reason-{{ $payment->id }}" name="rejection_reason" rows="2" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="staff-button staff-button-danger" type="submit">Tolak Deposit</button>
                                        </form>
                                    @endif
                                    @if ($payment->payment_type === 'BALANCE' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route('staff.payments.balance.approve', $payment) }}">@csrf<button class="staff-button staff-button-primary" type="submit">Sahkan Bayaran Penuh</button></form>
                                        <form method="POST" action="{{ route('staff.payments.balance.reject', $payment) }}" class="staff-reject-payment-form">
                                            @csrf
                                            <label for="balance-rejection-reason-{{ $payment->id }}">Sebab penolakan</label>
                                            <textarea id="balance-rejection-reason-{{ $payment->id }}" name="rejection_reason" rows="2" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="staff-button staff-button-danger" type="submit">Tolak Bayaran</button>
                                        </form>
                                    @endif
                                    @if ($payment->status === 'FAILED' && ! empty($payment->metadata['rejection_reason']))
                                        <p class="staff-payment-rejection"><strong>Sebab ditolak:</strong> {{ $payment->metadata['rejection_reason'] }}</p>
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

            @if ($order->relationLoaded('printJobs'))
                <section class="staff-section">
                    <h2 class="staff-section-title">Printing</h2>

                    <div class="staff-work-grid">
                        @forelse ($order->printJobs as $job)
                            <article class="staff-card">
                                <div class="staff-card-heading">
                                    <h3>{{ $job->side }}</h3>

                                    <span class="staff-status">
                                        {{ str_replace('_', ' ', $job->status) }}
                                    </span>
                                </div>

                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Quantity</dt>
                                        <dd>{{ $job->quantity }}</dd>
                                    </div>

                                    <div>
                                        <dt>Assigned</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Unassigned' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Started</dt>
                                        <dd>{{ $job->started_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Printed</dt>
                                        <dd>{{ $job->printed_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>
                                </dl>
                                @if (auth()->user()->isAdmin() || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRINTING) && $job->assigned_user_id === auth()->id()))
                                    @if ($job->status === 'READY_FOR_PRINT')
                                        <form method="POST" action="{{ route('staff.print-jobs.start', $job) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Start Printing</button></form>
                                    @elseif ($job->status === 'PRINTING')
                                        <form method="POST" action="{{ route('staff.print-jobs.mark-printed', $job) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Mark Printed</button></form>
                                    @elseif ($job->status === 'PRINTED')
                                        <p class="staff-work-message">Cetakan untuk pakej ini telah siap.</p>
                                    @endif
                                @endif
                                @if (auth()->user()->isAdmin() && ! request()->attributes->get('staff_overview_mode', false))
    <form
        method="POST"
        action="{{ route('staff.print-jobs.assign', $job) }}"
        class="staff-assignment-form"
    >
        @csrf

        <label for="printing-assignee-{{ $job->id }}">
            {{ $job->assigned_user_id ? 'Reassign Printing Staff' : 'Assign Printing Staff' }}
        </label>

        <div class="staff-assignment-controls">
            <select
                id="printing-assignee-{{ $job->id }}"
                name="assigned_user_id"
                required
            >
                <option value="">Select printing staff</option>

                @foreach ($printingStaff as $staff)
                    <option
                        value="{{ $staff->id }}"
                        @selected($job->assigned_user_id === $staff->id)
                    >
                        {{ $staff->name }}
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="staff-button staff-button-small"
            >
                {{ $job->assigned_user_id ? 'Reassign' : 'Assign' }}
            </button>
        </div>
    </form>
@endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                No print jobs available.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($order->relationLoaded('packingJob') && $order->packingJob)
                <section class="staff-section">
                    <h2 class="staff-section-title">Packing</h2>

                    <article class="staff-card">
                        <div class="staff-card-heading">
                            <h3>Packing Job</h3>

                            <span class="staff-status">
                                {{ str_replace('_', ' ', $order->packingJob->status) }}
                            </span>
                        </div>

                        <dl class="staff-detail-list">
                            <div>
                                <dt>Assigned</dt>
                                <dd>{{ $order->packingJob->assignedUser?->name ?? 'Unassigned' }}</dd>
                            </div>

                            <div>
                                <dt>Started</dt>
                                <dd>{{ $order->packingJob->started_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Packed</dt>
                                <dd>{{ $order->packingJob->packed_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>
                        </dl>
                        @if (auth()->user()->isAdmin() && ! request()->attributes->get('staff_overview_mode', false))
    <form
        method="POST"
        action="{{ route('staff.packing-jobs.assign', $order->packingJob) }}"
        class="staff-assignment-form"
    >
        @csrf

        <label for="packing-assignee-{{ $order->packingJob->id }}">
            {{ $order->packingJob->assigned_user_id ? 'Reassign Packing Staff' : 'Assign Packing Staff' }}
        </label>

        <div class="staff-assignment-controls">
            <select
                id="packing-assignee-{{ $order->packingJob->id }}"
                name="assigned_user_id"
                required
            >
                <option value="">Select packing staff</option>

                @foreach ($packingStaff as $staff)
                    <option
                        value="{{ $staff->id }}"
                        @selected($order->packingJob->assigned_user_id === $staff->id)
                    >
                        {{ $staff->name }}
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="staff-button staff-button-small"
            >
                {{ $order->packingJob->assigned_user_id ? 'Reassign' : 'Assign' }}
            </button>
        </div>
    </form>
@endif

                        @if ($order->packingJob->items->isNotEmpty())
                            <div class="staff-detail-group">
                                <h4>Items</h4>

                                <div class="staff-contact-list">
                                    @foreach ($order->packingJob->items as $item)
                                        <div class="staff-contact-row">
                                            <span>{{ $item->side }}</span>

                                            <strong>
                                                {{ $item->verified_present ? 'Verified' : 'Not Verified' }}
                                            </strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($order->packingJob->proof_storage_path)
                            <p class="staff-proof-status"><strong>Bukti packing:</strong> {{ $order->packingJob->proof_original_name ?? 'Telah dimuat naik' }}</p>
                        @endif

                        @if (auth()->user()->isOperationManagement() || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING) && $order->packingJob->assigned_user_id === auth()->id()))
                            @if ($order->packingJob->status === 'READY_FOR_PACKING')
                                <form method="POST" action="{{ route('staff.packing-jobs.start', $order->packingJob) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Start Packing</button></form>
                            @endif
                            @if ($order->packingJob->status === 'PACKING')
                                @foreach ($order->packingJob->items as $item)
                                    @unless ($item->verified_present)
                                        <form method="POST" action="{{ route('staff.packing-jobs.items.verify', ['packingJob' => $order->packingJob, 'packingItem' => $item]) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-small">Verify {{ ucfirst(strtolower($item->side)) }}</button></form>
                                    @endunless
                                @endforeach
                                @if ($order->packingJob->items->every(fn ($item) => $item->verified_present))
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('staff.packing-jobs.mark-packed', $order->packingJob) }}" class="staff-packing-complete-form">
                                        @csrf
                                        <label for="packing-proof">Bukti gambar barang telah dipack</label>
                                        <input id="packing-proof" type="file" name="packing_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                        @if ($order->fulfilment?->method === 'COURIER')
                                            <label for="courier-provider">Nama courier</label><input id="courier-provider" name="courier_provider" value="{{ old('courier_provider') }}" placeholder="Contoh: Pos Laju" required>
                                            <label for="tracking-number">Tracking number</label><input id="tracking-number" name="tracking_number" value="{{ old('tracking_number') }}" required>
                                        @endif
                                        <button type="submit" class="staff-button staff-button-primary">Upload Bukti & Mark Packed</button>
                                    </form>
                                @endif
                            @endif
                        @endif
                    </article>
                </section>
            @endif

            <section class="staff-section">
                <h2 class="staff-section-title">Fulfilment</h2>

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
                                <dt>Shipping Address</dt>
                                <dd>{{ $order->fulfilment->shipping_address ?? '-' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="staff-muted-text">
                            No fulfilment details.
                        </p>
                    @endif

                    @if ($order->fulfilmentJob)
                        <div class="staff-detail-group">
                            <h4>Fulfilment Job</h4>

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
                                    <dt>Tracking</dt>
                                    <dd>{{ $order->fulfilmentJob->tracking_number ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Completion Reference</dt>
                                    <dd>{{ $order->fulfilmentJob->completion_reference ?? '-' }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endif
                </article>
            </section>
        </main>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.staff-artwork-picker').forEach(function (picker) {
            const input = picker.querySelector('input[type="file"]');
            const cancelButton = picker.querySelector('.staff-artwork-cancel');

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
</body>
</html>
