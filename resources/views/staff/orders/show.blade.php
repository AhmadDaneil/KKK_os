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
    $customerFeedbackMessage = "Salam {$order->customer_name}, terima kasih kerana memilih King Kad Kahwin. Kami ingin mendapatkan maklum balas anda dan membantu jika ada sebarang pertanyaan tentang tempahan {$order->order_id}.";
    $customerPaymentReminder = "Salam {$order->customer_name}, ini peringatan mesra daripada King Kad Kahwin mengenai baki bayaran atau caj bagi tempahan {$order->order_id}. Sila hubungi kami jika anda perlukan bantuan.";
    $hasDesignJobs = $order->relationLoaded('designJobs');
    $batchArtworkJobs = $hasDesignJobs
        ? $order->designJobs->filter(fn ($job) =>
            auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
            && $job->assigned_user_id === auth()->id()
            && $job->status === 'DESIGN_IN_PROGRESS'
        )
        : collect();
    $usesBatchArtworkUpload = $batchArtworkJobs->count() > 1;
    $canUsePhotoshop = auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER)
        && $hasDesignJobs
        && $order->designJobs->contains('assigned_user_id', auth()->id());
    $statusLabels = [
        'DETAILS_INCOMPLETE' => 'Maklumat belum lengkap', 'READY_FOR_DESIGN' => 'Sedia untuk design',
        'DESIGN_IN_PROGRESS' => 'Reka bentuk sedang berjalan', 'DESIGN_READY' => 'Menunggu semakan customer',
        'CORRECTION_REQUESTED' => 'Pembetulan diperlukan', 'DESIGN_APPROVED' => 'Design diluluskan',
        'BALANCE_PENDING' => 'Menunggu bayaran baki', 'READY_FOR_PRINT' => 'Sedia untuk production',
        'PRINTING' => 'Pengeluaran sedang berjalan', 'PRINTED' => 'Pengeluaran siap',
        'READY_FOR_PACKING' => 'Sedia untuk pembungkusan', 'PACKING' => 'Pembungkusan sedang berjalan',
        'PACKED' => 'Pembungkusan siap', 'READY_FOR_PICKUP' => 'Sedia untuk pengambilan',
        'SHIPPED' => 'Telah dihantar', 'COMPLETED' => 'Tempahan selesai',
        'CANCELLED' => 'Tempahan dibatalkan', 'ARCHIVED' => 'Tempahan diarkibkan',
    ];
    $statusTone = static fn (string $status): string => match ($status) {
        'DETAILS_INCOMPLETE', 'CORRECTION_REQUESTED' => 'attention',
        'DESIGN_READY', 'BALANCE_PENDING', 'READY_FOR_PICKUP', 'SHIPPED' => 'waiting',
        'COMPLETED', 'ARCHIVED' => 'complete', 'CANCELLED' => 'attention', default => 'active',
    };
    $nextActions = [
        'DETAILS_INCOMPLETE' => 'Dapatkan maklumat customer yang belum lengkap',
        'READY_FOR_DESIGN' => 'Mulakan atau assign kerja design', 'DESIGN_IN_PROGRESS' => 'Teruskan kerja design',
                    'DESIGN_READY' => 'Tunggu semakan hasil design daripada pelanggan', 'CORRECTION_REQUESTED' => 'Selesaikan pembetulan hasil design',
        'DESIGN_APPROVED' => 'Semak bayaran baki', 'BALANCE_PENDING' => 'Semak bayaran baki',
        'READY_FOR_PRINT' => 'Mulakan production', 'PRINTING' => 'Kemas kini atau siapkan production',
        'PRINTED' => 'Mulakan packing', 'READY_FOR_PACKING' => 'Mulakan packing',
        'PACKING' => 'Sahkan item dan siapkan packing', 'PACKED' => 'Aturkan serahan atau pickup',
        'READY_FOR_PICKUP' => 'Maklumkan customer untuk pickup', 'SHIPPED' => 'Pantau penghantaran',
        'COMPLETED' => 'Tiada tindakan lanjut diperlukan',
        'CANCELLED' => 'Tiada tindakan operasi; rekod dikekalkan untuk audit',
        'ARCHIVED' => 'Tiada tindakan lanjut; rekod disimpan dalam arkib',
    ];
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $order->order_id }} - Staf KKK OS</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-compact.css') }}?v={{ filemtime(public_path('css/dashboard-compact.css')) }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()]) data-staff-theme="{{ auth()->user()->staff_theme }}">
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? __('ui.admin_operations') : __('ui.staff_operations') }} · {{ __('ui.order_detail') }}</p><h1>{{ $order->order_id }}</h1></div>
                <div class="staff-topbar-actions">
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
        <strong>Tindakan tidak dapat diselesaikan.</strong>

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
                        &larr; Tempahan
                    </a>

                    <div class="staff-detail-heading">
                        <h1>{{ $order->order_id }}</h1>

                        <span class="staff-status staff-status--{{ $statusTone($order->status) }}">
                            {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>

                    <p>
                        {{ $order->customer_name ?: 'Nama pelanggan tidak tersedia' }}
                    </p>
                </div>

                <div class="staff-page-count">
                    {{ $order->package_count }}
                    pakej
                </div>
            </div>

            <section class="staff-order-brief" aria-label="Ringkasan tindakan order">
                <div><span>Status semasa</span><strong>{{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}</strong></div>
                <div><span>Tindakan seterusnya</span><strong>{{ $nextActions[$order->status] ?? 'Buka setiap bahagian untuk semakan' }}</strong></div>
            </section>

            @if (auth()->user()->isOperationManagement() && ! $order->isTerminalOperationalStatus())
                <section class="staff-card" aria-labelledby="order-lifecycle-heading">
                    <h2 id="order-lifecycle-heading">Tutup Tempahan Tanpa Memadam Rekod</h2>
                    <p>Gunakan tindakan ini hanya selepas semakan operasi. Tempahan dan audit trail akan dikekalkan.</p>

                    <div class="staff-payment-actions">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.cancel', $order) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Batalkan tempahan {{ $order->order_id }}?" data-confirm-message="Rekod tempahan akan dikekalkan dengan status CANCELLED dan tidak boleh diaktifkan semula." data-confirm-button="Ya, batalkan tempahan" data-confirm-tone="danger">
                            @csrf
                            <label for="cancel-reason">Sebab pembatalan</label>
                            <textarea id="cancel-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>
                            <button class="staff-button staff-button-danger" type="submit">Batalkan Tempahan</button>
                        </form>

                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.archive', $order) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Arkibkan tempahan {{ $order->order_id }}?" data-confirm-message="Rekod tempahan akan dikekalkan dengan status ARCHIVED dan tidak boleh diaktifkan semula." data-confirm-button="Ya, arkibkan tempahan">
                            @csrf
                            <label for="archive-reason">Sebab arkib</label>
                            <textarea id="archive-reason" name="reason" maxlength="1000" required>{{ old('reason') }}</textarea>
                            <button class="staff-button staff-button-secondary" type="submit">Arkibkan Tempahan</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView)
                <nav class="staff-order-sections" aria-label="Bahagian order">
                    <a href="#customer-data">Pelanggan</a><a href="#design-work">Design</a><a href="#payment">Pembayaran</a><a href="#production">Pengeluaran</a><a href="#packing">Pembungkusan</a><a href="#fulfilment">Pemenuhan Tempahan</a>
                </nav>
            @endif

            @if (! auth()->user()->isOperationManagement())
                <p class="staff-work-message">Maklumat tempahan ini untuk bacaan sahaja.</p>
            @endif

            @if ($hasFullQueueView)
            <div id="customer-data" class="staff-detail-grid staff-department-group">
                <section class="staff-card">
                    <h2>Ringkasan Tempahan</h2>

                    <dl class="staff-detail-list">
                        <div>
                            <dt>Status</dt>
                            <dd>{{ str_replace('_', ' ', $order->status) }}</dd>
                        </div>

                        <div>
                            <dt>Pakej</dt>
                            <dd>{{ $order->package_count }}</dd>
                        </div>

                        <div>
                            <dt>Jumlah kad</dt>
                            <dd>{{ $order->card_quantity ?? '-' }}</dd>
                        </div>

                        <div>
                            <dt>Bayaran Tempahan</dt>
                            <dd>{{ $order->booking_payment_status ?? '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="staff-card">
                    <h2>Pelanggan</h2>

                    <dl class="staff-detail-list">
                        <div>
                            <dt>Nama</dt>
                            <dd>{{ $order->customer_name ?: '-' }}</dd>
                        </div>

                        <div>
                            <dt>E-mel</dt>
                            <dd>{{ $order->customer_email ?: '-' }}</dd>
                        </div>

                        <div>
                            <dt>Telefon</dt>
                            <dd>{{ $order->customer_phone ?: '-' }}</dd>
                        </div>
                    </dl>

                    @if (auth()->user()->isCustomerService() && $customerWhatsappNumber !== '')
                        <div class="staff-customer-contact-actions">
                            <a class="staff-button staff-button-primary" target="_blank" rel="noopener" href="https://wa.me/{{ $customerWhatsappNumber }}?text={{ rawurlencode($customerFeedbackMessage) }}">Mesej</a>
                            <a class="staff-button" target="_blank" rel="noopener" href="https://wa.me/{{ $customerWhatsappNumber }}?text={{ rawurlencode($customerPaymentReminder) }}">Peringatan Bayaran</a>
                        </div>
                    @endif
                </section>
            </div>

            <section class="staff-section staff-timeline-section">
                <div class="staff-lifecycle-heading">
                    <div><p class="staff-kicker">Perjalanan tempahan</p><h2>Progress tempahan</h2></div>
                    <span>Status semasa ditandakan dengan warna utama</span>
                </div>

                <ol class="staff-lifecycle-list" aria-label="Peringkat tempahan">
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
                        <summary>Lihat rekod aktiviti terperinci ({{ $timelineEvents->count() }})</summary>
                        <ol class="staff-timeline">
                            @foreach ($timelineEvents as $event)
                                <li class="staff-timeline-item">
                                    <span class="staff-timeline-marker" aria-hidden="true"></span>
                                    <div class="staff-timeline-content">
                                        <div class="staff-timeline-heading">
                                            <strong>{{ str_replace('_', ' ', $event['to_status'] ?: $event['event_type']) }}</strong>
                                            <time datetime="{{ $event['occurred_at']?->toIso8601String() }}">{{ $event['occurred_at']?->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') ?? '-' }}</time>
                                        </div>
                                        <p>{{ $event['actor']?->name ?? 'Sistem' }} @if ($event['from_status'] && $event['from_status'] !== $event['to_status'])<span>· {{ str_replace('_', ' ', $event['from_status']) }} → {{ str_replace('_', ' ', $event['to_status']) }}</span>@endif</p>
                                        @if ($event['reason'])<small>{{ $event['reason'] }}</small>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            </section>
            @endif

            <section class="staff-section">
                <h2 class="staff-section-title">Pakej</h2>

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
                                            <dt>Tema Kad</dt>
                                            <dd>{{ $packageSide->design->theme ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Kod Design</dt>
                                            <dd>{{ $packageSide->design->design_code ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Tajuk Kad</dt>
                                            <dd>{{ $packageSide->design->card_title ?? '-' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @php
                                $selectedProducts = collect($packageSide->additional_products ?? [])
                                    ->filter(fn ($product) => data_get($product, 'enabled'));
                            @endphp
                            @if ($selectedProducts->isNotEmpty())
                                <div class="staff-detail-group">
                                    <h4>Produk Tambahan</h4>
                                    <dl class="staff-detail-list">
                                        @foreach ($selectedProducts as $productKey => $product)
                                            <div class="staff-detail-wide">
                                                <dt>{{ $productKey === 'banner' ? 'Banner' : 'Banting' }}</dt>
                                                <dd>{{ data_get($product, 'quantity', 1) }} unit · {{ data_get($product, 'size') ?: 'Saiz belum dinyatakan' }} · {{ data_get($product, 'orientation') === 'LANDSCAPE' ? 'Melintang' : (data_get($product, 'orientation') === 'PORTRAIT' ? 'Menegak' : 'Orientasi belum dinyatakan') }}@if(data_get($product, 'material')) · {{ data_get($product, 'material') }}@endif @if(data_get($product, 'instructions'))<br>{{ data_get($product, 'instructions') }}@endif</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif

                            @if ($packageSide->parents)
                                <div class="staff-detail-group">
                                    <h4>Ibu Bapa</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Nama Bapa</dt>
                                            <dd>{{ $packageSide->parents->father_name ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Nama Ibu</dt>
                                            <dd>{{ $packageSide->parents->mother_name ?? '-' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @if ($packageSide->event)
                                <div class="staff-detail-group">
                                    <h4>Majlis</h4>

                                    <dl class="staff-detail-list">
                                        <div>
                                            <dt>Hari</dt>
                                            <dd>{{ $packageSide->event->day_name ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Tarikh</dt>
                                            <dd>{{ $packageSide->event->event_date?->format('Y-m-d') ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Tarikh Hijrah</dt>
                                            <dd>{{ $packageSide->event->hijri_date ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Masa Jamuan</dt>
                                            <dd>{{ $packageSide->event->meal_time ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Bersanding</dt>
                                            <dd>{{ $packageSide->event->bersanding_time ?? '-' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Tempat Majlis</dt>
                                            <dd>{{ $packageSide->event->venue_name ?? '-' }}</dd>
                                        </div>

                                        <div class="staff-detail-wide">
                                            <dt>Alamat</dt>
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
                                                Buka Google Maps
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                @if ($packageSide->event->contacts->isNotEmpty())
                                    <div class="staff-detail-group">
                                        <h4>Wakil Untuk Dihubungi</h4>

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
                    <h2 class="staff-section-title">Kerja Design</h2>

                    @if ($canUsePhotoshop)
                        <div class="staff-design-tools">
                            <div>
                                <p class="staff-kicker">Photoshop Auto Merge V11</p>
                                <h3>Sediakan tempahan pelanggan ini dalam Photoshop</h3>
                                <ol>
                                    <li>Muat turun fail CSV tempahan pelanggan.</li>
                                    <li>Buka Photoshop; JavaScript yang diluluskan akan bermula secara automatik.</li>
                                    <li>Pilih folder ROOT, kemudian pilih fail CSV yang telah dimuat turun.</li>
                                </ol>
                                <p class="staff-design-tools-note">
                                    Photoshop akan dibuka pada komputer Windows yang menjalankan KKK OS.
                                </p>
                            </div>

                            <div class="staff-design-tools-actions">
                                <a
                                    href="{{ route('staff.orders.photoshop.csv', $order) }}"
                                    class="staff-button"
                                >
                                    Muat Turun CSV Pelanggan
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('staff.orders.photoshop.launch', $order) }}"
                                >
                                    @csrf

                                    <button type="submit" class="staff-button staff-button-primary">
                                        Buka Photoshop
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="staff-work-grid">
                        @forelse ($order->designJobs as $job)
                            <article class="staff-card" data-design-job-id="{{ $job->id }}">
                                <div class="staff-card-heading">
                                    <h3>{{ $job->side }}</h3>

                                    <span class="staff-status">
                                        {{ str_replace('_', ' ', $job->status) }}
                                    </span>
                                </div>

                                <dl class="staff-detail-list">
                                    <div>
                                        <dt>Ditugaskan</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Belum ditugaskan' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Dimulakan</dt>
                                        <dd>{{ $job->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Sedia</dt>
                                        <dd>{{ $job->design_ready_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Versi Hasil Design</dt>
                                        <dd>{{ $job->artworkVersions->count() }}</dd>
                                    </div>
                                </dl>
                                @php
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

                                @if (filled($correctionComment))
                                    <aside class="staff-correction-note" aria-label="Arahan pembetulan pelanggan">
                                        <div class="staff-correction-note-heading">
                                            <span class="staff-correction-note-icon" aria-hidden="true">✎</span>
                                            <div>
                                                <span>Komen pelanggan</span>
                                                <h4>Arahan Pembetulan Artwork</h4>
                                            </div>
                                            <span class="staff-correction-note-state">
                                                {{ $latestCorrectionPayment?->status === 'PENDING' ? 'Menunggu semakan bayaran' : 'Untuk tindakan designer' }}
                                            </span>
                                        </div>
                                        <p><strong>Bahagian terlibat:</strong> {{ collect($correctionAssets)->map(fn ($asset) => ['CARD' => 'Kad Kahwin', 'BANNER' => 'Banner', 'BANTING' => 'Banting'][$asset] ?? $asset)->join(', ') }}</p>
                                        <blockquote>{{ $correctionComment }}</blockquote>
                                        <footer>
                                            <span>Pakej {{ ucfirst(strtolower($job->side)) }}</span>
                                            @if ($correctionSubmittedAt)
                                                <time datetime="{{ $correctionSubmittedAt->toIso8601String() }}">
                                                    Dihantar {{ $correctionSubmittedAt->timezone(config('app.display_timezone'))->format('d/m/Y, h:i A') }}
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
                                                data-confirm-title="Mulakan kerja design {{ ucfirst(strtolower($job->side)) }}?"
                                                data-confirm-message="Status pakej ini akan ditukar kepada sedang design dan masa mula akan direkodkan."
                                                data-confirm-button="Ya, mula design"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="staff-button staff-button-primary"
                                                >
                                                    Mulakan Design
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
                                                    Sambung Pembetulan
                                                </button>
                                            </form>
                                        @elseif ($job->status === 'DESIGN_IN_PROGRESS')
                                            @php
                                                $isBatchArtworkJob = $usesBatchArtworkUpload
                                                    && $batchArtworkJobs->contains('id', $job->id);
                                            @endphp
                                            <div class="staff-design-upload">
                                                <div class="staff-design-upload-heading">
                                                    <h4>Muat Naik Versi Hasil Design</h4>

                                                    <p>
                                                        Muat naik fail sumber yang boleh disunting dan
                                                        preview pelanggan.
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
                                                        data-confirm-title="Muat naik hasil design {{ ucfirst(strtolower($job->side)) }}?"
                                                        data-confirm-message="Pastikan fail sumber dan pratonton pelanggan yang dipilih adalah betul. Fail ini akan disimpan sebagai versi baharu hasil design."
                                                        data-confirm-button="Ya, muat naik"
                                                    >
                                                        @csrf
                                                @endif

                                                    <div class="staff-field">
                                                        <label for="source-artwork-{{ $job->id }}">
                                                            Fail Sumber Hasil Design
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
                                                            <button type="button" class="staff-file-add" aria-controls="source-artwork-{{ $job->id }}">+ Tambah Fail</button>
                                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            PSD atau PDF. Maksimum 100 MB.
                                                        </span>
                                                    </div>

                                                    @php
                                                        $jobProducts = $order->packageSides
                                                            ->firstWhere('id', $job->order_package_side_id)?->additional_products ?? [];
                                                    @endphp
                                                    <div class="staff-supplementary-artworks">
                                                        <div class="staff-supplementary-heading">
                                                            <h4>Artwork Tambahan</h4>
                                                            <p>@if(collect($jobProducts)->contains(fn ($product) => data_get($product, 'enabled'))) Pelanggan telah meminta produk tambahan. Semak spesifikasi pada bahagian Pakej di atas. @else Pelanggan tidak memilih banner atau banting untuk pakej ini. @endif</p>
                                                        </div>
                                                        <div class="staff-supplementary-grid">
                                                            <div class="staff-field">
                                                                <label for="banner-preview-{{ $job->id }}">Preview Banner <span class="staff-optional">{{ data_get($jobProducts, 'banner.enabled') ? '(diminta pelanggan)' : '(pilihan)' }}</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--banner" aria-hidden="true">2 : 1</span>
                                                                <input id="banner-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][banner_preview]' : 'banner_preview' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                                                <span class="staff-field-help">Format melintang 2:1 seperti 1280 × 640 px.</span>
                                                            </div>
                                                            <div class="staff-field">
                                                                <label for="banting-preview-{{ $job->id }}">Preview Banting <span class="staff-optional">{{ data_get($jobProducts, 'banting.enabled') ? '(diminta pelanggan)' : '(pilihan)' }}</span></label>
                                                                <span class="staff-artwork-ratio staff-artwork-ratio--banting" aria-hidden="true">1 : 2</span>
                                                                <input id="banting-preview-{{ $job->id }}" type="file" name="{{ $isBatchArtworkJob ? 'artworks['.$job->id.'][banting_preview]' : 'banting_preview' }}" @if ($isBatchArtworkJob) form="batch-artwork-upload" @endif accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                                                <span class="staff-field-help">Format menegak 1:2 seperti 640 × 1280 px.</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="staff-field">
                                                        <label for="customer-preview-{{ $job->id }}">
                                                            Pratonton Pelanggan
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
                                                            <button type="button" class="staff-file-add" aria-controls="customer-preview-{{ $job->id }}">+ Tambah Fail</button>
                                                            <ul class="staff-selected-files" aria-live="polite"></ul>
                                                        </div>

                                                        <span class="staff-field-help">
                                                            Format JPG atau PNG sahaja. Saiz maksimum 20 MB. Saiz dan kualiti pratonton pelanggan akan dikurangkan, kemudian dilindungi dengan tera air King Kad Kahwin.
                                                        </span>
                                                    </div>

                                                    <div class="staff-field">
                                                        <label for="internal-note-{{ $job->id }}">
                                                            Nota Dalaman
                                                            <span class="staff-optional">(pilihan)</span>
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
                                                            Muat Naik Hasil Design
                                                        </button>
                                                        <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                                                        </form>
                                                    @endif

                                                <div class="staff-artwork-ready-panel" data-artwork-ready-panel @if ($job->artworkVersions->isEmpty()) hidden @endif>
                                                        <div>
                                                            <strong>Hasil design telah dimuat naik</strong>
                                                            <p>
                                                                Versi <span data-artwork-version>{{ $job->artworkVersions->max('version_number') }}</span> ialah versi terkini.
                                                                Hantar kepada customer apabila preview sudah diperiksa.
                                                            </p>
                                                        </div>

                                                        <form
                                                            method="POST"
                                                            action="{{ route($operationRoutePrefix.'design-jobs.mark-ready', $job) }}"
                                                            class="js-staff-confirmation-form"
                                                            data-confirm-title="Hantar hasil design {{ ucfirst(strtolower($job->side)) }} kepada pelanggan?"
                                                            data-confirm-message="Versi {{ $job->artworkVersions->max('version_number') }} akan dihantar untuk semakan pelanggan. Pastikan pratonton telah diperiksa dan merupakan versi yang betul."
                                                            data-confirm-button="Ya, hantar untuk semakan"
                                                        >
                                                            @csrf
                                                            <button
                                                                type="submit"
                                                                class="staff-button staff-button-primary"
                                                            >
                                                                Hantar Untuk Semakan Pelanggan
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <p class="staff-work-message" data-artwork-upload-hint @if ($job->artworkVersions->isNotEmpty()) hidden @endif>
                                                        Muat naik fail sumber dan pratonton pelanggan terlebih dahulu.
                                                        Selepas itu, butang untuk menghantar hasil design kepada pelanggan akan dipaparkan.
                                                    </p>
                                            </div>
                                        @elseif ($job->status === 'DESIGN_READY')
                                            <p class="staff-work-message">
                                                Hasil design sedia untuk semakan pelanggan.
                                            </p>
                                        @endif
                                    </div>
                                @endif
                                @if (auth()->user()->isOperationManagement() && ! $order->isAssignmentLocked() && ! request()->attributes->get('staff_overview_mode', false))
    <form
        method="POST"
        action="{{ route($operationRoutePrefix.'design-jobs.assign', $job) }}"
        class="staff-assignment-form js-staff-confirmation-form js-async-assignment"
        data-confirm-assignment="Pereka"
        data-confirm-mode="{{ $job->assigned_user_id ? 'reassign' : 'assign' }}"
    >
        @csrf

        <label for="design-assignee-{{ $job->id }}">
            {{ $job->assigned_user_id ? 'Tugaskan Semula Pereka' : 'Tugaskan Pereka' }}
        </label>

        <div class="staff-assignment-controls">
            <select
                id="design-assignee-{{ $job->id }}"
                name="assigned_user_id"
                required
            >
                <option value="">Pilih pereka</option>

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
                {{ $job->assigned_user_id ? 'Tugaskan Semula' : 'Tugaskan' }}
            </button>
        </div>
    </form>
@endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                Tiada tugasan design tersedia.
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
                            data-confirm-title="Muat naik kedua-dua hasil design?"
                            data-confirm-message="Pastikan fail sumber dan pratonton pelanggan untuk pakej Lelaki serta Perempuan adalah betul. Kedua-duanya akan disimpan sebagai versi baharu hasil design."
                            data-confirm-button="Ya, upload kedua-duanya"
                        >
                            @csrf
                            <button type="submit" class="staff-button staff-button-primary">
                                Muat Naik Kedua-dua Hasil Design
                            </button>
                            <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                        </form>
                    @endif
                </section>
            @endif

            @if ($hasFullQueueView)
            <section id="payment" class="staff-section">
                <h2 class="staff-section-title">Pembayaran</h2>

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
                                    <dt>Jumlah</dt>
                                    <dd>{{ $payment->currency }} {{ $payment->amount }}</dd>
                                </div>

                                <div>
                                    <dt>Kaedah</dt>
                                    <dd>{{ $payment->provider ?: '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Tarikh Bayaran</dt>
                                    <dd>{{ $payment->paid_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                </div>
                            </dl>

                            @if (in_array($payment->payment_type, ['BOOKING_DEPOSIT', 'BALANCE', 'ARTWORK_CORRECTION'], true) && ! empty($payment->metadata['receipt_path']))
                                <div class="staff-payment-actions">
                                    @if (auth()->user()->isOperationManagement())
                                        <div class="staff-receipt-action-row">
                                            <div><strong>Bukti pembayaran</strong><small>Semak resit sebelum mengesahkan bayaran.</small></div>
                                            <a class="staff-button staff-receipt-button" target="_blank" rel="noopener" href="{{ route($operationRoutePrefix.'payments.receipt', $payment) }}" title="Buka resit pembayaran dalam tab baharu"><span aria-hidden="true">▤</span> Lihat Resit <small aria-hidden="true">↗</small></a>
                                        </div>
                                    @endif
                                    @if ($payment->payment_type === 'ARTWORK_CORRECTION')
                                        <aside class="staff-correction-note staff-correction-note--payment" aria-label="Komen pembetulan pelanggan">
                                            <div class="staff-correction-note-heading">
                                                <span class="staff-correction-note-icon" aria-hidden="true">✎</span>
                                                <div><span>Komen pelanggan</span><h4>Arahan Pembetulan Artwork</h4></div>
                                            </div>
                                            <blockquote>{{ $payment->metadata['correction_comment'] ?? 'Tiada komen diberikan.' }}</blockquote>
                                            <footer><span>Caj pembetulan RM10</span></footer>
                                        </aside>
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
                                    @if ($payment->payment_type === 'BOOKING_DEPOSIT' && auth()->user()->isOperationManagement() && $payment->status === 'PENDING')
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.approve', $payment) }}" class="staff-field js-staff-confirmation-form" data-confirm-title="Sahkan bayaran deposit?" data-confirm-message="Pastikan jumlah bayaran pada resit telah dimasukkan dengan betul. Selepas disahkan, tempahan akan diteruskan ke proses seterusnya." data-confirm-button="Ya, sahkan deposit">
                                            @csrf
                                            <label for="payment-amount-{{ $payment->id }}">Jumlah bayaran pada resit (RM)</label>
                                            <input id="payment-amount-{{ $payment->id }}" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" placeholder="Contoh: 100.00" required>
                                            <button class="staff-button staff-button-primary" type="submit">Sahkan Deposit</button>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.deposit.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Tolak bayaran deposit?" data-confirm-message="Pelanggan perlu menghantar semula bukti pembayaran selepas deposit ditolak. Pastikan sebab penolakan telah ditulis dengan jelas." data-confirm-button="Ya, tolak deposit" data-confirm-tone="danger">
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
                                        <form method="POST" action="{{ route($operationRoutePrefix.'payments.balance.reject', $payment) }}" class="staff-reject-payment-form js-staff-confirmation-form" data-confirm-title="Tolak bayaran penuh?" data-confirm-message="Pelanggan perlu menghantar semula bukti pembayaran. Pastikan sebab penolakan telah ditulis dengan jelas." data-confirm-button="Ya, tolak bayaran" data-confirm-tone="danger">
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
                            Tiada transaksi pembayaran.
                        </div>
                    @endforelse
                </div>
            </section>
            @endif

            @if (($canAssignProduction ?? false) && ! $order->isAssignmentLocked() && auth()->user()->isOperationManagement() && ! request()->attributes->get('staff_overview_mode', false))
                <section class="staff-section">
                    <h2 class="staff-section-title">Tugaskan Staf Pengeluaran</h2>

                    <div class="staff-work-grid">
                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-printing', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form js-async-assignment" data-confirm-assignment="Pengeluaran" data-confirm-mode="{{ $order->printing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>Pengeluaran</h3>
                                <span class="staff-status">{{ $order->printingAssignedUser ? 'DITUGASKAN' : 'BELUM DITUGASKAN' }}</span>
                            </div>
                            <label for="order-printing-assignee">{{ $order->printing_assigned_user_id ? 'Tugaskan Semula Staf Pengeluaran' : 'Tugaskan Staf Pengeluaran' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-printing-assignee" name="assigned_user_id" required>
                                    <option value="">Pilih staf pengeluaran</option>
                                    @foreach ($printingStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected($order->printing_assigned_user_id === $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="staff-button staff-button-small">{{ $order->printing_assigned_user_id ? 'Tugaskan Semula' : 'Tugaskan' }}</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route($operationRoutePrefix.'orders.assign-packing-fulfilment', $order) }}" class="staff-card staff-assignment-form js-staff-confirmation-form js-async-assignment" data-confirm-assignment="OM (Pembungkusan & Pemenuhan Tempahan)" data-confirm-mode="{{ $order->packing_assigned_user_id ? 'reassign' : 'assign' }}">
                            @csrf
                            <div class="staff-card-heading">
                                <h3>OM: Pembungkusan &amp; Pemenuhan Tempahan</h3>
                                <span class="staff-status">{{ $order->packingAssignedUser ? 'DITUGASKAN' : 'BELUM DITUGASKAN' }}</span>
                            </div>
                            <label for="order-packing-assignee">{{ $order->packing_assigned_user_id ? 'Tugaskan Semula OM' : 'Tugaskan OM' }}</label>
                            <div class="staff-assignment-controls">
                                <select id="order-packing-assignee" name="assigned_user_id" required>
                                    <option value="">Pilih OM (Pembungkusan &amp; Pemenuhan Tempahan)</option>
                                    @foreach ($packingStaff as $staff)
                                        <option value="{{ $staff->id }}" @selected($order->packing_assigned_user_id === $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="staff-button staff-button-small">{{ $order->packing_assigned_user_id ? 'Tugaskan Semula' : 'Tugaskan' }}</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            @if ($order->relationLoaded('printJobs'))
                <section id="production" class="staff-section">
                    <h2 class="staff-section-title">Pengeluaran</h2>

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
                                        <dt>Nama pasangan</dt>
                                        <dd>
                                            @php($couple = $order->couples->firstWhere('couple_number', 1))
                                            {{ collect([
                                                $couple?->groom_abbreviation ?: $couple?->groom_name,
                                                $couple?->bride_abbreviation ?: $couple?->bride_name,
                                            ])->filter()->join(' & ') ?: '-' }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Label hanger</dt>
                                        <dd>{{ $job->side === 'LELAKI' ? 'Pengantin Lelaki' : 'Pengantin Perempuan' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Ditugaskan</dt>
                                        <dd>{{ $job->assignedUser?->name ?? 'Belum ditugaskan' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Dimulakan</dt>
                                        <dd>{{ $job->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Printed</dt>
                                        <dd>{{ $job->printed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>

                                    <div>
                                        <dt>Progress Dikemas Kini</dt>
                                        <dd>{{ $job->progress_updated_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                                    </div>
                                </dl>

                                @if (filled($job->progress_files))
                                    <div class="staff-detail-group">
                                        <h4>Fail Progress Pengeluaran</h4>

                                        <div class="staff-contact-list">
                                            @foreach ($job->progress_files as $file)
                                                <div class="staff-contact-row">
                                                    <span>{{ $file['original_name'] ?? 'Fail progress' }}</span>
                                                    <a href="{{ route($operationRoutePrefix.'print-jobs.progress-files.show', ['printJob' => $job, 'file' => $loop->index]) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">
                                                        Lihat · {{ isset($file['uploaded_at']) ? \Illuminate\Support\Carbon::parse($file['uploaded_at'])->timezone(config('app.display_timezone'))->format('Y-m-d H:i') : '-' }}
                                                    </a>
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
                                            data-confirm-title="Mulakan cetakan {{ ucfirst(strtolower($job->side)) }}?"
                                            data-confirm-message="Kuantiti yang akan dicetak ialah {{ $job->quantity ?? $order->card_quantity ?? '-' }} keping. Pastikan pakej dan kuantiti adalah betul."
                                            data-confirm-button="Ya, mula printing"
                                        >
                                            @csrf
                                            <button type="submit" class="staff-button staff-button-primary">Mulakan Cetakan</button>
                                        </form>
                                    @elseif ($job->status === 'WAITING_FOR_PAYMENT')
                                        <p class="staff-work-message">Menunggu pengesahan bayaran penuh sebelum cetakan boleh dimulakan.</p>
                                    @elseif ($job->status === 'PRINTING')
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('staff.print-jobs.progress-files.store', $job) }}" class="staff-packing-complete-form js-staff-confirmation-form js-async-progress-upload" data-confirm-title="Muat naik progress cetakan {{ ucfirst(strtolower($job->side)) }}?" data-confirm-message="Gambar atau PDF yang dipilih akan disimpan sebagai bukti progress cetakan semasa." data-confirm-button="Ya, muat naik progress">
                                            @csrf
                                            <label for="print-progress-{{ $job->id }}">Muat naik progress pengeluaran</label>
                                            <div class="staff-file-picker">
                                                <input id="print-progress-{{ $job->id }}" type="file" name="progress_files[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" multiple required>
                                                <button type="button" class="staff-file-cancel" hidden aria-controls="print-progress-{{ $job->id }}">Batal</button>
                                            </div>
                                            <p class="staff-muted-text">Muat naik gambar atau PDF yang menunjukkan progress pengeluaran. Maksimum 10 fail, 20 MB setiap satu.</p>
                                            <button type="submit" class="staff-button staff-button-secondary">Muat Naik Progress</button>
                                            <p class="staff-upload-notice" role="status" aria-live="polite" hidden></p>
                                        </form>
                                        <form method="POST" action="{{ route($operationRoutePrefix.'print-jobs.mark-printed', $job) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Tandakan cetakan {{ ucfirst(strtolower($job->side)) }} sebagai siap?" data-confirm-message="Pastikan semua {{ $job->quantity ?? $order->card_quantity ?? '-' }} keping kad telah selesai dicetak dan diperiksa sebelum meneruskan." data-confirm-button="Ya, tandakan siap" data-confirm-tone="danger">@csrf<button type="submit" class="staff-button staff-button-primary">Tandakan Cetakan Siap</button></form>
                                    @elseif ($job->status === 'PRINTED')
                                        <p class="staff-work-message">Cetakan untuk pakej ini telah siap.</p>
                                    @endif
                                @endif
                            </article>
                        @empty
                            <div class="staff-empty">
                                Tiada tugasan cetakan tersedia.
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView && $order->relationLoaded('packingJob') && $order->packingJob)
                <section id="packing" class="staff-section">
                    <h2 class="staff-section-title">Pembungkusan</h2>

                    <article class="staff-card">
                        <div class="staff-card-heading">
                            <h3>Kerja Pembungkusan</h3>

                            <span class="staff-status">
                                {{ str_replace('_', ' ', $order->packingJob->status) }}
                            </span>
                        </div>

                        <dl class="staff-detail-list">
                            <div>
                                <dt>Ditugaskan</dt>
                                <dd>{{ $order->packingJob->assignedUser?->name ?? 'Belum ditugaskan' }}</dd>
                            </div>

                            <div>
                                <dt>Dimulakan</dt>
                                <dd>{{ $order->packingJob->started_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Packed</dt>
                                <dd>{{ $order->packingJob->packed_at?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? '-' }}</dd>
                            </div>
                        </dl>
                        @if ($order->packingJob->items->isNotEmpty())
                            <div class="staff-detail-group">
                                <h4>Senarai Item Pembungkusan</h4>

                                <div class="staff-contact-list staff-packing-checklist">
                                    @foreach ($order->packingJob->items as $item)
                                        <div @class(['staff-contact-row', 'is-verified' => $item->verified_present])>
                                            <div class="staff-packing-item-name">
                                                <span>Pakej {{ ucfirst(strtolower($item->side)) }}</span>
                                                <small>{{ $item->verified_present ? 'Item telah diperiksa dan disahkan lengkap' : 'Belum diperiksa' }}</small>
                                            </div>

                                            @if ($item->verified_present)
                                                <strong class="staff-verified-badge"><span aria-hidden="true">✓</span> Disahkan</strong>
                                            @elseif (auth()->user()->isOperationManagement() && $order->packingJob->status === 'PACKING')
                                                <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.items.verify', ['packingJob' => $order->packingJob, 'packingItem' => $item]) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Sahkan item {{ ucfirst(strtolower($item->side)) }}?" data-confirm-message="Pastikan semua kad untuk pakej {{ ucfirst(strtolower($item->side)) }} ada, lengkap dan dalam keadaan baik sebelum disahkan." data-confirm-button="Ya, sahkan item">
                                                    @csrf
                                                    <button type="submit" class="staff-button staff-verify-button"><span aria-hidden="true">✓</span> Sahkan Item</button>
                                                </form>
                                            @else
                                                <strong class="staff-unverified-badge">Belum Disahkan</strong>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($order->packingJob->proof_storage_path)
                            <div class="staff-proof-status">
                                <strong>Bukti packing:</strong> {{ $order->packingJob->proof_original_name ?? 'Telah dimuat naik' }}

                                @if (auth()->user()->isOperationManagement())
                                    <a href="{{ route($operationRoutePrefix.'packing-jobs.proof.show', $order->packingJob) }}" class="staff-button staff-button-small" target="_blank" rel="noopener">Lihat</a>
                                @endif
                            </div>
                        @endif

                        @if (auth()->user()->isOperationManagement())
                            @if ($order->packingJob->status === 'READY_FOR_PACKING')
                                <form method="POST" action="{{ route($operationRoutePrefix.'packing-jobs.start', $order->packingJob) }}" class="staff-workflow-form js-staff-confirmation-form" data-confirm-title="Mulakan proses packing?" data-confirm-message="Masa mula packing akan direkodkan. Pastikan semua barang yang telah dicetak tersedia untuk diperiksa dan dibungkus." data-confirm-button="Ya, mula packing">@csrf<button type="submit" class="staff-button staff-button-primary">Mulakan Pembungkusan</button></form>
                            @endif
                            @if ($order->packingJob->status === 'PACKING')
                                @if ($order->packingJob->items->every(fn ($item) => $item->verified_present))
                                    <form method="POST" enctype="multipart/form-data" action="{{ route($operationRoutePrefix.'packing-jobs.mark-packed', $order->packingJob) }}" class="staff-packing-complete-form js-staff-confirmation-form" data-confirm-title="Muat naik bukti dan tandakan pembungkusan selesai?" data-confirm-message="{{ $order->fulfilment?->method === 'COURIER' ? 'Pastikan gambar bukti pembungkusan, nama kurier dan nombor penjejakan adalah betul. Pembungkusan akan ditandakan selesai selepas dihantar.' : 'Pastikan gambar bukti menunjukkan semua barang telah dibungkus dengan lengkap. Pembungkusan akan ditandakan selesai untuk Pengambilan Sendiri.' }}" data-confirm-button="Ya, tandakan selesai dibungkus">
                                        @csrf
                                        <label for="packing-proof">Bukti gambar barang telah dipack</label>
                                        <div class="staff-file-picker">
                                            <input id="packing-proof" type="file" name="packing_proof" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                                            <button type="button" class="staff-file-cancel" hidden aria-controls="packing-proof">Batal</button>
                                        </div>
                                        @if ($order->fulfilment?->method === 'COURIER')
                                            <label for="courier-provider">Nama courier</label><input id="courier-provider" name="courier_provider" value="{{ old('courier_provider') }}" placeholder="Contoh: Pos Laju" required>
                                            <label for="tracking-number">Nombor Tracking</label><input id="tracking-number" name="tracking_number" value="{{ old('tracking_number') }}" required>
                                        @endif
                                        <button type="submit" class="staff-button staff-button-primary">Muat Naik Bukti & Tandakan Selesai Dibungkus</button>
                                    </form>
                                @endif
                            @endif
                        @endif
                    </article>
                </section>
            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_OM) && $order->packing_assigned_user_id === auth()->id())
                <section id="packing" class="staff-section">
                    <h2 class="staff-section-title">Pembungkusan</h2>
                    <div class="staff-empty">
                        Kerja pembungkusan akan diwujudkan secara automatik selepas semua kerja cetakan selesai.
                    </div>
                </section>
            @endif

            @if ($hasFullQueueView)
            <section id="fulfilment" class="staff-section">
                <h2 class="staff-section-title">Pemenuhan Tempahan</h2>

                <article class="staff-card">
                    @if ($order->fulfilment)
                        <dl class="staff-detail-list">
                            <div>
                                <dt>Kaedah</dt>
                                <dd>{{ $order->fulfilment->method }}</dd>
                            </div>

                            <div>
                                <dt>Penerima</dt>
                                <dd>{{ $order->fulfilment->recipient_name ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt>Telefon</dt>
                                <dd>{{ $order->fulfilment->recipient_phone ?? '-' }}</dd>
                            </div>

                            <div class="staff-detail-wide">
                                <dt>Alamat Penghantaran</dt>
                                <dd>{{ $order->fulfilment->shipping_address ?? '-' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="staff-muted-text">
                            Tiada maklumat pemenuhan tempahan.
                        </p>
                    @endif

                    @if ($order->fulfilmentJob)
                        <div class="staff-detail-group">
                            <h4>Kerja Pemenuhan Tempahan</h4>

                            <dl class="staff-detail-list">
                                <div>
                                    <dt>Job Status</dt>
                                    <dd>{{ $order->fulfilmentJob->status }}</dd>
                                </div>

                                <div>
                                    <dt>Kurier</dt>
                                    <dd>{{ $order->fulfilmentJob->courier_provider ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Nombor Tracking</dt>
                                    <dd>{{ $order->fulfilmentJob->tracking_number ?? '-' }}</dd>
                                </div>

                                <div>
                                    <dt>Rujukan Penyelesaian</dt>
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
                                        <h4>Penyelesaian Pengambilan</h4>

                                        <form
                                            method="POST"
                                            action="{{ route($operationRoutePrefix.'packing-jobs.collect-pickup', $order->packingJob) }}"
                                            class="staff-workflow-form"
                                        >
                                            @csrf

                                            <label for="completion-reference">
                                                Rujukan Penyelesaian
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
                                                Tandakan Pesanan Telah Diambil
                                            </button>
                                        </form>

                                    @elseif ($order->fulfilmentJob->method === 'COURIER')
                                        <h4>Serahan kepada Kurier</h4>

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
                                                Nombor Tracking
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
                                                Sahkan Serahan kepada Kurier
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
                    remove.textContent = 'Padam';
                    remove.setAttribute('aria-label', 'Padam ' + file.name);
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

                if (form.dataset.confirmCourier !== undefined) {
                    const courierName = form.querySelector('[name="courier_provider"]').value.trim();
                    const trackingNumber = form.querySelector('[name="tracking_number"]').value.trim();

                    title.textContent = 'Sahkan serahan kepada ' + courierName + '?';
                    message.textContent = 'Nombor tracking ' + trackingNumber + ' akan dipaparkan kepada pelanggan dan bungkusan akan ditandakan telah diserahkan. Pastikan bukti serta maklumat tracking adalah betul.';
                    confirmButton.textContent = 'Ya, sahkan serahan';
                } else if (form.dataset.confirmAssignment) {
                    const assignee = form.querySelector('[name="assigned_user_id"]');
                    const assigneeName = assignee.options[assignee.selectedIndex].text.trim();
                    const isReassignment = form.dataset.confirmMode === 'reassign';
                    const actionLabel = isReassignment ? 'Tukar' : 'Tugaskan';

                    title.textContent = actionLabel + ' staf ' + form.dataset.confirmAssignment + '?';
                    message.textContent = 'Tugasan ini akan diberikan kepada ' + assigneeName + '. Pastikan staff yang dipilih adalah betul.';
                    confirmButton.textContent = 'Ya, ' + actionLabel.toLowerCase() + ' staf';
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
            submitButton.textContent = 'Menyimpan...';

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
                    throw new Error(errors || payload.message || 'Penugasan tidak dapat disimpan.');
                }

                form.dataset.confirmMode = 'reassign';
                const label = form.querySelector('label');
                const status = form.querySelector('.staff-status');

                if (label) {
                        label.textContent = label.textContent.replace(/^Tugaskan\b/i, 'Tukar tugasan');
                }

                if (status) {
                    status.textContent = 'DITUGASKAN';
                }

                    submitButton.textContent = 'Tukar Tugasan';
                showAssignmentNotice(form, payload.message || 'Staf berjaya ditugaskan.', false);
            } catch (error) {
                submitButton.textContent = originalButtonText;
                showAssignmentNotice(form, error.message || 'Penugasan tidak dapat disimpan.', true);
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
            submitButton.textContent = 'Memuat naik...';

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
                    throw new Error(errors || payload.message || 'Muat naik progress tidak dapat diselesaikan.');
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
                    (payload.message || 'Progress production berjaya dimuat naik.')
                        + (fileNames.length ? ' ' + fileNames.join(', ') : ''),
                    false
                );
            } catch (error) {
                showUploadNotice(form, error.message || 'Muat naik progress tidak dapat diselesaikan.', true);
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        }

        async function submitArtworkUpload(form) {
            const submitButton = form.querySelector('[type="submit"]');
            const originalButtonText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Memuat naik...';

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
                    throw new Error(errors || payload.message || 'Hasil design tidak dapat dimuat naik.');
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
                        reviewForm.dataset.confirmMessage = 'Versi ' + artworkJob.version_number
                            + ' akan dihantar untuk semakan customer. Pastikan preview telah diperiksa dan merupakan versi yang betul.';
                    }

                    if (reviewPanel) {
                        reviewPanel.hidden = false;
                    }
                    if (uploadHint) {
                        uploadHint.hidden = true;
                    }
                });

                showUploadNotice(form, payload.message || 'Hasil design berjaya dimuat naik.', false);
            } catch (error) {
                showUploadNotice(form, error.message || 'Hasil design tidak dapat dimuat naik.', true);
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
            confirmButton.textContent = 'Memproses...';

            if (pendingForm.classList.contains('js-async-assignment')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Ya, teruskan';
                submitAssignment(form);
                return;
            }

            if (pendingForm.classList.contains('js-async-progress-upload')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Ya, teruskan';
                submitProgressUpload(form);
                return;
            }

            if (pendingForm.classList.contains('js-async-artwork-upload')) {
                const form = pendingForm;
                pendingForm = null;
                dialog.close();
                confirmButton.disabled = false;
                confirmButton.textContent = 'Ya, teruskan';
                submitArtworkUpload(form);
                return;
            }

            pendingForm.submit();
        });
    });
</script>
</body>
</html>
