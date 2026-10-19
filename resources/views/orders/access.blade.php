<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="same-origin">
    <title>Access Your Order — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-start-order brand-order-access">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Order access navigation">
            <a class="header-link nav-progress" href="{{ route('public.orders.progress', array_filter(['order_id' => $prefilledOrderId])) }}">← Track Progress</a>
            <a class="header-link nav-progress" href="{{ route('home') }}">Back to Home</a>
        </nav>
    </header>

    <main class="form-page access-page">
        <section class="form-intro access-intro" aria-labelledby="access-page-title">
            <p class="eyebrow">Secure customer access</p>
            <h1 id="access-page-title">Access Your Order Dashboard</h1>
            <p>Verify your order once to view the latest progress, customer-visible proofs and order details shared by our team.</p>
            <div class="access-assurance" aria-label="Access information">
                <span aria-hidden="true">✓</span>
                <p><strong>Your order stays private.</strong><br>Only the phone number used when booking can open this dashboard.</p>
            </div>
        </section>

        <section class="public-form access-card" aria-labelledby="access-form-title">
            <div class="access-form-heading">
                <p class="eyebrow">Order verification</p>
                <h2 id="access-form-title">Continue to your order</h2>
                <p>Enter the same phone number you used when placing your order.</p>
            </div>

            @if ($errors->any())
                <div class="form-alert" role="alert">
                    <strong>We could not verify these details.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('orders.access.store') }}">
                @csrf
                <label for="customer_reference">Order ID or customer reference</label>
                <input
                    id="customer_reference"
                    name="customer_reference"
                    value="{{ old('customer_reference', $prefilledOrderId) }}"
                    placeholder="Example: KKK-261019-0002"
                    autocomplete="off"
                    spellcheck="false"
                    @if ($prefilledOrderId) readonly @endif
                    @error('customer_reference') aria-invalid="true" @enderror
                    required
                >
                @if ($prefilledOrderId)
                    <p class="access-field-note">Order ID selected from Track Progress.</p>
                @endif

                <label for="customer_phone">Phone number</label>
                <input
                    id="customer_phone"
                    name="customer_phone"
                    type="tel"
                    value="{{ old('customer_phone') }}"
                    placeholder="Example: 0123456789"
                    autocomplete="tel"
                    inputmode="tel"
                    @error('customer_phone') aria-invalid="true" @enderror
                    required
                >
                <p class="access-field-note">Malaysian formats such as 0123456789 or 60123456789 are accepted.</p>

                <button class="button button-primary submit-button" type="submit">Continue to Dashboard →</button>
                <p class="privacy-note">Your phone number is used only to verify access to this order.</p>
            </form>
        </section>
    </main>

    @php($orderId = $prefilledOrderId)
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
