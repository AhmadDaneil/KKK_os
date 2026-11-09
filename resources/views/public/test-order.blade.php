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
            @if ($errors->any())
                <div class="form-alert" role="alert">Please review the information below.</div>
            @endif

            <fieldset>
                <legend>Number of packages</legend>
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
                <label for="package-type">Card package</label>
                <select id="package-type" name="package_type" required>
                    <option value="">Choose a package</option>
                    <option value="PUTERI_MANIS" @selected(old('package_type') === 'PUTERI_MANIS')>Puteri Manis</option>
                    <option value="JELATA" @selected(old('package_type') === 'JELATA')>Jelata</option>
                </select>
                @error('package_type')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <div id="test-side-field">
                <label for="test-side">Package side</label>
                <select id="test-side" name="side">
                    <option value="LELAKI" @selected(old('side', 'LELAKI') === 'LELAKI')>Groom's side</option>
                    <option value="PEREMPUAN" @selected(old('side') === 'PEREMPUAN')>Bride's side</option>
                </select>
                @error('side')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div id="test-two-package-options" hidden>
                <div class="dropdown-field">
                    <label for="test-package-format">Package format</label>
                    <select id="test-package-format" name="package_format">
                        <option value="SEPARATE" @selected(old('package_format', 'SEPARATE') === 'SEPARATE')>Separate packages</option>
                        <option value="FOLDED" @selected(old('package_format') === 'FOLDED')>Combined package — folded card</option>
                    </select>
                    @error('package_format')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="dropdown-field">
                    <label for="test-first-event-side">Whose event is first?</label>
                    <select id="test-first-event-side" name="first_event_side">
                        <option value="LELAKI" @selected(old('first_event_side', 'LELAKI') === 'LELAKI')>Groom's side</option>
                        <option value="PEREMPUAN" @selected(old('first_event_side') === 'PEREMPUAN')>Bride's side</option>
                    </select>
                    @error('first_event_side')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div id="test-single-quantity-field">
                <label for="test-card-quantity">Card quantity</label>
                <select id="test-card-quantity" name="card_quantity">
                    <option value="">Choose card quantity</option>
                    @foreach ($quantityOptions as $packageType => $options)
                        @foreach ($options['single'] as $quantityOption)
                            <option
                                value="{{ $quantityOption['quantity'] }}"
                                data-package-type="{{ $packageType }}"
                                @selected((string) old('card_quantity') === (string) $quantityOption['quantity'] && old('package_type') === $packageType)
                            >
                                {{ $quantityOption['quantity'] }} cards — RM {{ number_format((float) $quantityOption['amount'], 2) }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('card_quantity')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div id="test-two-quantity-field" hidden>
                <label for="test-two-package-quantities">Card quantities for both sides</label>
                <select id="test-two-package-quantities" name="two_package_quantities" disabled>
                    <option value="">Choose quantities</option>
                    @foreach ($quantityOptions as $packageType => $options)
                        @foreach ($options['pairs'] as $quantityOption)
                            <option
                                value="{{ $quantityOption['value'] }}"
                                data-package-type="{{ $packageType }}"
                                @selected(old('two_package_quantities') === $quantityOption['value'] && old('package_type') === $packageType)
                            >
                                Groom {{ $quantityOption['groom'] }} + Bride {{ $quantityOption['bride'] }} cards ({{ $quantityOption['total'] }} total) — RM {{ number_format((float) $quantityOption['amount'], 2) }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
                @error('two_package_quantities')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <label for="test-payment-plan">Payment</label>
                <select id="test-payment-plan" name="payment_plan" required>
                    <option value="DEPOSIT" @selected(old('payment_plan', 'DEPOSIT') === 'DEPOSIT')>Deposit payment</option>
                    <option value="FULL" @selected(old('payment_plan') === 'FULL')>Full card package payment</option>
                </select>
                <p class="field-help">Deposit: RM50 for one package or RM100 for two packages; the remaining card balance is due after design approval. Full payment covers the selected cards, so no card balance is due later. Postage and add-ons are excluded from this test checkout.</p>
                @error('payment_plan')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <label for="test-customer-name">Customer name</label>
            <input id="test-customer-name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required>
            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="two-columns">
                <div>
                    <label for="test-customer-email">Email</label>
                    <input id="test-customer-email" type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required>
                    @error('customer_email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="test-customer-phone">Phone number</label>
                    <input id="test-customer-phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="0123456789" pattern="01[0-9]{8,9}" inputmode="numeric" minlength="10" maxlength="11" autocomplete="tel" required>
                    @error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            @error('payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <button class="button button-primary submit-button" type="submit">Create Test Order &amp; Open Sandbox →</button>
            <p class="privacy-note">Testing route is available only while Billplz Sandbox is enabled.</p>
        </form>
    </main>

    <script>
        const packageInputs = document.querySelectorAll('input[name="package_count"]');
        const packageType = document.getElementById('package-type');
        const sideField = document.getElementById('test-side-field');
        const twoPackageOptions = document.getElementById('test-two-package-options');
        const singleQuantityField = document.getElementById('test-single-quantity-field');
        const singleQuantitySelect = document.getElementById('test-card-quantity');
        const twoQuantityField = document.getElementById('test-two-quantity-field');
        const twoQuantitySelect = document.getElementById('test-two-package-quantities');

        function updatePackageFields() {
            const selected = document.querySelector('input[name="package_count"]:checked');
            const twoPackagesSelected = selected?.value === '2';

            sideField.hidden = twoPackagesSelected;
            sideField.querySelector('select').disabled = twoPackagesSelected;
            twoPackageOptions.hidden = !twoPackagesSelected;
            singleQuantityField.hidden = twoPackagesSelected;
            singleQuantitySelect.disabled = twoPackagesSelected;
            twoQuantityField.hidden = !twoPackagesSelected;
            twoQuantitySelect.disabled = !twoPackagesSelected;
            twoPackageOptions.querySelectorAll('select').forEach((select) => {
                select.disabled = !twoPackagesSelected;
            });

            updateQuantityOptions();
        }

        function updateQuantityOptions() {
            [singleQuantitySelect, twoQuantitySelect].forEach((select) => {
                const selectedOption = select.selectedOptions[0];
                const selectedPackageType = selectedOption?.dataset.packageType;

                Array.from(select.options).forEach((option) => {
                    const matchesPackage = !option.dataset.packageType
                        || option.dataset.packageType === packageType.value;

                    option.hidden = !matchesPackage;
                    option.disabled = !matchesPackage;
                });

                if (selectedPackageType && selectedPackageType !== packageType.value) {
                    select.value = '';
                }
            });
        }

        packageInputs.forEach((input) => input.addEventListener('change', updatePackageFields));
        packageType.addEventListener('change', updateQuantityOptions);
        updatePackageFields();
    </script>
</body>
</html>
