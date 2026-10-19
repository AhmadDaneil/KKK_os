<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Payment Status — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-start-order">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async"><span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span></a>
    </header>
    <main class="form-page">
        <section class="form-intro">
            <p class="eyebrow">Payment status</p>
            <h1>{{ $payment->status === 'PAID' ? 'Payment successful' : 'Payment confirmation' }}</h1>
            <p>Your booking payment is verified securely with Billplz before your order dashboard is activated.</p>
        </section>
        <section class="public-form" aria-live="polite">
        @if ($payment->status === 'PAID')
            <p class="payment-success-message">Payment was successfully confirmed.</p>
            <p>Your booking payment has been confirmed securely.</p>
            <p>Customer reference: <strong>{{ $order->customer_reference }}</strong></p>
            <a class="button button-primary submit-button" href="{{ route('orders.dashboard', ['orderId' => $order->order_id]) }}">Continue to your order dashboard →</a>
        @else
            <p>Your payment has not been confirmed yet. Please refresh this page shortly. Do not make a second payment for the same order.</p>
            <a class="button button-secondary submit-button" href="{{ route('payments.booking.return', ['reference' => $payment->public_reference]) }}">Check payment again</a>
        @endif
        </section>
    </main>
</body>
</html>
