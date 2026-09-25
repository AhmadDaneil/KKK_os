<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Semakan Artwork - {{ $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/artwork-review.css') }}">
</head>
<body>
@php
    $orderStatusLabels = [
        'READY_FOR_DESIGN' => 'Menunggu Proses Design',
        'DESIGN_IN_PROGRESS' => 'Artwork Sedang Disediakan',
        'DESIGN_READY' => 'Artwork Sedia Untuk Semakan',
        'CORRECTION_REQUESTED' => 'Pembetulan Sedang Diproses',
        'DESIGN_APPROVED' => 'Artwork Telah Diluluskan',
        'BALANCE_PAYMENT_PENDING' => 'Menunggu Bayaran Baki',
        'PAID' => 'Bayaran Selesai',
        'PRINTING' => 'Dalam Proses Cetakan',
        'PRINTED' => 'Cetakan Selesai',
        'PACKING' => 'Dalam Proses Packing',
        'PACKED' => 'Packing Selesai',
        'READY_FOR_FULFILMENT' => 'Sedia Untuk Penghantaran / Pickup',
        'SHIPPED' => 'Telah Dihantar',
        'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan',
        'ARCHIVED' => 'Diarkibkan',
    ];

    $jobStatusLabels = [
        'READY_FOR_DESIGN' => 'Menunggu Designer',
        'DESIGN_IN_PROGRESS' => 'Sedang Disediakan',
        'DESIGN_READY' => 'Sedia Untuk Semakan',
        'CORRECTION_REQUESTED' => 'Pembetulan Diminta',
        'DESIGN_APPROVED' => 'Diluluskan',
    ];

    $isTerminalOrder = in_array($order->status, ['CANCELLED', 'ARCHIVED'], true);
@endphp

<div class="page-shell">
    <header class="page-header">
        <div>
            <p class="eyebrow">King Kad Kahwin</p>
            <h1>Semakan Artwork</h1>
            <p class="header-copy">
                Sila semak artwork setiap pakej dengan teliti sebelum meluluskan atau meminta pembetulan.
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
                    {{ $orderStatusLabels[$order->status] ?? 'Sedang Diproses' }}
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
            <strong>Tindakan tidak dapat dihantar.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isTerminalOrder)
        <div class="alert alert-neutral">
            Order ini telah {{ $order->status === 'CANCELLED' ? 'dibatalkan' : 'diarkibkan' }}.
            Tiada tindakan artwork baharu boleh dibuat.
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

            <section class="artwork-card">
                <div class="card-heading">
                    <div>
                        <p class="side-label">Pakej</p>
                        <h2>{{ ucfirst(strtolower($designJob->side)) }}</h2>
                    </div>

                    <span class="job-status job-status-{{ strtolower(str_replace('_', '-', $designJob->status)) }}">
                        {{ $jobStatusLabels[$designJob->status] ?? 'Sedang Diproses' }}
                    </span>
                </div>

                @if ($latestArtwork)
                    <div class="artwork-meta">
                        <div>
                            <span class="meta-label">Versi Artwork</span>
                            <strong>v{{ $latestArtwork->version_number }}</strong>
                        </div>

                        @if ($canPreview)
                            <div class="artwork-preview-links">
                                @foreach ($previewFiles as $previewIndex => $previewFile)
                                    <a
                                        class="button button-secondary"
                                        href="{{ route('orders.artwork.preview', [
                                            'orderId' => $order->order_id,
                                            'designJobId' => $designJob->id,
                                            'file' => $previewIndex,
                                        ]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {{ count($previewFiles) > 1 ? 'Lihat Artwork '.($previewIndex + 1) : 'Lihat Artwork' }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="empty-state">
                        <strong>Artwork belum tersedia.</strong>
                        <p>Designer sedang menyediakan artwork untuk pakej ini.</p>
                    </div>
                @endif

                @if ($correctionPayment?->status === 'PENDING')
                    <div class="state-panel state-warning">
                        <h3>Menunggu pengesahan bayaran RM10</h3>
                        <p>Permintaan diterima. Pembetulan akan dimulakan selepas bayaran disahkan.</p>
                        <p>{{ $correctionPayment->metadata['correction_comment'] }}</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_READY' && ! $isTerminalOrder)
                    <div class="review-actions">
                        <div class="action-panel approve-panel">
                            <h3>Artwork sudah betul?</h3>
                            <p>
                                Pastikan nama, tarikh, masa, alamat, nombor telefon dan semua maklumat pada artwork telah diperiksa.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('orders.artwork.approve', [
                                    'orderId' => $order->order_id,
                                    'designJobId' => $designJob->id,
                                ]) }}"
                                class="js-artwork-confirmation-form"
                                data-confirm-title="Luluskan artwork {{ ucfirst(strtolower($designJob->side)) }}?"
                                data-confirm-message="Artwork ini akan dianggap betul dan diteruskan ke proses pembayaran baki serta cetakan."
                                data-confirm-button="Ya, luluskan artwork"
                            >
                                @csrf
                                <button type="submit" class="button button-primary">
                                    Luluskan Artwork
                                </button>
                            </form>
                        </div>

                        <div class="action-panel correction-panel">
                            <h3>Perlu pembetulan?</h3>
                            <p>Nyatakan pembetulan dengan jelas supaya designer boleh membuat perubahan dengan tepat.</p>
                            <p class="correction-fee-warning">Setiap permintaan pembetulan dikenakan caj RM10. Nyatakan semua perubahan dalam satu permintaan.</p>
                            @if ($correctionPayment?->status === 'FAILED')
                                <div class="alert alert-error">Resit ditolak: {{ $correctionPayment->metadata['rejection_reason'] ?? '' }}. Sila hantar semula resit yang betul.</div>
                            @endif

                            <form
                                class="correction-payment-form js-artwork-confirmation-form"
                                data-confirm-title="Hantar permintaan pembetulan?"
                                data-confirm-message="Permintaan, caj RM10 dan bukti pembayaran akan dihantar untuk semakan. Pastikan semua pembetulan telah dinyatakan dengan jelas."
                                data-confirm-button="Ya, hantar pembetulan"
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
                                <label for="correction-{{ $designJob->id }}">Maklumat pembetulan</label>
                                <textarea
                                    id="correction-{{ $designJob->id }}"
                                    name="correction_comment"
                                    rows="5"
                                    maxlength="5000"
                                    required
                                    placeholder="Contoh: Sila betulkan ejaan nama pengantin perempuan daripada ... kepada ..."
                                >{{ (int) old('correction_job_id') === $designJob->id ? old('correction_comment') : ($correctionPayment?->status === 'FAILED' ? $correctionPayment->metadata['correction_comment'] : '') }}</textarea>
                                <p class="field-help">Maksimum 5,000 aksara.</p>

                                <label class="correction-fee-consent">
                                    <input type="checkbox" name="correction_fee_agreed" value="1" required aria-controls="correction-payment-{{ $designJob->id }}" @checked((int) old('correction_job_id') === $designJob->id && old('correction_fee_agreed'))>
                                    <span>Saya bersetuju dengan caj pembetulan RM10.</span>
                                </label>
                                <div class="correction-payment-details" id="correction-payment-{{ $designJob->id }}">
                                    <strong>Bayaran pembetulan: RM10</strong>
                                    <img class="correction-qr" src="{{ asset(config('kingkadkahwin.deposit.qr_image')) }}" alt="QR pembayaran pembetulan RM10">
                                    <label for="correction-receipt-{{ $designJob->id }}">Lampirkan resit pembayaran</label>
                                    <p class="field-help">JPG, JPEG, PNG, WEBP atau PDF. Maksimum 10 MB.</p>
                                    <input id="correction-receipt-{{ $designJob->id }}" type="file" name="correction_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                    <button type="button" class="button button-secondary correction-file-cancel" aria-controls="correction-receipt-{{ $designJob->id }}" hidden>Batal</button>
                                </div>

                                <button type="submit" class="button button-danger-outline">
                                    Hantar Permintaan Pembetulan
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($designJob->status === 'CORRECTION_REQUESTED')
                    <div class="state-panel state-warning">
                        <h3>Pembetulan telah diminta</h3>
                        <p>Designer sedang membuat pembetulan. Artwork versi baharu akan tersedia selepas siap.</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_APPROVED')
                    <div class="state-panel state-success">
                        <h3>Artwork telah diluluskan</h3>
                        <p>Tiada tindakan lanjut diperlukan untuk pakej ini.</p>
                    </div>
                @elseif ($designJob->status === 'DESIGN_IN_PROGRESS')
                    <div class="state-panel state-neutral">
                        <h3>Artwork sedang disediakan</h3>
                        <p>Sila tunggu sehingga designer menandakan artwork sedia untuk semakan.</p>
                    </div>
                @elseif ($designJob->status === 'READY_FOR_DESIGN')
                    <div class="state-panel state-neutral">
                        <h3>Menunggu proses design</h3>
                        <p>Artwork untuk pakej ini belum mula diproses.</p>
                    </div>
                @endif

                @if ($correctionHistory->isNotEmpty())
                    <div class="history-panel">
                        <h3>Sejarah Pembetulan</h3>

                        <div class="history-list">
                            @foreach ($correctionHistory as $action)
                                <article class="history-item">
                                    <div class="history-item-header">
                                        <strong>Permintaan pembetulan</strong>
                                        @if ($action->acted_at)
                                            <time datetime="{{ $action->acted_at->toIso8601String() }}">
                                                {{ $action->acted_at->format('d/m/Y H:i') }}
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
            <div class="empty-state page-empty-state">
                <strong>Tiada artwork untuk disemak buat masa ini.</strong>
                <p>Sila cuba semula selepas proses design bermula.</p>
            </div>
        @endforelse
    </main>

    <div class="page-actions">
        <a
            class="button button-secondary"
            href="{{ route('public.orders.progress', ['order_id' => $order->order_id]) }}"
        >
            &larr; Kembali ke Semak Progress
        </a>
    </div>
</div>
<dialog id="artwork-confirmation-dialog" class="artwork-confirmation-dialog" aria-labelledby="artwork-confirmation-title">
    <div class="artwork-confirmation-icon" aria-hidden="true">?</div>
    <h2 id="artwork-confirmation-title">Sahkan tindakan?</h2>
    <p id="artwork-confirmation-message"></p>
    <div class="artwork-confirmation-actions">
        <button id="cancel-artwork-confirmation" type="button">Tidak, kembali</button>
        <button id="confirm-artwork-action" type="button">Ya, teruskan</button>
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
        pendingArtworkForm.submit();
    });
</script>
</body>
</html>
