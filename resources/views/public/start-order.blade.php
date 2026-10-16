<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Start Your Order — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-start-order">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async"><span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span></a>
        <nav aria-label="Order page navigation">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Back</a>
            <a class="header-link nav-progress" href="{{ route('public.orders.progress') }}">Track Order</a>
        </nav>
    </header>
    <main class="form-page">
        <section class="form-intro">
            <p class="eyebrow">First step</p>
            <h1>Start your order</h1>
            <p>Tell us which package you need. Next, you will be taken to the full order form and receive an Order ID.</p>
        </section>
        <form id="start-order-form" class="public-form" method="POST" action="{{ route('public.orders.store') }}">
            @csrf
            @if ($errors->any())<div class="form-alert" role="alert">Please review the highlighted information.</div>@endif

            <fieldset>
                <legend>Number of packages</legend>
                <div class="choice-grid">
                    <label class="choice-card"><input type="radio" name="package_count" value="1" @checked(old('package_count', '1') == '1')><span><b>One package</b><small>Choose the groom's or bride's side</small></span></label>
                    <label class="choice-card"><input type="radio" name="package_count" value="2" @checked(old('package_count') == '2')><span><b>Two packages</b><small>Packages for both sides</small></span></label>
                </div>
                @error('package_count')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <label for="package-type">Card collection</label>
                <select id="package-type" name="package_type" required>
                    <option value="">Choose a card collection</option>
                    <option value="PUTERI_MANIS" @selected(old('package_type') === 'PUTERI_MANIS')>
                        Puteri Package
                    </option>
                    <option value="JELATA" @selected(old('package_type') === 'JELATA')>
                        Jelata Package
                    </option>
                </select>
                @error('package_type')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <div id="side-field">
                <label for="side">Package for</label>
                <select id="side" name="side">
                    <option value="LELAKI" @selected(old('side') === 'LELAKI')>Groom's side</option>
                    <option value="PEREMPUAN" @selected(old('side') === 'PEREMPUAN')>Bride's side</option>
                </select>
                @error('side')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div id="two-package-options" hidden>
                <div class="dropdown-field">
                    <label for="package-format">Package format</label>
                    <select id="package-format" name="package_format">
                        <option value="SEPARATE" @selected(old('package_format', 'SEPARATE') === 'SEPARATE')>Separate packages</option>
                        <option value="FOLDED" @selected(old('package_format') === 'FOLDED')>Combined package – Folded card</option>
                    </select>
                    @error('package_format')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="dropdown-field">
                    <label for="first-event-side">Whose event is first?</label>
                    <select id="first-event-side" name="first_event_side">
                        <option value="LELAKI" @selected(old('first_event_side', 'LELAKI') === 'LELAKI')>Groom's side</option>
                        <option value="PEREMPUAN" @selected(old('first_event_side') === 'PEREMPUAN')>Bride's side</option>
                    </select>
                    @error('first_event_side')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <label for="customer_name">Your name</label>
            <input id="customer_name" name="customer_name" placeholder="Example: Muhammad Syafiq Bin Rahim" value="{{ old('customer_name') }}" autocomplete="name" required>
            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="two-columns">
                <div><label for="customer_email">Email</label><input id="customer_email" type="email" name="customer_email" placeholder="Example: syafiq@email.com" value="{{ old('customer_email') }}" autocomplete="email" required>@error('customer_email')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="customer_phone">Phone number</label><input id="customer_phone" type="tel" name="customer_phone" placeholder="Example: 0123456789" pattern="01[0-9]{8,9}" inputmode="numeric" minlength="10" maxlength="11" title="Enter 10 or 11 digits starting with 01." value="{{ old('customer_phone') }}" autocomplete="tel" required>@error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
            <button id="start-order-submit" class="button button-primary submit-button" type="submit">Continue to Booking Payment →</button>
            <p class="privacy-note">This information is used only to manage your order.</p>
        </form>
    </main>

    <dialog id="start-order-confirmation" class="confirmation-dialog" aria-labelledby="start-order-confirmation-title">
        <div class="confirmation-dialog-icon" aria-hidden="true">?</div>
        <h2 id="start-order-confirmation-title">Create this order?</h2>
            <p>Please check your name, email, phone number and package selection before continuing.</p>
        <div class="confirmation-dialog-actions">
            <button id="cancel-start-order" class="button button-secondary" type="button">No, review again</button>
            <button id="confirm-start-order" class="button button-primary" type="button">Yes, create order</button>
        </div>
    </dialog>

    <script>
        const packageInputs = document.querySelectorAll('input[name="package_count"]');
        const sideField = document.getElementById('side-field');
        const twoPackageOptions = document.getElementById('two-package-options');
        const startOrderForm = document.getElementById('start-order-form');
        const startOrderSubmit = document.getElementById('start-order-submit');
        const confirmationDialog = document.getElementById('start-order-confirmation');
        const confirmStartOrder = document.getElementById('confirm-start-order');
        const cancelStartOrder = document.getElementById('cancel-start-order');
        let orderConfirmed = false;

        function updateSideField() {
            const selected = document.querySelector('input[name="package_count"]:checked');
            sideField.hidden = selected && selected.value === '2';
            sideField.querySelector('select').disabled = sideField.hidden;
            twoPackageOptions.hidden = !selected || selected.value !== '2';
            twoPackageOptions.querySelectorAll('select').forEach((select) => select.disabled = twoPackageOptions.hidden);
        }
        packageInputs.forEach((input) => input.addEventListener('change', updateSideField));
        updateSideField();

        startOrderForm.addEventListener('submit', function (event) {
            if (orderConfirmed) {
                startOrderSubmit.disabled = true;
                startOrderSubmit.classList.add('is-loading');
                startOrderSubmit.textContent = 'Preparing payment...';

                return;
            }

            event.preventDefault();
            confirmationDialog.showModal();
        });

        cancelStartOrder.addEventListener('click', function () {
            confirmationDialog.close();
        });

        confirmStartOrder.addEventListener('click', function () {
            orderConfirmed = true;
            confirmationDialog.close();
            startOrderForm.requestSubmit(startOrderSubmit);
        });
    </script>
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
