<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Design Review - {{ $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/artwork-review.css') }}?v={{ filemtime(public_path('css/artwork-review.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-customer-flow">
@php
    $orderStatusLabels = [
        'READY_FOR_DESIGN' => 'Awaiting Design',
        'DESIGN_IN_PROGRESS' => 'Design in Progress',
        'DESIGN_READY' => 'Design Ready for Review',
        'CORRECTION_REQUESTED' => 'Corrections in Progress',
        'DESIGN_APPROVED' => 'Design Approved',
        'BALANCE_PAYMENT_PENDING' => 'Awaiting Balance Payment',
        'PAID' => 'Payment Complete',
        'PRINTING' => 'Printing in Progress',
        'PRINTED' => 'Printing Complete',
        'PACKING' => 'Packing in Progress',
        'PACKED' => 'Packing Complete',
        'READY_FOR_FULFILMENT' => 'Ready for Delivery / Pickup',
        'SHIPPED' => 'Shipped',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'CANCELLED',
        'ARCHIVED' => 'Archived',
    ];

    $jobStatusLabels = [
        'READY_FOR_DESIGN' => 'Awaiting Designer',
        'DESIGN_IN_PROGRESS' => 'In Progress',
        'DESIGN_READY' => 'Ready for Review',
        'CORRECTION_REQUESTED' => 'Correction Requested',
        'DESIGN_APPROVED' => 'Approved',
    ];

    $isTerminalOrder = in_array($order->status, ['CANCELLED', 'ARCHIVED'], true);
@endphp

<div class="page-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">King Wedding Card</p>
            <h1>Design Review</h1>
            <p class="header-copy">
                Please review each package's design carefully before approving it or requesting changes.
            </p>
        </div>

        <div class="order-summary">
            <div>
                <span class="summary-label">Order ID</span>
                <strong>{{ $order->order_id }}</strong>
            </div>
            <div>
                <span class="summary-label">Status</span>
                <span class="status-badge status-{{ strtolower(str_replace('_', '-', $order->status)) }}">
                    {{ $orderStatusLabels[$order->status] ?? 'In Progress' }}
                </span>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="alert alert-success" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <strong>Could not submit the action.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isTerminalOrder)
        <div class="alert alert-neutral">
            This order has been {{ $order->status === 'CANCELLED' ? 'cancelled' : 'archived' }}.
            No further design actions can be taken.
        </div>
    @endif

    <main class="artwork-grid">
        @forelse ($order->designJobs->sortBy('side') as $designJob)
            @php
                $latestArtwork = $designJob->artworkVersions->sortByDesc('version_number')->first();
                $correctionPayment = $order->payments->where('payment_type', 'ARTWORK_CORRECTION')
                    ->filter(fn ($payment) => ($payment->metadata['design_job_id'] ?? null) === $designJob->id
                        && ($payment->metadata['artwork_version_id'] ?? null) === $latestArtwork?->id)
                    ->sortByDesc('id')->first();
                $correctionHistory = $designJob->reviewActions
                    ->where('action', 'CORRECTION_REQUESTED')
                    ->sortByDesc('acted_at');

                $canPreview = $latestArtwork
                    && in_array($designJob->status, ['DESIGN_READY', 'CORRECTION_REQUESTED', 'DESIGN_APPROVED'], true);
                $previewFiles = $latestArtwork?->preview_files ?: array_filter([
                    $latestArtwork?->preview_storage_path ? ['path' => $latestArtwork->preview_storage_path] : null,
                ]);
            @endphp

            <section class="artwork-card" id="design-job-{{ $designJob->id }}">
                <div class="card-heading">
                    <div>
                        <p class="side-label">Package</p>
                        <h2>{{ $designJob->side === 'LELAKI' ? 'Groom' : 'Bride' }}</h2>
                    </div>

                    <span class="job-status job-status-{{ strtolower(str_replace('_', '-', $designJob->status)) }}">
                        {{ $jobStatusLabels[$designJob->status] ?? 'In Progress' }}
                    </span>
                </div>

                @if ($latestArtwork)
                    <div class="artwork-meta">
                        <div>
                            <span class="meta-label">Design Version</span>
                            <strong>v{{ $latestArtwork->version_number }}</strong>
                        </div>
                        <span class="artwork-preview-count">{{ count($previewFiles) }} previews to review</span>
                    </div>

                    @if ($canPreview)
                        <section class="artwork-preview-section" aria-label="Package artwork preview {{ $designJob->side === 'LELAKI' ? 'groom' : 'bride' }}">
                            <div class="artwork-preview-heading">
                                <div><span class="meta-label">Visual review</span><h3>Preview Artwork</h3></div>
                                <p>Each package includes a banner and bunting using the same short names and card design theme. Select any image to view it larger.</p>
                            </div>
                            <div class="artwork-preview-gallery">
                                @foreach ($previewFiles as $previewIndex => $previewFile)
                                    @php
                                        $previewType = strtoupper($previewFile['artwork_type'] ?? 'CARD');
                                        $sameTypePosition = collect($previewFiles)->take($previewIndex + 1)
                                            ->filter(fn ($file) => strtoupper($file['artwork_type'] ?? 'CARD') === $previewType)->count();
                                        $previewLabel = match ($previewType) {
                                            'BANNER' => 'banner',
                                            'BANTING' => 'bunting',
                                            default => 'Wedding Card'.($sameTypePosition > 1 ? ' '.$sameTypePosition : ''),
                                        };
                                        $previewUrl = route('orders.artwork.preview', [
                                            'orderId' => $order->order_id,
                                            'designJobId' => $designJob->id,
                                            'file' => $previewIndex,
                                        ]);
                                    @endphp
                                    <figure class="artwork-preview-item artwork-preview-item--{{ strtolower($previewType) }}">
                                        <figcaption><strong>{{ $previewLabel }}</strong><span>{{ $designJob->side === 'LELAKI' ? "Groom's Side" : "Bride's Side" }}</span></figcaption>
                                        <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Open preview {{ strtolower($previewLabel) }} at full size">
                                            <img src="{{ $previewUrl }}" alt="Preview {{ strtolower($previewLabel) }} for the {{ $designJob->side === 'LELAKI' ? 'groom' : 'bride' }}" loading="lazy">
                                        </a>
                                    </figure>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @else
                    <div class="empty-state customer-empty-state">
                        <span class="customer-empty-icon" aria-hidden="true">✦</span>
                        <strong>The design is not ready to view yet.</strong>
                        <p>Our design team is preparing this package. We will update its status when it is ready for review.</p>
                    </div>
                @endif

                @if ($correctionPayment?->status === 'PENDING')
                    <div class="state-panel state-warning">
                        <h3>Awaiting RM10 Payment Confirmation</h3>
                        <p>Request received. Corrections will begin after payment confirmation.</p>
                        <p>{{ $correctionPayment->metadata['correction_comment'] }}</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_READY' && ! $isTerminalOrder)
                    <div class="review-actions">
                        <div class="action-panel approve-panel">
                            <h3>Is the design correct?</h3>
                            <p>
                                Check all names, dates, times, addresses, phone numbers and other details on the design.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('orders.artwork.approve', [
                                    'orderId' => $order->order_id,
                                    'designJobId' => $designJob->id,
                                ]) }}"
                                class="js-artwork-confirmation-form"
                                data-confirm-title="Approve {{ $designJob->side === 'LELAKI' ? "groom's" : "bride's" }} design?"
                                data-confirm-message="This design will be considered correct and the order will proceed to balance payment and printing."
                                data-confirm-button="Yes, approve design"
                            >
                                @csrf
                                <button type="submit" class="button button-primary">
                                    Approve Design
                                </button>
                            </form>
                        </div>

                        <div class="action-panel correction-panel">
                            <h3>Need corrections?</h3>
                            <p>Describe corrections clearly so the designer can make the right changes.</p>
                            <p class="correction-fee-warning">Each correction request incurs an RM10 charge. Include all changes in one request.</p>
                            @if ($correctionPayment?->status === 'FAILED')
                                <div class="alert alert-error">Receipt rejected: {{ $correctionPayment->metadata['rejection_reason'] ?? '' }}. Please resubmit a valid receipt.</div>
                            @endif

                            <form
                                class="correction-payment-form js-artwork-confirmation-form"
                                data-confirm-title="Submit a correction request?"
                                data-confirm-message="Your request, RM10 charge and payment proof will be submitted for review. Make sure all corrections are clearly stated."
                                data-confirm-button="Yes, submit corrections"
                                data-confirm-tone="danger"
                                enctype="multipart/form-data"
                                method="POST"
                                action="{{ route('orders.artwork.correction', [
                                    'orderId' => $order->order_id,
                                    'designJobId' => $designJob->id,
                                ]) }}"
                            >
                                @csrf
                                <input type="hidden" name="correction_job_id" value="{{ $designJob->id }}">
                                <fieldset class="correction-assets">
                                    <legend>Design items requiring correction</legend>
                                    <label><input type="checkbox" name="affected_assets[]" value="CARD" checked> Wedding Card</label>
                                    @if (collect($latestArtwork?->preview_files ?? [])->contains(fn ($file) => data_get($file, 'artwork_type') === 'BANNER'))
                                        <label><input type="checkbox" name="affected_assets[]" value="BANNER"> Banner</label>
                                    @endif
                                    @if (collect($latestArtwork?->preview_files ?? [])->contains(fn ($file) => data_get($file, 'artwork_type') === 'BANTING'))
                                        <label><input type="checkbox" name="affected_assets[]" value="BANTING"> Bunting</label>
                                    @endif
                                </fieldset>
                                <label for="correction-{{ $designJob->id }}">Correction details</label>
                                <textarea
                                    id="correction-{{ $designJob->id }}"
                                    name="correction_comment"
                                    rows="5"
                                    maxlength="5000"
                                    required
                                    placeholder="Example: Please correct the bride's name from ... to ..."
                                >{{ (int) old('correction_job_id') === $designJob->id ? old('correction_comment') : ($correctionPayment?->status === 'FAILED' ? $correctionPayment->metadata['correction_comment'] : '') }}</textarea>
                                <p class="field-help">Maximum 5,000 characters.</p>

                                <label class="correction-fee-consent">
                                    <input type="checkbox" name="correction_fee_agreed" value="1" required aria-controls="correction-payment-{{ $designJob->id }}" @checked((int) old('correction_job_id') === $designJob->id && old('correction_fee_agreed'))>
                                    <span>I agree to the RM10 correction charge.</span>
                                </label>
                                <div class="correction-payment-details" id="correction-payment-{{ $designJob->id }}">
                                    <strong>Correction payment: RM10</strong>
                                    <img class="correction-qr" src="{{ asset(config('kingkadkahwin.deposit.qr_image')) }}" alt="RM10 correction payment QR code">
                                    <label for="correction-receipt-{{ $designJob->id }}">Attach payment receipt</label>
                                    <p class="field-help">JPG, JPEG, PNG, WEBP or PDF. Maximum 10 MB.</p>
                                    <input id="correction-receipt-{{ $designJob->id }}" type="file" name="correction_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                    <button type="button" class="button button-secondary correction-file-cancel" aria-controls="correction-receipt-{{ $designJob->id }}" hidden>Cancel</button>
                                </div>

                                <button type="submit" class="button button-danger-outline">
                                    Submit Correction Request
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($designJob->status === 'CORRECTION_REQUESTED')
                    <div class="state-panel state-warning">
                        <h3>Corrections requested</h3>
                        <p>The designer is making corrections. A new design version will be available when complete.</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_APPROVED')
                    <div class="state-panel state-success">
                        <h3>Design approved</h3>
                        <p>No further action is required for this package.</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_IN_PROGRESS')
                    <div class="state-panel state-neutral">
                        <h3>Design in progress</h3>
                        <p>Please wait until the designer marks the design ready for review.</p>
                    </div>
                @elseif ($designJob->status === 'READY_FOR_DESIGN')
                    <div class="state-panel state-neutral">
                        <h3>Awaiting design</h3>
                        <p>Design work for this package has not started yet.</p>
                    </div>
                @endif

                @if ($correctionHistory->isNotEmpty())
                    <div class="history-panel">
                        <h3>Correction History</h3>

                        <div class="history-list">
                            @foreach ($correctionHistory as $action)
                                <article class="history-item">
                                    <div class="history-item-header">
                                        <strong>Correction request</strong>
                                        @if ($action->acted_at)
                                            <time datetime="{{ $action->acted_at->toIso8601String() }}">
                                                {{ $action->acted_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
                                            </time>
                                        @endif
                                    </div>

                                    @if (filled($action->customer_comment))
                                        <p>{{ $action->customer_comment }}</p>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @empty
            <div class="empty-state customer-empty-state page-empty-state">
                <span class="customer-empty-icon" aria-hidden="true">✦</span>
                <strong>No designs are available for review yet.</strong>
                <p>Your order is awaiting design. No action is needed from you right now.</p>
            </div>
        @endforelse
    </main>

    <div class="page-actions">
        <a
            class="button button-secondary"
            href="{{ route('public.orders.progress', ['order_id' => $order->order_id]) }}"
        >
            &larr; Back to Order Tracking
        </a>
    </div>
</div>
<dialog id="artwork-confirmation-dialog" class="artwork-confirmation-dialog" aria-labelledby="artwork-confirmation-title">
    <div class="artwork-confirmation-icon" aria-hidden="true">?</div>
    <h2 id="artwork-confirmation-title">Confirm action?</h2>
    <p id="artwork-confirmation-message"></p>
    <div class="artwork-confirmation-actions">
        <button id="cancel-artwork-confirmation" type="button">No, go back</button>
        <button id="confirm-artwork-action" type="button">Yes, continue</button>
    </div>
</dialog>
<script>
    document.querySelectorAll('.correction-payment-form').forEach(function (form) {
        const consent = form.querySelector('[name="correction_fee_agreed"]');
        const details = form.querySelector('.correction-payment-details');
        const receipt = form.querySelector('[name="correction_receipt"]');
        const cancel = form.querySelector('.correction-file-cancel');
        const submit = form.querySelector('[type="submit"]');
        function update() {
            details.hidden = !consent.checked;
            receipt.disabled = !consent.checked;
            submit.disabled = !consent.checked;
            cancel.hidden = receipt.files.length === 0;
        }
        consent.addEventListener('change', update);
        receipt.addEventListener('change', update);
        cancel.addEventListener('click', function () {
            receipt.value = '';
            update();
            receipt.focus();
        });
        update();
    });

    const artworkConfirmationForms = document.querySelectorAll('.js-artwork-confirmation-form');
    const artworkConfirmationDialog = document.getElementById('artwork-confirmation-dialog');
    const artworkConfirmationTitle = document.getElementById('artwork-confirmation-title');
    const artworkConfirmationMessage = document.getElementById('artwork-confirmation-message');
    const confirmArtworkAction = document.getElementById('confirm-artwork-action');
    const cancelArtworkConfirmation = document.getElementById('cancel-artwork-confirmation');
    let pendingArtworkForm = null;

    artworkConfirmationForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            pendingArtworkForm = form;
            artworkConfirmationTitle.textContent = form.dataset.confirmTitle;
            artworkConfirmationMessage.textContent = form.dataset.confirmMessage;
            confirmArtworkAction.textContent = form.dataset.confirmButton;
            confirmArtworkAction.classList.toggle('is-danger', form.dataset.confirmTone === 'danger');
            confirmArtworkAction.classList.toggle('is-primary', form.dataset.confirmTone !== 'danger');
            artworkConfirmationDialog.showModal();
        });
    });

    cancelArtworkConfirmation.addEventListener('click', function () {
        pendingArtworkForm = null;
        artworkConfirmationDialog.close();
    });

    confirmArtworkAction.addEventListener('click', function () {
        if (!pendingArtworkForm) {
            return;
        }

        confirmArtworkAction.disabled = true;
        confirmArtworkAction.textContent = 'Memproses...';
        const formSubmit = pendingArtworkForm.querySelector('[type="submit"]');
        if (formSubmit) {
            formSubmit.disabled = true;
            formSubmit.classList.add('is-loading');
            formSubmit.textContent = 'Submitting...';
        }
        pendingArtworkForm.submit();
    });
</script>
@include('public.partials.customer-service')
@include('public.partials.theme-toggle')
@include('partials.malay-validation')
</body>
</html>
