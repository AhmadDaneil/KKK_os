<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Track Your Order — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-order-progress">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async"><span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span></a>
        <nav aria-label="Order tracking navigation">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Back</a>
            <a class="header-link nav-progress" href="{{ route('public.orders.create') }}">Chat to Book</a>
        </nav>
    </header>
    <main class="progress-page">
        <section class="progress-search">
            <p class="eyebrow">Your order status</p>
            <h1>Track your order</h1>
            <p>Enter the order ID you received when placing your order.</p>
            <form method="POST" action="{{ route('public.orders.progress.lookup') }}">
                @csrf
                <div class="progress-lookup-fields">
                    <div>
                        <label for="order_id">Order ID</label>
                        <input id="order_id" name="order_id" value="{{ old('order_id', $orderId ?? '') }}" placeholder="Example: KKK-260917-0001" autocomplete="off" required>
                    </div>
                    <button class="button button-primary" type="submit">Track Order</button>
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
                            <strong>Action required: pay the remaining balance</strong>
                            <span>The design has been approved. Please pay the remaining balance so your order can proceed to printing.</span>
                        </div>
                        <a href="#balance-payment">Make Payment</a>
                    </div>
                @endif
                <div class="progress-card-heading"><div><small>ORDER ID</small><h2>{{ $orderId }}</h2></div><strong>{{ $progress['percentage'] }}%</strong></div>
                <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}"><span style="width: {{ $progress['percentage'] }}%"></span></div>
                <section class="order-lifecycle" aria-labelledby="order-lifecycle-title">
                    <div class="order-lifecycle-heading">
                        <div>
                            <small>ORDER JOURNEY</small>
                            <h4 id="order-lifecycle-title">Your order progress</h4>
                        </div>
                        <span>The current stage is highlighted</span>
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
                <div class="public-artwork-action">
                    <div>
                        <strong>Order progress and proofing</strong>
                        <span>See the latest updates and proofs shared for your order.</span>
                    </div>
                    <a class="button button-primary" href="{{ ($canAccessOrder ?? false) ? route('orders.dashboard', ['orderId' => $orderId]) : route('orders.access.create', ['order_id' => $orderId]) }}">View progress</a>
                </div>
                @if ($deposit)
                    <div class="deposit-public-status" data-status="{{ strtolower($deposit['status']) }}">
                        <span>Deposit</span>
                        <strong>{{ match ($deposit['status']) { 'PAID' => 'Confirmed', 'FAILED' => 'Receipt Rejected', default => 'Awaiting Payment' } }}</strong>
                        @if ($deposit['status'] === 'FAILED' && $deposit['reason'])<p>Reason: {{ $deposit['reason'] }}</p>@endif
                    </div>
                @endif
                @if (($orderStatus ?? null) === 'DESIGN_APPROVED' || ! empty($balance))
                    <section id="balance-payment" class="balance-payment-card" data-status="{{ strtolower($balance['status'] ?? 'new') }}">
                        <div class="balance-payment-heading">
                            <div><small>REMAINING BALANCE</small><h4>Balance payment after design approval</h4></div>
                            @if ((float) ($balanceAmount ?? 0) > 0)<strong>RM {{ number_format((float) $balanceAmount, 2) }}</strong>@endif
                        </div>

                        @if (($balance['status'] ?? null) === 'PENDING')
                            @if (($balance['provider'] ?? null) === 'BILLPLZ')
                                <div class="balance-status-message"><strong>Awaiting payment</strong><span>Complete your secure payment to continue your order.</span></div>
                                <form method="POST" action="{{ route('orders.balance-payment.billplz.store', ['orderId' => $orderId]) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Continue to Payment</button>
                                </form>
                                @error('billplz')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @else
                                <div class="balance-status-message"><strong>Receipt under review</strong><span>Our operations team will confirm payment before printing begins.</span></div>
                            @endif
                        @elseif (($balance['status'] ?? null) === 'PAID')
                            <div class="balance-status-message is-paid"><strong>Full payment confirmed</strong><span>Your order will proceed to printing.</span></div>
                        @else
                            @if (($balance['status'] ?? null) === 'FAILED')
                                <div class="balance-status-message is-failed"><strong>Payment failed</strong><span>{{ $balance['reason'] ?: 'Please try your payment again.' }}</span></div>
                            @endif
                            @if (config('services.billplz.enabled') && $balanceBillplzAmount !== null && (float) $balanceBillplzAmount > 0)
                                <form method="POST" action="{{ route('orders.balance-payment.billplz.store', ['orderId' => $orderId]) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Pay Balance RM {{ number_format((float) $balanceBillplzAmount, 2) }}</button>
                                </form>
                                @error('billplz')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @endif
                        @endif
                    </section>
                @endif
                @if (! empty($shipment['tracking_number']))
                    <div class="tracking-card">
                        <small>DELIVERY INFORMATION</small>
                        <div><span>Courier</span><strong>{{ $shipment['courier_provider'] ?: '-' }}</strong></div>
                        <div><span>Tracking Number</span><strong class="tracking-number">{{ $shipment['tracking_number'] }}</strong></div>
                        @if ($shipment['shipped_at'])<p>Shipped on {{ $shipment['shipped_at']->timezone(config('app.display_timezone'))->format('d/m/Y, h:i A') }}</p>@endif
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
