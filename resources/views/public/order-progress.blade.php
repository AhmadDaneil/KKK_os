<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Semak Progress — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="utility-page">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">K</span><span><b>KingKadKahwin</b><small>Kad indah, kenangan bermakna</small></span></a>
        <nav aria-label="Navigasi halaman progress">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Kembali</a>
            <a class="header-link nav-progress" href="{{ route('public.orders.create') }}">Tempah Sekarang</a>
        </nav>
    </header>
    <main class="progress-page">
        <section class="progress-search">
            <p class="eyebrow">Status tempahan anda</p>
            <h1>Semak progress</h1>
            <p>Paste atau masukkan Order ID yang diterima semasa membuat tempahan.</p>
            <form method="POST" action="{{ route('public.orders.progress.lookup') }}">
                @csrf
                <label for="order_id">Order ID</label>
                <div class="search-row"><input id="order_id" name="order_id" value="{{ old('order_id', $orderId ?? '') }}" placeholder="Contoh: KKK-260917-0001" autocomplete="off" required><button class="button button-primary" type="submit">Semak</button></div>
                @error('order_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </form>
        </section>

        @isset($progress)
            <section class="public-progress-card" data-progress-tone="{{ $progress['tone'] }}">
                @if (session('balance_success'))<div class="payment-success-message">{{ session('balance_success') }}</div>@endif
                <div class="progress-card-heading"><div><small>ORDER ID</small><h2>{{ $orderId }}</h2></div><strong>{{ $progress['percentage'] }}%</strong></div>
                <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}"><span style="width: {{ $progress['percentage'] }}%"></span></div>
                <h3>{{ $progress['label'] }}</h3>
                <p>{{ $progress['message'] }}</p>
                @if ($artworkReady ?? false)
                    <div class="public-artwork-action">
                        <div>
                            <strong>Artwork anda sudah tersedia</strong>
                            <span>Semak setiap preview sebelum meluluskan atau meminta pembetulan.</span>
                        </div>
                        <a class="button button-primary" href="{{ route('orders.artwork.review', ['orderId' => $orderId]) }}">Buka Semakan Penuh</a>
                    </div>
                    @if (($artworkPreviews ?? collect())->isNotEmpty())
                        <div class="public-artwork-previews">
                            @foreach ($artworkPreviews as $artwork)
                                <article>
                                    <div><strong>Kad Pihak {{ ucfirst(strtolower($artwork['side'])) }}</strong><span>Artwork versi {{ $artwork['version'] }}</span></div>
                                    <iframe
                                        src="{{ route('orders.artwork.preview', ['orderId' => $orderId, 'designJobId' => $artwork['design_job_id']]) }}"
                                        title="Preview artwork pihak {{ strtolower($artwork['side']) }}"
                                        loading="lazy"
                                    ></iframe>
                                </article>
                            @endforeach
                        </div>
                    @endif
                @endif
                @if ($deposit)
                    <div class="deposit-public-status" data-status="{{ strtolower($deposit['status']) }}">
                        <span>Deposit</span>
                        <strong>{{ match ($deposit['status']) { 'PAID' => 'Disahkan', 'FAILED' => 'Resit Ditolak', default => 'Menunggu Semakan' } }}</strong>
                        @if ($deposit['status'] === 'FAILED' && $deposit['reason'])<p>Sebab: {{ $deposit['reason'] }}</p>@endif
                    </div>
                @endif
                @if (($orderStatus ?? null) === 'DESIGN_APPROVED' || ! empty($balance))
                    <section class="balance-payment-card" data-status="{{ strtolower($balance['status'] ?? 'new') }}">
                        <div class="balance-payment-heading">
                            <div><small>PEMBAYARAN PENUH</small><h4>Bayaran baki selepas artwork diluluskan</h4></div>
                            @if ((float) ($balanceAmount ?? 0) > 0)<strong>RM {{ number_format((float) $balanceAmount, 2) }}</strong>@endif
                        </div>

                        @if (($balance['status'] ?? null) === 'PENDING')
                            <div class="balance-status-message"><strong>Resit sedang disemak</strong><span>Operation Management akan mengesahkan pembayaran sebelum cetakan dimulakan.</span></div>
                        @elseif (($balance['status'] ?? null) === 'PAID')
                            <div class="balance-status-message is-paid"><strong>Bayaran penuh disahkan</strong><span>Tempahan anda akan diteruskan ke proses cetakan.</span></div>
                        @else
                            @if (($balance['status'] ?? null) === 'FAILED')
                                <div class="balance-status-message is-failed"><strong>Resit ditolak</strong><span>{{ $balance['reason'] ?: 'Sila buat semakan dan hantar resit baharu.' }}</span></div>
                            @endif
                            <p>Imbas QR di bawah untuk membuat pembayaran penuh, kemudian lampirkan resit sebagai bukti pembayaran.</p>
                            <div class="balance-payment-layout">
                                <div class="balance-qr-panel">
                                    @if ($balanceQrImage && file_exists(public_path($balanceQrImage)))
                                        <img src="{{ asset($balanceQrImage) }}" alt="QR pembayaran penuh">
                                    @else
                                        <div class="balance-qr-placeholder"><strong>QR</strong><span>Letakkan imej QR pembayaran dalam konfigurasi sistem.</span></div>
                                    @endif
                                </div>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('orders.balance-receipt.store', ['orderId' => $orderId]) }}">
                                    @csrf
                                    <label for="balance-receipt">Bukti pembayaran penuh</label>
                                    <p>JPG, JPEG, PNG, WEBP atau PDF. Maksimum 10 MB.</p>
                                    <div class="balance-receipt-picker">
                                        <input id="balance-receipt" type="file" name="balance_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                                        <button id="cancel-balance-receipt" type="button" hidden aria-controls="balance-receipt">Batal</button>
                                    </div>
                                    @error('balance_receipt')<p class="field-error">{{ $message }}</p>@enderror
                                    <button class="button button-primary" type="submit">Hantar Bukti Pembayaran</button>
                                </form>
                            </div>
                        @endif
                    </section>
                @endif
                @if (! empty($shipment['tracking_number']))
                    <div class="tracking-card">
                        <small>MAKLUMAT PENGHANTARAN</small>
                        <div><span>Courier</span><strong>{{ $shipment['courier_provider'] ?: '-' }}</strong></div>
                        <div><span>Tracking Number</span><strong class="tracking-number">{{ $shipment['tracking_number'] }}</strong></div>
                        @if ($shipment['shipped_at'])<p>Dihantar pada {{ $shipment['shipped_at']->format('d/m/Y, h:i A') }}</p>@endif
                    </div>
                @endif
                <div class="public-stages">
                    @foreach ($progress['stages'] as $stage)
                        <div class="@if ($stage['complete']) complete @endif"><i>✓</i><span>{{ $stage['label'] }}</span></div>
                    @endforeach
                </div>
            </section>
        @endisset
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('balance-receipt');
            const cancelButton = document.getElementById('cancel-balance-receipt');

            if (!input || !cancelButton) {
                return;
            }

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
    </script>
</body>
</html>
