<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Thank You - {{ $review['order_id'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/thank-you.css') }}?v={{ filemtime(public_path('css/thank-you.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-customer-flow">
    @php
        $fulfilmentLabel = match ($review['fulfilment']['method'] ?? null) {
            'COURIER' => 'Post / Courier',
            'PICKUP' => 'Self Pickup at KKK',
            default => '-',
        };
    @endphp

    <main class="thank-you-page">
        <section class="success-card">
            <div class="success-icon" aria-hidden="true">✓</div>
            <p class="eyebrow">Confirmation Successful</p>
            <h1>Thank You</h1>
            <p class="success-message">
                Your order details have been confirmed and received by KingKadKahwin.
            </p>
        </section>

        <section class="order-card">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Order Details</p>
                    <h2>{{ $review['order_id'] }}</h2>
                </div>
                <span class="confirmed-badge">Confirmed</span>
            </div>

            <dl class="summary-grid">
                <div>
                    <dt>Customer Name</dt>
                    <dd>{{ $review['customer_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Number of Packages</dt>
                    <dd>{{ $review['package_count'] }}</dd>
                </div>
                @if ($review['package_count'] === 2)
                    <div><dt>Gift Amount</dt><dd>{{ $review['jumlah_hantaran'] ?: '-' }}</dd></div>
                @endif
                <div>
                    <dt>Groom's Name</dt>
                    <dd>{{ $review['couple']['groom_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Bride's Name</dt>
                    <dd>{{ $review['couple']['bride_name'] ?: '-' }}</dd>
                </div>
                <div>
                    <dt>Delivery Method</dt>
                    <dd>{{ $fulfilmentLabel }}</dd>
                </div>
                <div>
                    <dt>Confirmation Date</dt>
                    <dd>{{ $order->details_confirmed_at?->timezone(config('app.display_timezone'))->format('d/m/Y, h:i A') }}</dd>
                </div>
            </dl>

            <div class="package-list">
                @foreach ($review['package_sides'] as $side)
                    @php
                        $sideLabel = $side['side'] === 'LELAKI' ? "Groom's Side" : "Bride's Side";
                    @endphp
                    <article class="package-summary">
                        <h3>{{ $review['package_count'] === 2 ? 'Event '.$loop->iteration.' – ' : 'Package – ' }}{{ $sideLabel }}</h3>
                        <dl>
                            <div>
                                <dt>Design Code</dt>
                                <dd>{{ $side['design']['design_code'] ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt>Event Date</dt>
                                <dd>{{ $side['event']['event_date'] ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt>Event Venue</dt>
                                <dd>{{ $side['event']['venue_name'] ?: '-' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>

            <a class="progress-button" href="{{ route('public.orders.progress', ['order_id' => $review['order_id']]) }}">
                Track Order
            </a>
        </section>
    </main>
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
</body>
</html>
