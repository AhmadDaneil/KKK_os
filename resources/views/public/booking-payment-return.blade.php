<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Payment Status — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-start-order brand-payment-return">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
    </header>

    <main class="payment-return-page">
        <section class="payment-return-card" aria-live="polite">
            @if ($payment->status === 'PAID')
                <span class="payment-return-icon" aria-hidden="true">✓</span>
                <p class="eyebrow">Payment confirmed</p>
                <h1>Payment Successful</h1>
                <p class="payment-return-lead">Your booking payment has been verified securely. Your order dashboard is now ready.</p>
                <dl class="payment-return-summary">
                    <div><dt>Order ID</dt><dd>{{ $order->customer_reference }}</dd></div>
                    <div><dt>Amount paid</dt><dd>MYR {{ number_format((float) $payment->amount, 2) }}</dd></div>
                    <div><dt>Payment status</dt><dd>Paid</dd></div>
                    @if ($payment->paid_at)<div><dt>Payment date</dt><dd>{{ $payment->paid_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</dd></div>@endif
                </dl>
                <div class="payment-return-actions">
                    @if ($receiptUrl)
                        <a class="button button-secondary" href="{{ $receiptUrl }}" target="_blank" rel="noopener noreferrer">View Billplz E-Receipt</a>
                    @endif
                    <a class="button button-primary" href="{{ route('orders.dashboard', ['orderId' => $order->order_id]) }}">Continue to Your Dashboard →</a>
                </div>
                <a class="payment-return-merchant" href="{{ route('home') }}">Return to Merchant</a>
            @else
                <span class="payment-return-icon is-pending" aria-hidden="true">…</span>
                <p class="eyebrow">Payment confirmation</p>
                <h1>Payment is being confirmed</h1>
                <p class="payment-return-lead">Please wait a moment while Billplz confirms your booking payment. Do not make another payment for this order.</p>
                <div class="payment-return-actions">
                    <a class="button button-primary" href="{{ route('payments.booking.return', ['reference' => $payment->public_reference]) }}">Check Payment Again</a>
                    <a class="button button-secondary" href="{{ route('home') }}">Return to Merchant</a>
                </div>
            @endif
        </section>
    </main>

    @php($orderId = $order->order_id)
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
</body>
</html>
