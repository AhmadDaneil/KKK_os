<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Terima Kasih - {{ $review['order_id'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/thank-you.css') }}">
</head>
<body>
    @php
        $fulfilmentLabel = match ($review['fulfilment']['method'] ?? null) {
            'COURIER' => 'Pos / Courier',
            'PICKUP' => 'Self Pickup di KKK',
            default => '-',
        };
    @endphp

    <main class="thank-you-page">
        <section class="success-card">
            <div class="success-icon" aria-hidden="true">✓</div>
            <p class="eyebrow">Pengesahan Berjaya</p>
            <h1>Terima Kasih</h1>
            <p class="success-message">
                Maklumat tempahan anda telah berjaya disahkan dan diterima oleh pihak King Kad Kahwin.
            </p>
        </section>

        <section class="order-card">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Maklumat Tempahan</p>
                    <h2>{{ $review['order_id'] }}</h2>
                </div>
                <span class="confirmed-badge">Telah Disahkan</span>
            </div>

            <dl class="summary-grid">
                <div>
                    <dt>Nama Customer</dt>
                    <dd>{{ $review['customer_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Jumlah Pakej</dt>
                    <dd>{{ $review['package_count'] }}</dd>
                </div>
                <div>
                    <dt>Nama Pengantin Lelaki</dt>
                    <dd>{{ $review['couple']['groom_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Nama Pengantin Perempuan</dt>
                    <dd>{{ $review['couple']['bride_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Kaedah Penghantaran</dt>
                    <dd>{{ $fulfilmentLabel }}</dd>
                </div>
                <div>
                    <dt>Tarikh Disahkan</dt>
                    <dd>{{ $order->details_confirmed_at?->format('d/m/Y, h:i A') }}</dd>
                </div>
            </dl>

            <div class="package-list">
                @foreach ($review['package_sides'] as $side)
                    @php
                        $sideLabel = $side['side'] === 'LELAKI' ? 'Lelaki' : 'Perempuan';
                    @endphp
                    <article class="package-summary">
                        <h3>{{ $review['package_count'] === 2 ? 'Majlis '.$loop->iteration.' – ' : 'Pakej ' }}Pihak {{ $sideLabel }}</h3>
                        <dl>
                            <div>
                                <dt>Kod Design</dt>
                                <dd>{{ $side['design']['design_code'] ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt>Tarikh Majlis</dt>
                                <dd>{{ $side['event']['event_date'] ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt>Tempat Majlis</dt>
                                <dd>{{ $side['event']['venue_name'] ?: '-' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <a class="progress-button" href="{{ route('orders.dashboard', ['orderId' => $review['order_id']]) }}">
                Semak Progress
            </a>
        </section>
    </main>
</body>
</html>
