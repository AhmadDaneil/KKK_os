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
        <form class="public-form" method="POST" action="{{ route('public.orders.store') }}">
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

            <label for="customer_name">Nama anda</label>
            <input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" required>
            @error('customer_name')<p class="field-error">{{ $message }}</p>@enderror

            <div class="two-columns">
                <div><label for="customer_email">Email</label><input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email') }}" autocomplete="email" required>@error('customer_email')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="customer_phone">Nombor telefon</label><input id="customer_phone" type="tel" name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" required>@error('customer_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
            <button class="button button-primary submit-button" type="submit">Cipta Tempahan & Teruskan →</button>
            <p class="privacy-note">Maklumat ini digunakan untuk mengurus tempahan anda sahaja.</p>
        </form>
    </main>
    <script>
        const packageInputs = document.querySelectorAll('input[name="package_count"]');
        const sideField = document.getElementById('side-field');
        function updateSideField() {
            const selected = document.querySelector('input[name="package_count"]:checked');
            sideField.hidden = selected && selected.value === '2';
            sideField.querySelector('select').disabled = sideField.hidden;
        }
        packageInputs.forEach((input) => input.addEventListener('change', updateSideField));
        updateSideField();
    </script>
</body>
</html>
