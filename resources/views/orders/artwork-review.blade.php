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
                $correctionHistory = $designJob->reviewActions
                    ->where('action', 'CORRECTION_REQUESTED')
                    ->sortByDesc('acted_at');

                $canPreview = $latestArtwork
                    && in_array($designJob->status, ['DESIGN_READY', 'CORRECTION_REQUESTED', 'DESIGN_APPROVED'], true);
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
                            <a
                                class="button button-secondary"
                                href="{{ route('orders.artwork.preview', [
                                    'orderId' => $order->order_id,
                                    'designJobId' => $designJob->id,
                                ]) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Lihat Artwork
                            </a>
                        @endif
                    </div>
                @else
                    <div class="empty-state">
                        <strong>Artwork belum tersedia.</strong>
                        <p>Designer sedang menyediakan artwork untuk pakej ini.</p>
                    </div>
                @endif

                @if ($designJob->status === 'DESIGN_READY' && ! $isTerminalOrder)
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
                                onsubmit="return confirm('Anda pasti mahu meluluskan artwork ini? Selepas diluluskan, artwork akan diteruskan ke proses seterusnya.');"
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

                            <form
                                method="POST"
                                action="{{ route('orders.artwork.correction', [
                                    'orderId' => $order->order_id,
                                    'designJobId' => $designJob->id,
                                ]) }}"
                            >
                                @csrf
                                <label for="correction-{{ $designJob->id }}">Maklumat pembetulan</label>
                                <textarea
                                    id="correction-{{ $designJob->id }}"
                                    name="correction_comment"
                                    rows="5"
                                    maxlength="5000"
                                    required
                                    placeholder="Contoh: Sila betulkan ejaan nama pengantin perempuan daripada ... kepada ..."
                                ></textarea>
                                <p class="field-help">Maksimum 5,000 aksara.</p>

                                <button type="submit" class="button button-danger-outline">
                                    Minta Pembetulan
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
</body>
</html>
