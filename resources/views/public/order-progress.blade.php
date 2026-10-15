<!doctype html>
<html lang="ms" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Semak Progress — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-order-progress">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async"><span><b>KingKadKahwin</b><small>Kad indah, kenangan bermakna</small></span></a>
        <nav aria-label="Navigasi halaman progress">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Kembali</a>
            <a class="header-link nav-progress" href="{{ route('public.orders.create') }}">Tempah Sekarang</a>
        </nav>
    </header>
    <main class="progress-page">
        <section class="progress-search">
            <p class="eyebrow">Status tempahan anda</p>
            <h1>Semak progress</h1>
            <p>Masukkan ID Tempahan yang diterima semasa membuat tempahan.</p>
            <form method="POST" action="{{ route('public.orders.progress.lookup') }}">
                @csrf
                <div class="progress-lookup-fields">
                    <div>
                        <label for="order_id">ID Tempahan</label>
                        <input id="order_id" name="order_id" value="{{ old('order_id', $orderId ?? '') }}" placeholder="Contoh: KKK-260917-0001" autocomplete="off" required>
                    </div>
                    <button class="button button-primary" type="submit">Semak</button>
                </div>
                @error('order_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            </form>
        </section>

        @isset($progress)
            <section class="public-progress-card" data-progress-tone="{{ $progress['tone'] }}">
                @if (session('balance_success'))<div class="payment-success-message">{{ session('balance_success') }}</div>@endif
                @if (($orderStatus ?? null) === 'DESIGN_APPROVED' && ($balance['status'] ?? null) !== 'PAID')
                    <div class="balance-payment-notice" role="status">
                        <div>
                            <strong>Tindakan diperlukan: jelaskan baki bayaran</strong>
                            <span>Hasil design sudah diluluskan. Sila jelaskan baki bayaran supaya tempahan boleh diteruskan ke cetakan.</span>
                        </div>
                        <a href="#balance-payment">Buat pembayaran</a>
                    </div>
                @endif
                <div class="progress-card-heading"><div><small>ORDER ID</small><h2>{{ $orderId }}</h2></div><strong>{{ $progress['percentage'] }}%</strong></div>
                <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}"><span style="width: {{ $progress['percentage'] }}%"></span></div>
                <section class="order-lifecycle" aria-labelledby="order-lifecycle-title">
                    <div class="order-lifecycle-heading">
                        <div>
                            <small>PERJALANAN TEMPAHAN</small>
                            <h4 id="order-lifecycle-title">Progress tempahan anda</h4>
                        </div>
                        <span>Status semasa ditanda dengan warna hijau</span>
                    </div>
                    <ol class="order-lifecycle-list">
                        @foreach ($progress['stages'] as $stage)
                            <li class="is-{{ $stage['state'] }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                                <span class="order-lifecycle-marker" aria-hidden="true">{{ $stage['state'] === 'complete' ? '✓' : $stage['number'] }}</span>
                                <strong>{{ $stage['label'] }}</strong>
                                <small>{{ $stage['description'] }}</small>
                            </li>
                        @endforeach
                    </ol>
                </section>
                @if (($orderStatus ?? null) !== 'DESIGN_APPROVED')
                    <h3>{{ $progress['label'] }}</h3>
                    <p>{{ $progress['message'] }}</p>
                @endif
                @if ($deposit)
                    <div class="deposit-public-status" data-status="{{ strtolower($deposit['status']) }}">
                        <span>Deposit</span>
                        <strong>{{ match ($deposit['status']) { 'PAID' => 'Disahkan', 'FAILED' => 'Resit Ditolak', default => 'Menunggu Pembayaran' } }}</strong>
                        @if ($deposit['status'] === 'FAILED' && $deposit['reason'])<p>Sebab: {{ $deposit['reason'] }}</p>@endif
                    </div>
                @endif
                @if (($orderStatus ?? null) === 'DESIGN_APPROVED' || ! empty($balance))
                    <section id="balance-payment" class="balance-payment-card" data-status="{{ strtolower($balance['status'] ?? 'new') }}">
                        <div class="balance-payment-heading">
                            <div><small>PEMBAYARAN BAKI</small><h4>Bayaran baki selepas hasil design diluluskan</h4></div>
                            @if ((float) ($balanceAmount ?? 0) > 0)<strong>RM {{ number_format((float) $balanceAmount, 2) }}</strong>@endif
                        </div>

                        @if (($balance['status'] ?? null) === 'PENDING')
                            @if (($balance['provider'] ?? null) === 'BILLPLZ')
                                <div class="balance-status-message"><strong>Menunggu pembayaran</strong><span>Lengkapkan pembayaran selamat untuk meneruskan tempahan.</span></div>
                                <form method="POST" action="{{ route('orders.balance-payment.billplz.store', ['orderId' => $orderId]) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Teruskan Pembayaran</button>
                                </form>
                                @error('billplz')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @else
                                <div class="balance-status-message"><strong>Resit sedang disemak</strong><span>Pengurusan Operasi akan mengesahkan pembayaran sebelum cetakan dimulakan.</span></div>
                            @endif
                        @elseif (($balance['status'] ?? null) === 'PAID')
                            <div class="balance-status-message is-paid"><strong>Bayaran penuh disahkan</strong><span>Tempahan anda akan diteruskan ke proses cetakan.</span></div>
                        @else
                            @if (($balance['status'] ?? null) === 'FAILED')
                                <div class="balance-status-message is-failed"><strong>Pembayaran gagal</strong><span>{{ $balance['reason'] ?: 'Sila cuba pembayaran semula.' }}</span></div>
                            @endif
                            @if (config('services.billplz.enabled') && $balanceBillplzAmount !== null && (float) $balanceBillplzAmount > 0)
                                <form method="POST" action="{{ route('orders.balance-payment.billplz.store', ['orderId' => $orderId]) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Bayar baki RM {{ number_format((float) $balanceBillplzAmount, 2) }}</button>
                                </form>
                                @error('billplz')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @endif
                        @endif
                    </section>
                @endif
                @if (! empty($shipment['tracking_number']))
                    <div class="tracking-card">
                        <small>MAKLUMAT PENGHANTARAN</small>
                        <div><span>Kurier</span><strong>{{ $shipment['courier_provider'] ?: '-' }}</strong></div>
                        <div><span>Nombor Tracking</span><strong class="tracking-number">{{ $shipment['tracking_number'] }}</strong></div>
                        @if ($shipment['shipped_at'])<p>Dihantar pada {{ $shipment['shipped_at']->timezone(config('app.display_timezone'))->format('d/m/Y, h:i A') }}</p>@endif
                    </div>
                @endif
            </section>
        @endisset
    </main>
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
