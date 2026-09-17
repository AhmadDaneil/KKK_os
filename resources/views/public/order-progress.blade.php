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
        <a class="header-link" href="{{ route('public.orders.create') }}">Tempah Sekarang</a>
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
                <div class="progress-card-heading"><div><small>ORDER ID</small><h2>{{ $orderId }}</h2></div><strong>{{ $progress['percentage'] }}%</strong></div>
                <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}"><span style="width: {{ $progress['percentage'] }}%"></span></div>
                <h3>{{ $progress['label'] }}</h3>
                <p>{{ $progress['message'] }}</p>
                @if ($deposit)
                    <div class="deposit-public-status" data-status="{{ strtolower($deposit['status']) }}">
                        <span>Deposit</span>
                        <strong>{{ match ($deposit['status']) { 'PAID' => 'Disahkan', 'FAILED' => 'Resit Ditolak', default => 'Menunggu Semakan' } }}</strong>
                        @if ($deposit['status'] === 'FAILED' && $deposit['reason'])<p>Sebab: {{ $deposit['reason'] }}</p>@endif
                    </div>
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
</body>
</html>
