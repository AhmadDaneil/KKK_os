<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mulakan Tempahan — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="utility-page">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">K</span><span><b>KingKadKahwin</b><small>Kad indah, kenangan bermakna</small></span></a>
        <a class="header-link" href="{{ route('public.orders.progress') }}">Semak Progress</a>
    </header>
    <main class="form-page">
        <section class="form-intro">
            <p class="eyebrow">Langkah pertama</p>
            <h1>Mulakan tempahan anda</h1>
            <p>Beritahu kami jenis pakej yang diperlukan. Selepas ini anda akan terus dibawa ke borang lengkap dan menerima Order ID.</p>
        </section>
        <form id="start-order-form" class="public-form" method="POST" action="{{ route('public.orders.store') }}">
            @csrf
            @if ($errors->any())<div class="form-alert" role="alert">Sila semak semula maklumat yang ditandakan.</div>@endif

            <fieldset>
                <legend>Bilangan pakej</legend>
                <div class="choice-grid">
                    <label class="choice-card"><input type="radio" name="package_count" value="1" @checked(old('package_count', '1') == '1')><span><b>Satu pakej</b><small>Pilih pihak lelaki atau perempuan</small></span></label>
                    <label class="choice-card"><input type="radio" name="package_count" value="2" @checked(old('package_count') == '2')><span><b>Dua pakej</b><small>Pakej lelaki dan perempuan</small></span></label>
                </div>
                @error('package_count')<p class="field-error">{{ $message }}</p>@enderror
            </fieldset>

            <div id="side-field">
                <label for="side">Pakej untuk pihak</label>
                <select id="side" name="side">
                    <option value="LELAKI" @selected(old('side') === 'LELAKI')>Lelaki</option>
                    <option value="PEREMPUAN" @selected(old('side') === 'PEREMPUAN')>Perempuan</option>
                </select>
                @error('side')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div id="two-package-options" hidden>
                <div class="dropdown-field">
                    <label for="package-format">Jenis pakej</label>
                    <select id="package-format" name="package_format">
                        <option value="SEPARATE" @selected(old('package_format', 'SEPARATE') === 'SEPARATE')>Pakej Berasingan</option>
                        <option value="FOLDED" @selected(old('package_format') === 'FOLDED')>Pakej Gabungan – Kad Lipatan</option>
                    </select>
                    @error('package_format')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="dropdown-field">
                    <label for="first-event-side">Majlis pertama untuk?</label>
                    <select id="first-event-side" name="first_event_side">
                        <option value="LELAKI" @selected(old('first_event_side', 'LELAKI') === 'LELAKI')>Pihak Lelaki</option>
                        <option value="PEREMPUAN" @selected(old('first_event_side') === 'PEREMPUAN')>Pihak Perempuan</option>
                    </select>
                    @error('first_event_side')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <label for="customer_name">Nama anda</label>
            <input id="customer_name" name="customer_name" placeholder="e.g. Muhammad Syafiq Bin Rahim" value="{{ old('customer_name') }}" autocomplete="name" required>
            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="two-columns">
                <div><label for="customer_email">Email</label><input id="customer_email" type="email" name="customer_email" placeholder="e.g. syafiq@email.com" value="{{ old('customer_email') }}" autocomplete="email" required>@error('customer_email')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="customer_phone">Nombor telefon</label><input id="customer_phone" type="tel" name="customer_phone" placeholder="e.g. 012-3456789" value="{{ old('customer_phone') }}" autocomplete="tel" required>@error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
            <button id="start-order-submit" class="button button-primary submit-button" type="submit">Cipta Tempahan & Teruskan →</button>
            <p class="privacy-note">Maklumat ini digunakan untuk mengurus tempahan anda sahaja.</p>
        </form>
    </main>

    <dialog id="start-order-confirmation" class="confirmation-dialog" aria-labelledby="start-order-confirmation-title">
        <div class="confirmation-dialog-icon" aria-hidden="true">?</div>
        <h2 id="start-order-confirmation-title">Cipta tempahan ini?</h2>
        <p>Pastikan nama, email, nombor telefon dan pilihan pakej anda betul sebelum meneruskan.</p>
        <div class="confirmation-dialog-actions">
            <button id="cancel-start-order" class="button button-secondary" type="button">Tidak, semak semula</button>
            <button id="confirm-start-order" class="button button-primary" type="button">Ya, cipta tempahan</button>
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
                startOrderSubmit.textContent = 'Mencipta tempahan...';

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
</body>
</html>
