@php
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $logoutRoute = $isAdminPortal ? 'admin.logout' : 'staff.logout';
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $operationRoutePrefix = $isAdminPortal ? 'admin.' : 'staff.';
    $batchArtworkJobs = $order->relationLoaded('designJobs')
        ? $order->designJobs->filter(fn ($job) =>
            auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
            && $job->assigned_user_id === auth()->id()
            && $job->status === 'DESIGN_IN_PROGRESS'
        )
        : collect();
    $usesBatchArtworkUpload = $batchArtworkJobs->count() > 1;
    $canUsePhotoshop = auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
        && $order->relationLoaded('designJobs')
        && $order->designJobs->contains('assigned_user_id', auth()->id());
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $order->order_id }} - KKK OS Staff</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : 'Staff Operations' }} · Order Detail</p><h1>{{ $order->order_id }}</h1></div>
                <form class="js-logout-form" method="POST" action="{{ route($logoutRoute) }}">@csrf<button type="submit" class="staff-button staff-button-small">Log Keluar</button></form>
            </header>

        <main class="staff-main">
            @if (session('status'))
    <div class="staff-alert staff-alert-success">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="staff-alert staff-alert-error">
        <strong>Action could not be completed.</strong>

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

            @if (! auth()->user()->isOperationManagement())
                <p class="staff-work-message">Read-only order details.</p>
            @endif

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

                    @if ($canUsePhotoshop)
                        <div class="staff-design-tools">
                            <div>
                                <p class="staff-kicker">Photoshop Auto Merge V11</p>
                                <h3>Prepare this customer order in Photoshop</h3>
                                <ol>
                                    <li>Download the customer order CSV.</li>
                                    <li>Open Photoshop with the approved JavaScript.</li>
                                    <li>Select the ROOT folder, then select the downloaded CSV file.</li>
                                </ol>
                                <p class="staff-design-tools-note">
                                    Photoshop opens on the Windows workstation running KKK OS.
                                </p>
                            </div>

                            <div class="staff-design-tools-actions">
                                <a
                                    href="{{ route('staff.orders.photoshop.csv', $order) }}"
                                    class="staff-button"
                                >
                                    Download Customer CSV
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('staff.orders.photoshop.launch', $order) }}"
                                >
                                    @csrf

                                    <button type="submit" class="staff-button staff-button-primary">
                                        Open Photoshop
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

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
                                @if (auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
                                    && $job->assigned_user_id === auth()->id())
                                    <div class="staff-design-actions">
                                        @if ($job->status === 'READY_FOR_DESIGN')
                                            <form
                                                method="POST"
                                                action="{{ route($operationRoutePrefix.'design-jobs.start', $job) }}"
                                                class="js-staff-confirmation-form"
                                                data-confirm-title="Mulakan kerja design {{ ucfirst(strtolower($job->side)) }}?"
                                                data-confirm-message="Status pakej ini akan ditukar kepada sedang design dan masa mula akan direkodkan."
                                                data-confirm-button="Ya, mula design"
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
                                                    Resume Correction
                                                </button>
                                            </form>
                                        @elseif ($job->status === 'DESIGN_IN_PROGRESS')
                                            @php
                                                $isBatchArtworkJob = $usesBatchArtworkUpload
                                                    && $batchArtworkJobs->contains('id', $job->id);
                                            @endphp
                                            <div class="staff-design-upload">
                                                <div class="staff-design-upload-heading">
                                                    <h4>Upload Artwork Version</h4>

                                                    <p>
                                                        Upload the editable source artwork and a separate
                                                        customer preview.
                                                    </p>
                                                </div>

                                                @if ($isBatchArtworkJob)
                                                    <div class="staff-artwork-form">
                                                @else
                                                    <form
                                                        method="POST"
                                                        action="{{ route($operationRoutePrefix.'design-jobs.artwork.store', $job) }}"
                                                        enctype="multipart/form-data"
                                                        class="staff-artwork-form js-staff-confirmation-form"
                                                        data-confirm-title="Upload artwork {{ ucfirst(strtolower($job->side)) }}?"
                                                        data-confirm-message="Pastikan fail source artwork dan customer preview yang dipilih adalah betul. Fail ini akan disimpan sebagai versi artwork baharu."
                                                        data-confirm-button="Ya, upload artwork"
                                                    >
                                                        @csrf
                                                @endif

                                                    <div class="staff-field">
                                                        <label for="source-artwork-{{ $job->id }}">
                                                            Source Artwork
                                                        </label>

                                                        <div class="staff-multi-file-picker" data-file-kind="source artwork">
                                                        <input
                                                            id="source-artwork-{{ $job->id }}"
                                                            type="file"
                                                            name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][source_artwork][]' : 'source_artwork[]' }}"
                                                            @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif
                                                            accept=".psd,.pdf"
                                                            multiple
                                                            required
                                                        >
                                                            <button type="button" class="staff-file-add" aria-controls="source-artwork-{{ $job->id }}">+ Add File</button>
                                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            PSD or PDF. Maximum 100 MB.
                                                        </span>
                                                    </div>

                                                    <div class="staff-field">
                                                        <label for="customer-preview-{{ $job->id }}">
                                                            Customer Preview
                                                        </label>

                                                        <div class="staff-multi-file-picker" data-file-kind="customer preview">
                                                        <input
                                                            id="customer-preview-{{ $job->id }}"
                                                            type="file"
                                                            name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][customer_preview][]' : 'customer_preview[]' }}"
                                                            @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif
                                                            accept=".jpg,.jpeg,.png"
                                                            multiple
                                                            required
                                                        >
                                                            <button type="button" class="staff-file-add" aria-controls="customer-preview-{{ $job->id }}">+ Add File</button>
                                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            JPG or PNG only. Maximum 20 MB. Customer image previews are reduced in size and quality, then protected with a large King Kad Kahwin watermark.
                                                        </span>
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
                                                            Upload Artwork
                                                        </button>
                                                        </form>
                                                    @endif

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
                                                            action="{{ route($operationRoutePrefix.'design-jobs.mark-ready', $job) }}"
                                                            class="js-staff-confirmation-form"
                                                            data-confirm-title="Hantar artwork {{ ucfirst(strtolower($job->side)) }} kepada customer?"
                                                            data-confirm-message="Versi {{ $job->artworkVersions->max('version_number') }} akan dihantar untuk semakan customer. Pastikan preview telah diperiksa dan merupakan versi yang betul."
                                                            data-confirm-button="Ya, hantar untuk semakan"
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
                                @if (auth()->user()->isOperationManagement() && ! request()->attributes->get('staff_overview_mode', false))
    <form
        method="POST"
        action="{{ route($operationRoutePrefix.'design-jobs.assign', $job) }}"
        class="staff-assignment-form js-staff-confirmation-form"
        data-confirm-assignment="Designer"
        data-confirm-mode="{{ $job->assigned_user_id ? 'reassign' : 'assign' }}"
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

                    @if ($usesBatchArtworkUpload)
                        <form
                            id="batch-artwork-upload"
                            method="POST"
                            action="{{ route('staff.orders.design-artworks.store', $order) }}"
                            enctype="multipart/form-data"
                            class="staff-batch-artwork-form js-staff-confirmation-form"
                            data-confirm-title="Upload kedua-dua artwork?"
                            data-confirm-message="Pastikan fail source dan customer preview untuk pakej Lelaki serta Perempuan adalah betul. Kedua-duanya akan disimpan sebagai versi artwork baharu."
                            data-confirm-button="Ya, upload kedua-duanya"
                        >
                            @csrf
                            <button type="submit" class="staff-button staff-button-primary">
                                Upload Both Artwork
                            </button>
                        </form>
                    @endif
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

                            @if (in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE', 'ARTWORK_CORRECTION'], true) && ! empty($payment->metadata['receipt_path']))
                                <div class="staff-payment-actions">
                                    @if ($payment->payment_type === 'ARTWORK_CORRECTION')
                                        <p><strong>Caj pembetulan RM10</strong>: {{ $payment->metadata['correction_comment'] ?? '' }}</p>
                                        @if (auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                            <form method="POST" action="{{ route($operationRoutePrefix.'payments.correction.approve', $payment) }}">
                                                @csrf
                                                <button class="staff-button staff-button-primary" type="submit">Sahkan Bayaran Pembetulan RM10</button>
                                            </form>
                                            <form method="POST" action="{{ route($operationRoutePrefix.'payments.correction.reject', $payment) }}" class="staff-reject-payment-form">
                                                @csrf
                                                <label for="correction-rejection-{{ $payment->id }}">Sebab penolakan</label>
                                                <textarea id="correction-rejection-{{ $payment->id }}" name="rejection_reason" maxlength="1000" required></textarea>
                                                <button class="staff-button staff-button-danger" type="submit">Tolak Resit Pembetulan</button>
                                            </form>
                                        @endif
                                    @endif
                                    @if (auth()->user()->isOperationManagement())
                                    <a class="staff-button staff-button-small" target="_blank" rel="noopener" href="{{ route($operationRoutePrefix.'payments.receipt', $payment) }}">Lihat Resit</a>
                                    @endif
                                    @if ($payment->payment_type === 'BOOKING_DEPOSIT' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.approve', $payment) }}" class="staff-field js-staff-confirmation-form" data-confirm-title="Sahkan bayaran deposit?" data-confirm-message="Pastikan jumlah bayaran pada resit telah dimasukkan dengan betul. Selepas disahkan, tempahan akan diteruskan ke proses seterusnya." data-confirm-button="Ya, sahkan deposit">
                                            @csrf
                                            <label for="payment-amount-{{ $payment->id }}">Jumlah bayaran pada resit (RM)</label>
                                            <input id="payment-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" placeholder="Contoh: 100.00" required>
                                            <button class="staff-button staff-button-primary" type="submit">Sahkan Deposit</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Tolak bayaran deposit?" data-confirm-message="Customer perlu menghantar semula bukti pembayaran selepas deposit ditolak. Pastikan sebab penolakan telah ditulis dengan jelas." data-confirm-button="Ya, tolak deposit" data-confirm-tone="danger">
                                            @csrf
                                            <label for="rejection-reason-{{ $payment->id }}">Sebab penolakan</label>
                                            <textarea id="rejection-reason-{{ $payment->id }}" name="rejection_reason" rows="2" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="staff-button staff-button-danger" type="submit">Tolak Deposit</button>
                                        </form>
                                    @endif
                                    @if ($payment->payment_type === 'BALANCE' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.balance.approve', $payment) }}" class="staff-field js-staff-confirmation-form" data-confirm-title="Sahkan bayaran penuh?" data-confirm-message="Pastikan jumlah pada resit telah dimasukkan dengan betul. Selepas disahkan, tempahan akan diteruskan ke proses cetakan." data-confirm-button="Ya, sahkan bayaran">
                                            @csrf
                                            <label for="payment-amount-{{ $payment->id }}">Jumlah bayaran pada resit (RM)</label>
                                            <input id="payment-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" placeholder="Contoh: 100.00" required>
                                            <button class="staff-button staff-button-primary" type="submit">Sahkan Bayaran Penuh</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.balance.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Tolak bayaran penuh?" data-confirm-message="Customer perlu menghantar semula bukti pembayaran. Pastikan sebab penolakan telah ditulis dengan jelas." data-confirm-button="Ya, tolak bayaran" data-confirm-tone="danger">
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

            @if (($canAssignProduction ?? false) && auth()->user()->isOperationManagement() && ! request()->attributes->get('staff_overview_mode', false))
                <section class="staff-section">
                    <h2 class="staff-section-title">Assign Production Staff</h2>

                    <div class="staff-work-grid">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-printing', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form" data-confirm-assignment="Printing" data-confirm-mode="{{ $order->printing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>Printing</h3>
                                <span class="staff-status">{{ $order->printingAssignedUser ? 'ASSIGNED' : 'UNASSIGNED' }}</span>
                            </div>
                            <label for="order-printing-assignee">{{ $order->printing_assigned_user_id ? 'Reassign Printing Staff' : 'Assign Printing Staff' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-printing-assignee" name="assigned_user_id" required>
                                    <option value="">Select printing staff</option>
                                    @foreach ($printingStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected($order->printing_assigned_user_id === $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="staff-button staff-button-small">{{ $order->printing_assigned_user_id ? 'Reassign' : 'Assign' }}</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-packing-fulfilment', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form" data-confirm-assignment="Packing & Fulfilment" data-confirm-mode="{{ $order->packing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>Packing &amp; Fulfilment</h3>
                                <span class="staff-status">{{ $order->packingAssignedUser ? 'ASSIGNED' : 'UNASSIGNED' }}</span>
                            </div>
                            <label for="order-packing-assignee">{{ $order->packing_assigned_user_id ? 'Reassign Packing & Fulfilment Staff' : 'Assign Packing & Fulfilment Staff' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-packing-assignee" name="assigned_user_id" required>
                                    <option value="">Select packing &amp; fulfilment staff</option>
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
                                        <dd>{{ $job->quantity ?? $order->card_quantity ?? '-' }}</dd>
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

                                    <div>
                                        <dt>Progress Updated</dt>
                                        <dd>{{ $job->progress_updated_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>
                                </dl>

                                @if (filled($job->progress_files))
                                    <div class="staff-detail-group">
                                        <h4>Printing Progress Files</h4>

                                        <div class="staff-contact-list">
                                            @foreach ($job->progress_files as $file)
                                                <div class="staff-contact-row">
                                                    <span>{{ $file['original_name'] ?? 'Progress file' }}</span>
                                                    <a href="{{ route($operationRoutePrefix.'print-jobs.progress-files.show', ['printJob' => $job, 'file' => $loop->index]) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">
                                                        View · {{ isset($file['uploaded_at']) ? \Illuminate\Support\Carbon::parse($file['uploaded_at'])->format('Y-m-d H:i') : '-' }}
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                @if (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRINTING) && $job->assigned_user_id === auth()->id())
                                    @if ($job->status === 'READY_FOR_PRINT')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'print-jobs.start', $job) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Start Printing</button></form>
                                    @elseif ($job->status === 'WAITING_FOR_PAYMENT')
                                        <p class="staff-work-message">Menunggu pengesahan bayaran penuh sebelum cetakan boleh dimulakan.</p>
                                    @elseif ($job->status === 'PRINTING')
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('staff.print-jobs.progress-files.store', $job) }}" class="staff-packing-complete-form">
                                            @csrf
                                            <label for="print-progress-{{ $job->id }}">Upload printing progress</label>
                                            <div class="staff-file-picker">
                                                <input id="print-progress-{{ $job->id }}" type="file" name="progress_files[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" multiple required>
                                                <button type="button" class="staff-file-cancel" hidden aria-controls="print-progress-{{ $job->id }}">Batal</button>
                                            </div>
                                            <p class="staff-muted-text">Upload photos or PDFs that show the current printing progress. Maximum 10 files, 20 MB each.</p>
                                            <button type="submit" class="staff-button staff-button-secondary">Upload Progress</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'print-jobs.mark-printed', $job) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Mark Printed</button></form>
                                    @elseif ($job->status === 'PRINTED')
                                        <p class="staff-work-message">Cetakan untuk pakej ini telah siap.</p>
                                    @endif
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
                            <div class="staff-proof-status">
                                <strong>Bukti packing:</strong> {{ $order->packingJob->proof_original_name ?? 'Telah dimuat naik' }}

                                @if (auth()->user()->isOperationManagement() || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING) && $order->packingJob->assigned_user_id === auth()->id()))
                                    <a href="{{ route($operationRoutePrefix.'packing-jobs.proof.show', $order->packingJob) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">View</a>
                                @endif
                            </div>
                        @endif

                        @if (auth()->user()->isOperationManagement() || (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING) && $order->packingJob->assigned_user_id === auth()->id()))
                            @if ($order->packingJob->status === 'READY_FOR_PACKING')
                                <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.start', $order->packingJob) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-primary">Start Packing</button></form>
                            @endif
                            @if ($order->packingJob->status === 'PACKING')
                                @foreach ($order->packingJob->items as $item)
                                    @unless ($item->verified_present)
                                        <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.items.verify', ['packingJob' => $order->packingJob, 'packingItem' => $item]) }}" class="staff-workflow-form">@csrf<button type="submit" class="staff-button staff-button-small">Verify {{ ucfirst(strtolower($item->side)) }}</button></form>
                                    @endunless
                                @endforeach
                                @if ($order->packingJob->items->every(fn ($item) => $item->verified_present))
                                    <form method="POST" enctype="multipart/form-data" action="{{ route($operationRoutePrefix.'packing-jobs.mark-packed', $order->packingJob) }}" class="staff-packing-complete-form">
                                        @csrf
                                        <label for="packing-proof">Bukti gambar barang telah dipack</label>
                                        <div class="staff-file-picker">
                                            <input id="packing-proof" type="file" name="packing_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                            <button type="button" class="staff-file-cancel" hidden aria-controls="packing-proof">Batal</button>
                                        </div>
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
            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING) && $order->packing_assigned_user_id === auth()->id())
                <section class="staff-section">
                    <h2 class="staff-section-title">Packing</h2>
                    <div class="staff-empty">
                        Packing job akan diwujudkan secara automatik selepas semua kerja cetakan selesai.
                    </div>
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
                                                COMPLETE - Kad telah diambil oleh customer
                                            </label>

                                            <button
                                                type="submit"
                                                class="staff-button staff-button-primary"
                                            >
                                                Mark Pickup Collected
                                            </button>
                                        </form>

                                    @elseif ($order->fulfilmentJob->method === 'COURIER')
                                        <h4>Serahan kepada Courier</h4>

                                        <form
                                            method="POST"
                                            enctype="multipart/form-data"
                                            action="{{ route($operationRoutePrefix.'packing-jobs.complete-courier', $order->packingJob) }}"
                                            class="staff-packing-complete-form"
                                        >
                                            @csrf

                                            <label for="courier-packing-proof">
                                                Bukti parcel bersama label tracking
                                            </label>

                                            <div class="staff-file-picker">
                                            <input
                                                id="courier-packing-proof"
                                                type="file"
                                                name="packing_proof"
                                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                                required
                                            >
                                                <button type="button" class="staff-file-cancel" hidden aria-controls="courier-packing-proof">Batal</button>
                                            </div>

                                            <label for="courier-provider">
                                                Nama courier
                                            </label>

                                            <input
                                                id="courier-provider"
                                                type="text"
                                                name="courier_provider"
                                                value="{{ old('courier_provider', $order->fulfilmentJob->courier_provider) }}"
                                                placeholder="Contoh: Pos Laju"
                                                required
                                            >

                                            <label for="tracking-number">
                                                Tracking number
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
                                                COMPLETE - Parcel telah diserahkan kepada courier
                                            </label>

                                            <button
                                                type="submit"
                                                class="staff-button staff-button-primary"
                                            >
                                                Sahkan Serahan kepada Courier
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            </section>
        </main>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
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
                    remove.textContent = 'Delete';
                    remove.setAttribute('aria-label', 'Delete ' + file.name);
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
    <h2 id="staff-action-confirmation-title">Sahkan tindakan?</h2>
    <p id="staff-action-confirmation-message"></p>
    <div class="logout-confirmation-actions">
        <button id="cancel-staff-action" type="button">Tidak, kembali</button>
        <button id="confirm-staff-action" type="button">Ya, teruskan</button>
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

                if (form.dataset.confirmAssignment) {
                    const assignee = form.querySelector('[name="assigned_user_id"]');
                    const assigneeName = assignee.options[assignee.selectedIndex].text.trim();
                    const isReassignment = form.dataset.confirmMode === 'reassign';
                    const actionLabel = isReassignment ? 'Tukar' : 'Assign';

                    title.textContent = actionLabel + ' staff ' + form.dataset.confirmAssignment + '?';
                    message.textContent = 'Tugasan ini akan diberikan kepada ' + assigneeName + '. Pastikan staff yang dipilih adalah betul.';
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

        confirmButton.addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }

            confirmButton.disabled = true;
            confirmButton.textContent = 'Memproses...';
            pendingForm.submit();
        });
    });
</script>
</body>
</html>
