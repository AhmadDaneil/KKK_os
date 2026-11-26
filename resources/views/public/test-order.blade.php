<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Order — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-start-order">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Order page navigation">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Back to home</a>
        </nav>
    </header>

    <main class="form-page">
        <section class="form-intro">
            <p class="eyebrow">Testing only</p>
            <h1>Place a test order</h1>
            <p>Complete the form to create a staging order and continue to Billplz Sandbox.</p>
            <div class="landing-resume-order" role="note">
                <strong>Sandbox payment only</strong>
                <p>This test checkout cannot use a live Billplz payment link.</p>
            </div>
        </section>

        <form class="public-form" method="POST" action="{{ route('public.orders.test.store') }}">
            @csrf
            <p class="field-help"><span class="required-marker" aria-hidden="true">*</span> Fields marked * must be completed.</p>
            @if ($errors->any())
                <div class="form-alert" role="alert">Please review the information below.</div>
            @endif

            <fieldset>
                <legend>Number of packages <span class="required-marker" aria-hidden="true">*</span></legend>
                <div class="choice-grid">
                    <label class="choice-card">
                        <input type="radio" name="package_count" value="1" @checked(old('package_count', '1') == '1')>
                        <span><b>One package</b><small>Choose one side</small></span>
                    </label>
                    <label class="choice-card">
                        <input type="radio" name="package_count" value="2" @checked(old('package_count') == '2')>
                        <span><b>Two packages</b><small>For both sides</small></span>
                    </label>
                </div>
                @error('package_count')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <label for="package-type">Card package <span class="required-marker" aria-hidden="true">*</span></label>
                <select id="package-type" name="package_type" required>
                    <option value="">Choose a package</option>
                    <option value="PUTERI_MANIS" @selected(old('package_type') === 'PUTERI_MANIS')>Puteri Manis (Full package)</option>
                    <option value="JELATA" @selected(old('package_type') === 'JELATA')>Jelata (card and envelope only)</option>
                </select>
                @error('package_type')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset id="test-side-field">
                <label for="test-side">Package side <span class="required-marker" aria-hidden="true">*</span></label>
                <select id="test-side" name="side">
                    <option value="LELAKI" @selected(old('side', 'LELAKI') === 'LELAKI')>Groom's side</option>
                    <option value="PEREMPUAN" @selected(old('side') === 'PEREMPUAN')>Bride's side</option>
                </select>
                @error('side')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <input name="package_format" type="hidden" value="SEPARATE">

            <fieldset>
                <label for="test-payment-plan">Payment</label>
                <select id="test-payment-plan" name="payment_plan" required>
                    <option value="DEPOSIT" @selected(old('payment_plan', 'DEPOSIT') === 'DEPOSIT')>Deposit payment</option>
                    <option value="FULL" @selected(old('payment_plan') === 'FULL')>Full card package payment</option>
                </select>
                <p class="field-help" id="test-payment-plan-help"></p>
                @error('payment_plan')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <label for="test-customer-name">Customer name <span class="required-marker" aria-hidden="true">*</span></label>
            <input id="test-customer-name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required>
            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="two-columns">
                <div>
                    <label for="test-customer-email">Email <span class="required-marker" aria-hidden="true">*</span></label>
                    <input id="test-customer-email" type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required>
                    @error('customer_email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="test-customer-phone">Phone number <span class="required-marker" aria-hidden="true">*</span></label>
                    <input id="test-customer-phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="0123456789" pattern="01[0-9]{8,9}" inputmode="numeric" minlength="10" maxlength="11" autocomplete="tel" required>
                    @error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            @error('payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <button id="test-order-submit-button" class="button button-primary submit-button" type="submit">Create Test Order &amp; Open Sandbox →</button>
            <p class="privacy-note">Testing route is available only while Billplz Sandbox is enabled.</p>
        </form>
    </main>

    <script>
        const packageInputs = document.querySelectorAll('input[name="package_count"]');
        const sideField = document.getElementById('test-side-field');
        const paymentPlan = document.getElementById('test-payment-plan');
        const paymentHelp = document.getElementById('test-payment-plan-help');
        const submitButton = document.getElementById('test-order-submit-button');

        function updatePackageFields() {
            const selected = document.querySelector('input[name="package_count"]:checked');
            const twoPackagesSelected = selected?.value === '2';

            sideField.hidden = twoPackagesSelected;
            sideField.querySelector('select').disabled = twoPackagesSelected;
        }

        function updatePaymentPlan() {
            const isFullPayment = paymentPlan.value === 'FULL';

            paymentHelp.textContent = isFullPayment
                ? 'Complete the order details and choose card quantity first. Then pay the full card package price before final confirmation. Postage and add-ons are excluded from this test checkout.'
                : 'Pay RM50 for one package or RM100 for two packages now. Any remaining card balance is due after design approval. Postage and add-ons are excluded from this test checkout.';

            submitButton.textContent = isFullPayment
                ? 'Create Test Order & Complete Details →'
                : 'Create Test Order & Open Sandbox →';
        }

        packageInputs.forEach((input) => input.addEventListener('change', updatePackageFields));
        paymentPlan.addEventListener('change', updatePaymentPlan);
        updatePackageFields();
        updatePaymentPlan();
    </script>
    @include('public.partials.theme-toggle')
</body>
</html>
