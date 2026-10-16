<!doctype html>
<html lang="ms" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Akses Tempahan — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-order-progress">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async"><span><b>KingKadKahwin</b><small>Kad indah, kenangan bermakna</small></span></a>
        <nav aria-label="Navigasi akses tempahan">
            <a class="header-link nav-progress" href="{{ route('public.orders.progress') }}">← Kembali</a>
        </nav>
    </header>

    <main class="progress-page">
        <section class="progress-search">
            <p class="eyebrow">Akses selamat</p>
            <h1>Lihat progress</h1>
            <p>Masukkan nombor telefon yang digunakan semasa membuat tempahan untuk melihat progress dan proofing.</p>

            <form method="POST" action="{{ route('orders.access.store', array_filter(['order_id' => $prefilledOrderId ?? null])) }}">
                @csrf

                <div>
                    <label for="customer_reference">{{ ($prefilledOrderId ?? null) ? 'ID Tempahan' : 'ID Tempahan atau Rujukan Pelanggan' }}</label>
                    <input id="customer_reference" name="customer_reference" value="{{ old('customer_reference', $prefilledOrderId ?? '') }}" @readonly($prefilledOrderId ?? false) autocomplete="off" required>
                    @error('customer_reference')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="dropdown-field">
                    <label for="customer_phone">Nombor telefon</label>
                    <input id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" autocomplete="tel" inputmode="tel" required autofocus>
                    @error('customer_phone')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <button class="button button-primary submit-button" type="submit">Buka progress tempahan</button>
                <p class="privacy-note">Maklumat ini digunakan untuk mengesahkan bahawa tempahan tersebut milik anda.</p>
            </form>
        </section>
    </main>

    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
