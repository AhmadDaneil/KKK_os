<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="same-origin">
    <title>Order Proofing — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-order-progress brand-order-proofing">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Proofing navigation">
            <a class="header-link nav-progress" href="{{ route('public.orders.progress', ['order_id' => $order->order_id]) }}">← Track Progress</a>
            <a class="header-link nav-progress" href="{{ route('home') }}">Back to Home</a>
        </nav>
    </header>

    <main class="proofing-page">
        <section class="proofing-hero">
            <div>
                <p class="eyebrow">Customer proofing</p>
                <h1>Your Order Progress</h1>
                <p>View the latest customer-visible updates and proofs shared by the KingKadKahwin team.</p>
            </div>
            <div class="proofing-order-id">
                <span>ORDER ID</span>
                <strong>{{ $order->order_id }}</strong>
                <b>{{ $progress['percentage'] }}%</b>
            </div>
            <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}">
                <span style="width: {{ $progress['percentage'] }}%"></span>
            </div>
            <p class="proofing-current-status"><strong>{{ $progress['label'] }}</strong><span>{{ $progress['message'] }}</span></p>
        </section>

        <section class="proofing-updates" aria-labelledby="proofing-updates-title">
            <div class="proofing-heading">
                <div>
                    <p class="eyebrow">Proofs and updates</p>
                    <h2 id="proofing-updates-title">Shared with you</h2>
                </div>
                <p>Only files approved for customer viewing appear here.</p>
            </div>

            @if ($order->progressUpdates->isNotEmpty())
                <ol class="proofing-list">
                    @foreach ($order->progressUpdates as $update)
                        <li>
                            <div class="proofing-update-meta">
                                <span>{{ match ($update->uploader_role) { 'DESIGNER' => 'Designer', 'PRODUCTION' => 'Production', 'OPERATION_MANAGEMENT' => 'OM', default => 'KKK Team' } }}</span>
                                <time datetime="{{ $update->published_at->toIso8601String() }}">{{ $update->published_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</time>
                            </div>
                            <h3>{{ str($update->order_status)->replace('_', ' ')->title() }}</h3>
                            <p>{{ $update->message ?: 'A new progress proof has been shared for your order.' }}</p>
                            @if ($update->hasAttachment())
                                <a class="button button-primary" href="{{ route('orders.progress-updates.attachment', ['orderId' => $order->order_id, 'progressUpdate' => $update]) }}" target="_blank" rel="noopener">View Proof</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="proofing-empty">
                    <span aria-hidden="true">⌛</span>
                    <h3>No proof has been shared yet</h3>
                    <p>The latest customer-visible proof will appear here once it is uploaded by our team.</p>
                </div>
            @endif
        </section>
    </main>

    @php($orderId = $order->order_id)
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
</body>
</html>
