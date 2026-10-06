<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>KKK OS - {{ $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/customer-dashboard.css') }}?v={{ filemtime(public_path('css/customer-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
</head>
<body>
<main>
<header class="order-summary">
    <div class="order-summary-heading">
        <div>
            <p class="eyebrow">KING KAD KAHWIN</p>
            <h1>{{ __('ui.customer_order') }}</h1>
        </div>

        <div class="order-summary-actions">
            <a href="{{ route('home') }}" class="back-home-button">
                <span aria-hidden="true">&larr;</span>
                {{ __('ui.back') }}
            </a>

            <span class="status-badge">
                {{ $progress['label'] }}
            </span>
        </div>
    </div>

    <div class="order-summary-grid">
        <div>
            <span class="summary-label">ID Tempahan</span>
            <strong>{{ $order->order_id }}</strong>
        </div>

        <div>
            <span class="summary-label">{{ __('ui.package') }}</span>
            <strong>
                {{ $order->package_count }}
                Pakej
            </strong>
        </div>

        @if ($order->customer_name)
            <div>
                <span class="summary-label">{{ __('ui.customer_name') }}</span>
                <strong>{{ $order->customer_name }}</strong>
            </div>
        @endif

        @if ($order->customer_phone)
            <div>
                <span class="summary-label">{{ __('ui.phone') }}</span>
                <strong>{{ $order->customer_phone }}</strong>
            </div>
        @endif

        @if ($order->customer_email)
            <div>
                <span class="summary-label">{{ __('ui.email') }}</span>
                <strong>{{ $order->customer_email }}</strong>
            </div>
        @endif
    </div>

    <section class="progress-panel" data-progress-tone="{{ $progress['tone'] }}">
        <div class="progress-heading">
            <div>
                <span class="summary-label">{{ __('ui.order_progress') }}</span>
                <strong>{{ $progress['label'] }}</strong>
            </div>
            <span class="progress-percentage">{{ $progress['percentage'] }}%</span>
        </div>

        <div
            class="progress-track"
            role="progressbar"
            aria-label="{{ __('ui.order_progress') }}"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $progress['percentage'] }}"
        >
            <span class="progress-fill" style="width: {{ $progress['percentage'] }}%"></span>
        </div>

        <div class="progress-stages" aria-label="{{ __('ui.order_stages') }}">
            @foreach ($progress['stages'] as $stage)
                <div @class([
                    'progress-stage',
                    'is-complete' => $stage['state'] === 'complete',
                    'is-current' => $stage['state'] === 'current',
                ])>
                    <span class="stage-dot" aria-hidden="true"></span>
                    <span>{{ $stage['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="order-status-message">
        {{ $progress['message'] }}
    </div>
    @if ($order->fulfilmentJob?->method === 'COURIER' && $order->fulfilmentJob?->tracking_number)
        <div class="customer-tracking-card"><span>{{ __('ui.shipping_information') }}</span><strong>{{ $order->fulfilmentJob->courier_provider ?: 'Kurier' }}</strong><code>{{ $order->fulfilmentJob->tracking_number }}</code></div>
    @endif
</header>

@php($depositPayment = $order->payments->firstWhere('payment_type', 'BOOKING_DEPOSIT'))
@if ($depositPayment)
    <section class="deposit-status-card" data-deposit-status="{{ strtolower($depositPayment->status) }}">
        <div><span>{{ __('ui.deposit_status') }}</span><strong>{{ match ($depositPayment->status) { 'PAID' => __('ui.deposit_confirmed'), 'FAILED' => __('ui.receipt_rejected'), default => __('ui.pending_review') } }}</strong></div>
        @if (session('deposit_status'))<p class="deposit-success">{{ session('deposit_status') }}</p>@endif
        @if ($depositPayment->status === 'PENDING')<p>Resit deposit anda telah diterima dan sedang disemak oleh Pengurusan Operasi.</p>@endif
        @if ($depositPayment->status === 'PAID')<p>Bayaran deposit telah disahkan. Tempahan boleh diteruskan ke proses design.</p>@endif
        @if ($depositPayment->status === 'FAILED')
            <p><strong>Sebab penolakan:</strong> {{ $depositPayment->metadata['rejection_reason'] ?? 'Resit tidak dapat disahkan.' }}</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('orders.deposit-receipt.update', ['orderId' => $order->order_id]) }}" data-deposit-resubmission-form>
                @csrf
                <label for="replacement-deposit-receipt">{{ __('ui.upload_new_receipt') }}</label>
                <input id="replacement-deposit-receipt" type="file" name="deposit_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                <button type="submit" data-deposit-resubmission-button>{{ __('ui.resubmit_receipt') }}</button>
            </form>
        @endif
    </section>
@endif

    @if (in_array($order->status, [
    'DETAILS_CONFIRMED',
    'READY_FOR_DESIGN',
    'DESIGN_IN_PROGRESS',
    'DESIGN_READY',
    'CORRECTION_REQUESTED',
    'DESIGN_APPROVED',
], true))
    <section class="dashboard-action-card">
        <h2>Hasil Design Tempahan</h2>

        @if ($order->status === 'DETAILS_CONFIRMED')
            <p>
                Maklumat tempahan anda telah disahkan.
                Hasil design anda akan disediakan oleh pereka.
            </p>

            <span class="artwork-progress-label">
                Menunggu proses design
            </span>

        @elseif ($order->status === 'READY_FOR_DESIGN')
            <p>
                Tempahan anda sedang menunggu proses design.
            </p>

            <span class="artwork-progress-label">
                Menunggu designer
            </span>

        @elseif ($order->status === 'DESIGN_IN_PROGRESS')
            <p>
                Pereka sedang menyediakan artwork tempahan anda.
            </p>

            <span class="artwork-progress-label">
                Reka bentuk sedang disediakan
            </span>

        @elseif ($order->status === 'DESIGN_READY')
            <p>
                Hasil design anda telah tersedia. Sila semak hasil design sebelum membuat
                kelulusan atau meminta pembetulan.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Semak Hasil Design
            </a>

        @elseif ($order->status === 'CORRECTION_REQUESTED')
            <p>
                Permintaan pembetulan anda sedang diproses. Anda masih boleh
                melihat status semakan artwork.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Lihat Status Hasil Design
            </a>

        @else
            <p>
                Hasil design tempahan anda telah diluluskan.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Lihat Hasil Design
            </a>
        @endif
    </section>
@endif

@if (session('draft_saved'))
        <div class="notice customer-feedback customer-feedback-success" role="status">
            <strong>Maklumat anda telah disimpan.</strong>
            <span>Anda boleh keluar sekarang dan sambung semula melalui pautan dashboard yang sama.</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="errors customer-feedback customer-feedback-error" role="alert">
            <strong>Kami perlukan beberapa maklumat lagi.</strong>
            <span>Sila semak ruangan di bawah, kemudian cuba semula.</span>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php($couple = $order->couples->firstWhere('couple_number', 1))
    @php($secondCouple = $order->couples->firstWhere('couple_number', 2))
    @php($isEditable = $order->status === 'DETAILS_INCOMPLETE')
    @php($firstEventSide = old('first_event_side', $order->first_event_side ?: 'LELAKI'))
    @php($orderedPackageSides = $order->packageSides->sortBy(fn ($item) => $item->side === $firstEventSide ? 0 : 1)->values())

    @if (! $isEditable)
        <div class="notice readonly-notice">
            <strong>Maklumat tempahan telah dikunci.</strong>
            <div>
                Maklumat yang telah disahkan hanya boleh dilihat dan tidak boleh diubah.
            </div>
        </div>
    @endif

    <form
        id="customer-order-form"
        method="POST"
        action="{{ route('orders.draft.update', ['orderId' => $order->order_id]) }}"
        enctype="multipart/form-data"
    >
        @csrf

        <div class="order-form-layout">
            @include('orders.partials.card-preview')

            <div class="order-form-fields">

        <fieldset class="customer-data-lock" @disabled(! $isEditable)>

        @if ((int) $order->package_count === 2)
            <fieldset class="two-package-settings">
                <legend>Tetapan Dua Pakej</legend>
                <p class="field-help">Kedua-dua majlis mempunyai maklumat dan pilihan design masing-masing.</p>
                <div class="grid">
                    <div>
                        <label for="package-format">Jenis Pakej</label>
                        <select id="package-format" name="package_format">
                            <option value="SEPARATE" @selected(old('package_format', $order->package_format) === 'SEPARATE')>Pakej Berasingan</option>
                            <option value="FOLDED" @selected(old('package_format', $order->package_format) === 'FOLDED')>Pakej Gabungan – Kad Lipatan</option>
                        </select>
                    </div>
                    <div>
                        <label for="first-event-side">Majlis Pertama</label>
                        <select id="first-event-side" name="first_event_side">
                            <option value="LELAKI" @selected($firstEventSide === 'LELAKI')>Pihak Lelaki</option>
                            <option value="PEREMPUAN" @selected($firstEventSide === 'PEREMPUAN')>Pihak Perempuan</option>
                        </select>
                    </div>
                </div>
            </fieldset>
        @endif

<fieldset>
    <legend>Kuantiti Kad</legend>

    <p class="field-help">
        Masukkan jumlah kad untuk tempahan ini.
        Bagi tempahan dua pakej, kuantiti yang sama digunakan untuk kedua-dua pakej.
    </p>

    <div class="grid">
        <div>
            <label for="card_quantity">Jumlah Kad</label>
            <input
                id="card_quantity"
                type="number"
                name="card_quantity"
                min="1"
                step="1"
                inputmode="numeric"
                value="{{ old('card_quantity', $order->card_quantity) }}"
            >

            @error('card_quantity')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</fieldset>

        <fieldset>
            <legend>Maklumat Pasangan</legend>
            <div class="grid">
                <div><label>Nama Pengantin Lelaki</label><input name="couple[groom_name]" placeholder="Contoh: Muhammad Syafiq Bin Rahim" value="{{ old('couple.groom_name', $couple?->groom_name) }}"></div>
                <div><label>Singkatan Pengantin Lelaki</label><input name="couple[groom_abbreviation]" placeholder="Contoh: Syafiq" value="{{ old('couple.groom_abbreviation', $couple?->groom_abbreviation) }}"></div>
                <div><label>Nama Pengantin Perempuan</label><input name="couple[bride_name]" placeholder="Contoh: Nur Awanis Binti Azman" value="{{ old('couple.bride_name', $couple?->bride_name) }}"></div>
                <div><label>Singkatan Pengantin Perempuan</label><input name="couple[bride_abbreviation]" placeholder="Contoh: Awanis" value="{{ old('couple.bride_abbreviation', $couple?->bride_abbreviation) }}"></div>
            </div>
        </fieldset>
        @foreach ($orderedPackageSides as $packageSide)
            @php($side = $packageSide->side)
            @php($event = $packageSide->event)
            @php($hideFoldedDesign = $order->package_count === 2 && $order->package_format === 'FOLDED' && ! $loop->first)
            <fieldset data-package-side="{{ $side }}">
                <legend data-package-legend>{{ $order->package_count === 2 ? 'Majlis '.$loop->iteration.' – ' : 'Pakej ' }}Pihak {{ ucfirst(strtolower($side)) }}</legend>
                <div data-folded-second-design="{{ ! $loop->first ? 'true' : 'false' }}" @if ($hideFoldedDesign) hidden @endif>
                <h3>Design</h3>

<div class="grid">
    <div>
        <label>Tema</label>
        @php($selectedTheme = old("sides.$side.design.theme", $packageSide->design?->theme))

<select name="sides[{{ $side }}][design][theme]">
    <option value="">-- Pilih Tema --</option>

    @foreach ([
        'PORTRAIT',
        'ARCH',
        'CARTOON',
        'ISLAMIC',
        'MINIMALIST',
        'RUSTY',
        'SONGKET',
        'GARDEN',
        'NOSTALGIA',
        'DESA',
    ] as $theme)
        <option value="{{ $theme }}" @selected($selectedTheme === $theme)>
            {{ $theme }}
        </option>
    @endforeach
</select>
<p class="field-help">
    Gambar pengantin hanya digunakan untuk tema <strong>PORTRAIT</strong>. Tema lain, termasuk NOSTALGIA, menggunakan design tanpa gambar pengantin pada banner dan banting.
</p>
    </div>

    <div>
        <label>Kod Design</label>
        <input
            name="sides[{{ $side }}][design][design_code]"
            placeholder="Contoh: KKK-001"
            value="{{ old("sides.$side.design.design_code", $packageSide->design?->design_code) }}"
        >
    </div>

    @php($selectedCardTitle = old("sides.$side.design.card_title", $packageSide->design?->card_title))

<div>
    <label>Tajuk Majlis</label>

    <select name="sides[{{ $side }}][design][card_title]">
        <option value="">-- Pilih Tajuk Majlis --</option>
        <option value="Walimatul Urus" @selected($selectedCardTitle === 'Walimatul Urus')>
            Walimatul Urus
        </option>
        <option value="Majlis Perkahwinan" @selected($selectedCardTitle === 'Majlis Perkahwinan')>
            Majlis Perkahwinan
        </option>
        <option value="Kenduri Kesyukuran" @selected($selectedCardTitle === 'Kenduri Kesyukuran')>
            Kenduri Kesyukuran
        </option>
    </select>
</div>
</div>

<div class="image-upload-field" data-card-image-upload @if (strtoupper((string) $selectedTheme) !== 'PORTRAIT') hidden @endif>
    <label for="card-image-{{ strtolower($side) }}">
        Gambar Pengantin
    </label>

    <p class="field-help">
        Untuk tema PORTRAIT sahaja. Gunakan gambar menegak/portrait yang jelas, sebaiknya nisbah 3:4.
        Gambar mendatar atau petak akan dipotong dan diseragamkan kepada 900 × 1200 px (3:4).
        Format JPG, JPEG, PNG atau WEBP. Maksimum 10 MB.
    </p>

    @if ($packageSide->design?->card_image_path)
        <p class="upload-status">
            Gambar telah dimuat naik.
            Pilih fail baharu di bawah hanya jika anda mahu menggantikannya.
        </p>
    @endif

    <div class="card-image-picker">
    <input
        id="card-image-{{ strtolower($side) }}"
        type="file"
        name="sides[{{ $side }}][design][card_image]"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
    >
    <button type="button" class="cancel-card-image" hidden aria-controls="card-image-{{ strtolower($side) }}">Batal</button>
    </div>

    @error("sides.$side.design.card_image")
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
                </div>
                <section class="additional-products" aria-labelledby="included-design-items-{{ strtolower($side) }}">
                    <h3 id="included-design-items-{{ strtolower($side) }}">Termasuk Dalam Pakej Kad</h3>
                    <p class="field-help">Setiap pakej kad kahwin termasuk preview Banner dan Banting. Designer akan menggunakan nama singkatan serta tema Design kad yang dipilih. Gambar pengantin hanya digunakan untuk tema PORTRAIT.</p>
                </section>
                <h3>Ibu Bapa Pengantin {{ ucfirst(strtolower($side)) }}</h3>
                <p class="field-help">Masukkan nama ibu bapa bagi pihak yang menjadi tuan rumah majlis ini.</p>
                <div class="grid">
                    <div><label>Nama Bapa</label><input name="sides[{{ $side }}][parents][father_name]" placeholder="Contoh: Encik Rahim Bin Abdullah" value="{{ old("sides.$side.parents.father_name", $packageSide->parents?->father_name) }}"></div>
                    <div><label>Nama Ibu</label><input name="sides[{{ $side }}][parents][mother_name]" placeholder="Contoh: Puan Aminah Binti Ismail" value="{{ old("sides.$side.parents.mother_name", $packageSide->parents?->mother_name) }}"></div>
                </div>
                <h3>Majlis</h3>
                <div class="grid">
                    @php($selectedDay = old("sides.$side.event.day_name", $event?->day_name))

<div>
    <label>Hari</label>

    <select name="sides[{{ $side }}][event][day_name]" data-event-day>
        <option value="">-- Pilih Hari --</option>

        @foreach ([
            'Ahad',
            'Isnin',
            'Selasa',
            'Rabu',
            'Khamis',
            'Jumaat',
            'Sabtu',
        ] as $day)
            <option value="{{ $day }}" @selected($selectedDay === $day)>
                {{ $day }}
            </option>
        @endforeach
    </select>
</div>
                    <div>
                        <label>Tarikh</label>
                        <input type="date" name="sides[{{ $side }}][event][event_date]" value="{{ old("sides.$side.event.event_date", $event?->event_date?->format('Y-m-d')) }}" data-event-date>
                        <p class="date-picker-help" data-event-date-help>Pilih hari dahulu untuk memaparkan tarikh yang sepadan sahaja.</p>
                    </div>
                    <div>
                        <label>Tarikh Hijri</label>
                        <input name="sides[{{ $side }}][event][hijri_date]" placeholder="Contoh: 4 Zulhijjah 1446H" value="{{ old("sides.$side.event.hijri_date", $event?->hijri_date) }}" data-hijri-date>
                        <p class="date-picker-help" data-hijri-date-help>Pilih hari dahulu untuk menapis tarikh Hijri yang sepadan.</p>
                    </div>
                    <div><label>Masa Makan</label><input type="time" name="sides[{{ $side }}][event][meal_time]" value="{{ old("sides.$side.event.meal_time", $event?->meal_time ? substr($event->meal_time, 0, 5) : '') }}"></div>
                    <div><label>Masa Bersanding</label><input type="time" name="sides[{{ $side }}][event][bersanding_time]" value="{{ old("sides.$side.event.bersanding_time", $event?->bersanding_time ? substr($event->bersanding_time, 0, 5) : '') }}"></div>
                    <div><label>Nama Tempat</label><input name="sides[{{ $side }}][event][venue_name]" placeholder="Contoh: Dewan Seri Impian" value="{{ old("sides.$side.event.venue_name", $event?->venue_name) }}"></div>
                </div>
                <label>Alamat Penuh</label>
                <textarea rows="4" name="sides[{{ $side }}][event][full_address]" placeholder="Contoh: No. 87, Laluan Taman Meru 8, Taman Meru 2B, 30020 Ipoh, Perak">{{ old("sides.$side.event.full_address", $event?->full_address) }}</textarea>
                <label>Pautan Google Maps</label>
                <input type="url" name="sides[{{ $side }}][event][google_maps_url]" placeholder="Contoh: https://maps.app.goo.gl/..." value="{{ old("sides.$side.event.google_maps_url", $event?->google_maps_url) }}">
                <h3>Wakil Untuk Dihubungi</h3>

@for ($contactNumber = 1; $contactNumber <= 3; $contactNumber++)
    @php($contact = $event?->contacts?->firstWhere('contact_number', $contactNumber))

    <div class="grid">
        <div>
            <label>Wakil {{ $contactNumber }} - Nama</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_name]"
                placeholder="Contoh: Ahmad"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_name", $contact?->contact_name) }}"
            >
        </div>

        <div>
            <label>Wakil {{ $contactNumber }} - Telefon</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_phone]"
                type="tel"
                pattern="01[0-9]{8,9}"
                inputmode="numeric"
                minlength="10"
                maxlength="11"
                title="Masukkan 10 atau 11 digit bermula dengan 01."
                placeholder="Contoh: 0123456789"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_phone", $contact?->contact_phone) }}"
            >
        </div>
    </div>
@endfor
            </fieldset>
        @endforeach

        <fieldset>
    <legend>Penghantaran / Pengambilan</legend>

    @php($fulfilmentMethod = old('fulfilment.method', $order->fulfilment?->method))

    <p class="field-help">
        Pilih bagaimana tempahan anda akan diterima selepas siap.
    </p>

    <div class="fulfilment-options">
        <label class="fulfilment-option">
            <input
                type="radio"
                name="fulfilment[method]"
                value="COURIER"
                @checked($fulfilmentMethod === 'COURIER')
            >
            <span>
                <strong>Pos / Kurier</strong><br>
                Tempahan akan dihantar ke alamat yang diberikan.
            </span>
        </label>

        <label class="fulfilment-option">
            <input
                type="radio"
                name="fulfilment[method]"
                value="PICKUP"
                @checked($fulfilmentMethod === 'PICKUP')
            >
            <span>
                <strong>Pengambilan Sendiri</strong><br>
                Ambil sendiri tempahan di KKK.
            </span>
        </label>
    </div>

    <div
    id="courier-details"
    class="courier-details"
    @if ($fulfilmentMethod !== 'COURIER') hidden @endif
>
    <h3>Maklumat Penghantaran Kurier</h3>

    <div class="grid">
        <div>
            <label>Nama Penerima</label>
            <input
                id="courier-recipient-name"
                name="fulfilment[recipient_name]"
                placeholder="Contoh: Muhammad Syafiq Bin Rahim"
                value="{{ old('fulfilment.recipient_name', $order->fulfilment?->recipient_name) }}"
            >
        </div>

        <div>
            <label>Telefon Penerima</label>
            <input
                id="courier-recipient-phone"
                name="fulfilment[recipient_phone]"
                type="tel"
                pattern="01[0-9]{8,9}"
                inputmode="numeric"
                minlength="10"
                maxlength="11"
                title="Masukkan 10 atau 11 digit bermula dengan 01."
                placeholder="Contoh: 0123456789"
                value="{{ old('fulfilment.recipient_phone', $order->fulfilment?->recipient_phone) }}"
            >
        </div>
    </div>

    <label>Alamat Penghantaran</label>
    <textarea
        id="courier-shipping-address"
        rows="4"
        name="fulfilment[shipping_address]"
        placeholder="Contoh: No. 12, Jalan Melur 3, Taman Melur, 43000 Kajang, Selangor"
    >{{ old('fulfilment.shipping_address', $order->fulfilment?->shipping_address) }}</textarea>
</div>
</fieldset>

    </fieldset>
            </div>
        </div>

@if ($isEditable)
    <div class="form-actions">
        <button type="submit">Simpan Draf</button>

        <span id="autosave-status" class="autosave-status" data-state="saved" role="status" aria-live="polite">
            <span class="autosave-indicator" aria-hidden="true"></span>
            <span id="autosave-message">Semua perubahan selamat disimpan</span>
        </span>

        <a
            id="review-order-link"
            href="{{ route('orders.review.show', ['orderId' => $order->order_id]) }}"
        >
            Semak Maklumat & Teruskan
        </a>
    </div>
@endif

</form>
</main>
<script src="{{ asset('js/customer-event-date-pickers.js') }}?v={{ filemtime(public_path('js/customer-event-date-pickers.js')) }}" data-calendar-url="{{ route('public.calendar.convert') }}" defer></script>
@include('partials.malay-validation')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const reviewLink = document.getElementById('review-order-link');

    if (!reviewLink) {
        return;
    }

    function clearRequiredErrors() {
        document
            .querySelectorAll('.required-field-error')
            .forEach(function (element) {
                element.remove();
            });

        document
            .querySelectorAll('.input-error')
            .forEach(function (element) {
                element.classList.remove('input-error');
            });
    }

    function addRequiredError(field, message) {
        if (!field) {
            return;
        }

        field.classList.add('input-error');

        const error = document.createElement('p');
        error.className = 'field-error required-field-error';
        error.textContent = 'Sila isi ' + message + '.';

        field.insertAdjacentElement('afterend', error);
    }

    function validateRequiredField(selector, label) {
        const field = document.querySelector(selector);

        if (!field) {
            return true;
        }

        if (String(field.value ?? '').trim() !== '') {
            return true;
        }

        addRequiredError(field, label);

        return false;
    }

    reviewLink.addEventListener('click', function (event) {
        clearRequiredErrors();

        let valid = true;

        const requiredFields = [
            {
                selector: '[name="couple[groom_name]"]',
                label: 'Nama Pengantin Lelaki'
            },
            {
                selector: '[name="couple[bride_name]"]',
                label: 'Nama Pengantin Perempuan'
            },
            {
                selector: '[name="fulfilment[method]"]:checked',
                label: 'Kaedah Pemenuhan Tempahan',
                type: 'radio'
            }
        ];

        requiredFields.forEach(function (item) {
            if (item.type === 'radio') {
                const checked = document.querySelector(item.selector);

                if (!checked) {
                    const firstRadio = document.querySelector(
                        '[name="fulfilment[method]"]'
                    );

                    if (firstRadio) {
                        const container = firstRadio.closest('.fulfilment-options');

                        const error = document.createElement('p');
                        error.className = 'field-error required-field-error';
                        error.textContent = 'Sila pilih Kaedah Pemenuhan Tempahan.';

                        container.insertAdjacentElement('afterend', error);
                    }

                    valid = false;
                }

                return;
            }

            if (!validateRequiredField(item.selector, item.label)) {
                valid = false;
            }
        });

        document
            .querySelectorAll('fieldset[data-package-side]')
            .forEach(function (fieldset) {
                const side = fieldset.dataset.packageSide;
                const sideLabel = side === 'LELAKI'
                    ? 'Pakej Lelaki'
                    : 'Pakej Perempuan';

                const sideRequiredFields = [
                    ['design][design_code]', 'Kod Design'],
                    ['parents][father_name]', 'Nama Bapa'],
                    ['parents][mother_name]', 'Nama Ibu'],
                    ['event][event_date]', 'Tarikh Majlis'],
                    ['event][meal_time]', 'Masa Majlis / Jamuan'],
                    ['event][venue_name]', 'Nama Tempat Majlis'],
                    ['event][full_address]', 'Alamat Penuh'],

                    ['event][contacts][1][contact_name]', 'Wakil 1 - Nama'],
                    ['event][contacts][1][contact_phone]', 'Wakil 1 - Telefon'],

                    ['event][contacts][2][contact_name]', 'Wakil 2 - Nama'],
                    ['event][contacts][2][contact_phone]', 'Wakil 2 - Telefon'],

                    ['event][contacts][3][contact_name]', 'Wakil 3 - Nama'],
                    ['event][contacts][3][contact_phone]', 'Wakil 3 - Telefon']
                ];

                sideRequiredFields.forEach(function (item) {
                    const field = fieldset.querySelector(
                        '[name="sides[' + side + '][' + item[0] + '"]'
                    );

                    if (!field) {
                        return;
                    }

                    if (String(field.value ?? '').trim() === '') {
                        addRequiredError(
                            field,
                            sideLabel + ': ' + item[1]
                        );

                        valid = false;
                    }
                });
            });

        /*
         * Courier fields hanya wajib apabila customer pilih Courier.
         */
        const fulfilmentMethod = document.querySelector(
            '[name="fulfilment[method]"]:checked'
        );

        if (fulfilmentMethod && fulfilmentMethod.value === 'COURIER') {
            [
                ['[name="fulfilment[recipient_name]"]', 'Nama Penerima'],
                ['[name="fulfilment[recipient_phone]"]', 'No Telefon Penerima'],
                ['[name="fulfilment[shipping_address]"]', 'Alamat Penghantaran']
            ].forEach(function (item) {
                if (!validateRequiredField(item[0], item[1])) {
                    valid = false;
                }
            });
        }

        if (!valid) {
            event.preventDefault();

            const firstError = document.querySelector('.input-error');

            if (firstError) {
                firstError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                firstError.focus();
            }
        }
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-order-form');
        const reviewLink = document.getElementById('review-order-link');
        const status = document.getElementById('autosave-status');

        if (!form || !status) {
            return;
        }

        let autosaveTimer;
        let saveQueue = Promise.resolve();
        let draftRevision = 0;
        let savedRevision = 0;

        function setStatus(message, state) {
            const messageElement = document.getElementById('autosave-message');

            if (messageElement) {
                messageElement.textContent = message;
            } else {
                status.textContent = message;
            }

            status.dataset.state = state;
            status.setAttribute('aria-busy', state === 'saving' ? 'true' : 'false');
        }

        function buildDraftData(includeFiles) {
            const formData = new FormData(form);

            if (!includeFiles) {
                form.querySelectorAll('input[type="file"]').forEach(function (input) {
                    formData.delete(input.name);
                });
            }

            return formData;
        }

        function saveDraft(includeFiles, revision, attempt) {
            const formData = buildDraftData(includeFiles);
            const csrfToken = formData.get('_token');
            const retryAttempt = attempt || 0;

            setStatus('Menyimpan...', 'saving');

            const controller = new AbortController();
            const timeout = window.setTimeout(function () {
                controller.abort();
            }, 15000);

            return fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                signal: controller.signal
            }).then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () { return {}; }).then(function (payload) {
                        const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                        throw new Error(errors || payload.message || 'Kami tidak dapat menyimpan perubahan ini.');
                    });
                }

                savedRevision = Math.max(savedRevision, revision);

                if (savedRevision === draftRevision) {
                    setStatus('Semua perubahan telah disimpan', 'saved');
                }
            }).catch(function (error) {
                if (retryAttempt === 0) {
                    return new Promise(function (resolve) {
                        window.setTimeout(resolve, 1200);
                    }).then(function () {
                        return saveDraft(includeFiles, revision, 1);
                    });
                }

                if (revision === draftRevision) {
                    setStatus(
                        'Simpanan belum berjaya. ' + (error.message || 'Semak sambungan internet, kemudian tekan Simpan Draf.'),
                        'error'
                    );
                }
                throw error;
            }).finally(function () {
                window.clearTimeout(timeout);
            });
        }

        function queueSave(includeFiles) {
            const revision = draftRevision;

            saveQueue = saveQueue
                .catch(function () {})
                .then(function () {
                    return saveDraft(includeFiles, revision);
                });

            return saveQueue;
        }

        function scheduleAutosave(event) {
            const includeFiles = event.target instanceof HTMLInputElement && event.target.type === 'file';

            window.clearTimeout(autosaveTimer);
            draftRevision += 1;
            setStatus('Perubahan belum disimpan', 'pending');
            autosaveTimer = window.setTimeout(function () {
                queueSave(includeFiles).catch(function () {});
            }, 800);
        }

        form.addEventListener('input', scheduleAutosave);
        form.addEventListener('change', scheduleAutosave);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'hidden' || savedRevision === draftRevision) {
                return;
            }

            window.clearTimeout(autosaveTimer);
            queueSave(false).catch(function () {});
        });

        window.addEventListener('pagehide', function () {
            if (savedRevision === draftRevision) {
                return;
            }

            window.clearTimeout(autosaveTimer);

            /*
             * A normal fetch may be cancelled while a page is closing. sendBeacon
             * keeps the small, file-free draft request alive during navigation.
             */
            if (navigator.sendBeacon) {
                navigator.sendBeacon(form.action, buildDraftData(false));
                return;
            }

            queueSave(false).catch(function () {});
        });

        if (reviewLink) {
            reviewLink.addEventListener('click', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                event.preventDefault();
                window.clearTimeout(autosaveTimer);
                reviewLink.setAttribute('aria-disabled', 'true');
                reviewLink.classList.add('is-loading');
                reviewLink.textContent = 'Menyimpan maklumat...';

                queueSave(true)
                    .then(function () {
                        window.location.assign(reviewLink.href);
                    })
                    .catch(function () {
                        reviewLink.removeAttribute('aria-disabled');
                        reviewLink.classList.remove('is-loading');
                        reviewLink.textContent = 'Semak Maklumat & Teruskan';
                    });
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-order-form');
        const preview = document.querySelector('.live-preview');

        if (!form || !preview) {
            return;
        }

        const previewToggle = preview.querySelector('[data-live-preview-toggle]');
        const previewBody = preview.querySelector('[data-live-preview-body]');

        function setPreviewOpen(isOpen) {
            preview.classList.toggle('is-collapsed', !isOpen);
            previewToggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (previewBody) {
                previewBody.hidden = !isOpen;
            }
        }

        previewToggle?.addEventListener('click', function () {
            setPreviewOpen(preview.classList.contains('is-collapsed'));
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !preview.classList.contains('is-collapsed')) {
                setPreviewOpen(false);
                previewToggle?.focus();
            }
        });

        let activeSide = preview.querySelector('[data-card-preview]:not(.is-hidden)')?.dataset.cardPreview;
        let activeFace = 'front';
        let isFoldedCardPreview = preview.dataset.foldedCardPreview === 'true';

        function field(name) {
            return form.elements.namedItem(name);
        }

        function value(name, fallback) {
            const control = field(name);
            return control && control.value.trim() ? control.value.trim() : fallback;
        }

        function setText(card, key, text) {
            card.querySelectorAll('[data-preview-field="' + key + '"]').forEach(function (node) {
                node.textContent = text;
            });
        }

        function formatDate(raw) {
            if (!raw) {
                return 'TARIKH MAJLIS';
            }

            const date = new Date(raw + 'T00:00:00');
            return new Intl.DateTimeFormat('ms-MY', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).format(date);
        }

        function jawiCardTitle(title) {
            const translations = {
                'Walimatul Urus': 'وليمة العروس',
                'Majlis Perkahwinan': 'مجليس ڤركهوينن',
                'Kenduri Kesyukuran': 'کندوري کشوکورن'
            };

            return translations[title] || translations['Walimatul Urus'];
        }

        function syncCard(side) {
            const cardWrap = preview.querySelector('[data-card-preview="' + side + '"]');

            if (!cardWrap) {
                return;
            }

            const prefix = 'sides[' + side + ']';
            const groomName = value('couple[groom_name]', 'NAMA PENGANTIN');
            const brideName = value('couple[bride_name]', 'NAMA PASANGAN');
            const groomDisplay = value('couple[groom_abbreviation]', groomName);
            const brideDisplay = value('couple[bride_abbreviation]', brideName);

            setText(cardWrap, 'groom_name', groomName);
            setText(cardWrap, 'bride_name', brideName);
            setText(cardWrap, 'groom_display', groomDisplay);
            setText(cardWrap, 'bride_display', brideDisplay);
            const cardTitle = value(prefix + '[design][card_title]', 'Walimatul Urus');
            setText(cardWrap, 'card_title', cardTitle);
            setText(cardWrap, 'card_title_jawi', jawiCardTitle(cardTitle));
            setText(cardWrap, 'father_name', value(prefix + '[parents][father_name]', 'NAMA BAPA'));
            setText(cardWrap, 'mother_name', value(prefix + '[parents][mother_name]', 'NAMA IBU'));
            setText(cardWrap, 'day_name', value(prefix + '[event][day_name]', 'HARI').toUpperCase());
            setText(cardWrap, 'event_date', formatDate(value(prefix + '[event][event_date]', '')));
            setText(cardWrap, 'hijri_date', value(prefix + '[event][hijri_date]', ''));
            setText(cardWrap, 'meal_time', value(prefix + '[event][meal_time]', '-'));
            setText(cardWrap, 'bersanding_time', value(prefix + '[event][bersanding_time]', '-'));
            setText(cardWrap, 'venue_name', value(prefix + '[event][venue_name]', 'NAMA TEMPAT'));
            setText(cardWrap, 'full_address', value(prefix + '[event][full_address]', 'Alamat penuh majlis'));

            for (let number = 1; number <= 3; number += 1) {
                const contactPrefix = prefix + '[event][contacts][' + number + ']';
                setText(cardWrap, 'contact_' + number + '_name', value(contactPrefix + '[contact_name]', 'Ahli ' + number));
                setText(cardWrap, 'contact_' + number + '_phone', value(contactPrefix + '[contact_phone]', '-'));
            }

            const theme = value(prefix + '[design][theme]', 'songket').toLowerCase();
            cardWrap.querySelector('.wedding-card-front')?.setAttribute('data-theme', theme);
        }

        function showSelection() {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                card.classList.toggle('is-hidden', card.dataset.cardPreview !== activeSide);
                card.querySelectorAll('[data-card-face]').forEach(function (face) {
                    face.classList.toggle('is-hidden', face.dataset.cardFace !== activeFace);
                });
            });

            preview.querySelectorAll('[data-preview-folded-side]').forEach(function (tab) {
                tab.classList.toggle(
                    'is-active',
                    tab.dataset.previewFoldedSide === activeSide
                        && tab.dataset.previewFoldedFace === activeFace
                );
            });
        }

        function updateTwoPackageOrder() {
            const firstSideControl = field('first_event_side');

            if (!firstSideControl) {
                return;
            }

            const order = [firstSideControl.value, firstSideControl.value === 'LELAKI' ? 'PEREMPUAN' : 'LELAKI'];
            const sections = Array.from(form.querySelectorAll('[data-package-side]'));
            const reference = sections[sections.length - 1]?.nextElementSibling;
            const parent = sections[0]?.parentNode;

            order.forEach(function (side, index) {
                const section = form.querySelector('[data-package-side="' + side + '"]');
                if (section && parent) {
                    parent.insertBefore(section, reference);
                    const legend = section.querySelector('[data-package-legend]');
                    if (legend) legend.textContent = 'Majlis ' + (index + 1) + ' – Pihak ' + (side === 'LELAKI' ? 'Lelaki' : 'Perempuan');
                }
            });

            if (isFoldedCardPreview) {
                const tabs = preview.querySelector('[data-folded-preview-tabs]');
                const pages = [
                    { side: order[0], face: 'front', label: 'Halaman 1 · Kad Depan' },
                    { side: order[0], face: 'back', label: 'Halaman 2 · Kad Belakang · ' + (order[0] === 'LELAKI' ? 'Lelaki' : 'Perempuan') },
                    { side: order[1], face: 'back', label: 'Halaman 3 · Kad Belakang · ' + (order[1] === 'LELAKI' ? 'Lelaki' : 'Perempuan') },
                ];

                pages.forEach(function (page) {
                    const tab = preview.querySelector('[data-preview-folded-side="' + page.side + '"][data-preview-folded-face="' + page.face + '"]');
                    if (tab && tabs) {
                        tabs.appendChild(tab);
                        tab.textContent = page.label;
                    }
                });
            } else {
                const tabs = preview.querySelector('.preview-side-tabs');
                order.forEach(function (side, index) {
                    const tab = preview.querySelector('[data-preview-side-target="' + side + '"]');
                    if (tab && tabs) {
                        tabs.appendChild(tab);
                        const label = tab.querySelector('[data-preview-tab-label]');
                        if (label) label.textContent = 'Majlis ' + (index + 1) + ' – ' + (side === 'LELAKI' ? 'Lelaki' : 'Perempuan');
                    }
                });
            }

            activeSide = order[0];
            activeFace = 'front';
            preview.querySelectorAll('[data-preview-side-target]').forEach(function (tab) {
                tab.classList.toggle('is-active', tab.dataset.previewSideTarget === activeSide);
            });
            showSelection();
        }

        function updatePackageFormatPreview() {
            const packageFormat = field('package_format');

            if (!packageFormat) {
                return;
            }

            isFoldedCardPreview = packageFormat.value === 'FOLDED';
            preview.dataset.foldedCardPreview = isFoldedCardPreview ? 'true' : 'false';

            const foldedTabs = preview.querySelector('[data-folded-preview-tabs]');
            const separateTabs = preview.querySelector('[data-preview-separate-tabs]');
            const separateFaceTabs = preview.querySelector('[data-preview-separate-face-tabs]');

            if (foldedTabs) foldedTabs.hidden = !isFoldedCardPreview;
            if (separateTabs) separateTabs.hidden = isFoldedCardPreview;
            if (separateFaceTabs) separateFaceTabs.hidden = isFoldedCardPreview;

            form.querySelectorAll('[data-folded-second-design="true"]').forEach(function (section) {
                section.hidden = isFoldedCardPreview;
                section.querySelectorAll('input, select, textarea').forEach(function (control) {
                    control.disabled = isFoldedCardPreview;
                });
            });

            updateTwoPackageOrder();
        }

        function updatePortraitImageFields() {
            form.querySelectorAll('[data-card-image-upload]').forEach(function (uploadField) {
                const packageSide = uploadField.closest('[data-package-side]')?.dataset.packageSide;

                if (!packageSide) {
                    return;
                }

                const theme = value('sides[' + packageSide + '][design][theme]', '').toUpperCase();
                uploadField.hidden = theme !== 'PORTRAIT';
            });
        }

        preview.querySelectorAll('[data-preview-side-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                activeSide = button.dataset.previewSideTarget;
                preview.querySelectorAll('[data-preview-side-target]').forEach(function (tab) {
                    tab.classList.toggle('is-active', tab === button);
                });
                showSelection();
            });
        });

        preview.querySelectorAll('[data-preview-face-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                activeFace = button.dataset.previewFaceTarget;
                preview.querySelectorAll('[data-preview-face-target]').forEach(function (tab) {
                    tab.classList.toggle('is-active', tab === button);
                });
                showSelection();
            });
        });

        preview.querySelectorAll('[data-preview-folded-side]').forEach(function (button) {
            button.addEventListener('click', function () {
                activeSide = button.dataset.previewFoldedSide;
                activeFace = button.dataset.previewFoldedFace;
                showSelection();
            });
        });

        form.addEventListener('input', function () {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                syncCard(card.dataset.cardPreview);
            });
        });

        form.addEventListener('change', function (event) {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                syncCard(card.dataset.cardPreview);
            });
            if (event.target.name === 'first_event_side') {
                updateTwoPackageOrder();
            }
            if (event.target.name === 'package_format') {
                updatePackageFormatPreview();
            }
            if (event.target.name?.endsWith('[design][theme]')) {
                updatePortraitImageFields();
            }
        });

        preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
            syncCard(card.dataset.cardPreview);
        });
        updatePackageFormatPreview();
        updatePortraitImageFields();
        showSelection();
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const courierDetails = document.getElementById('courier-details');
        const methodInputs = document.querySelectorAll('input[name="fulfilment[method]"]');

        if (!courierDetails) {
            return;
        }

        function updateFulfilmentFields() {
            const selected = document.querySelector(
                'input[name="fulfilment[method]"]:checked'
            );

            const isCourier = selected && selected.value === 'COURIER';

            courierDetails.hidden = !isCourier;
        }

        methodInputs.forEach(function (input) {
            input.addEventListener('change', updateFulfilmentFields);
        });

        updateFulfilmentFields();
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.card-image-picker').forEach(function (picker) {
            const input = picker.querySelector('input[type="file"]');
            const cancelButton = picker.querySelector('.cancel-card-image');

            function updateCancelButton() {
                cancelButton.hidden = input.files.length === 0;
            }

            input.addEventListener('change', updateCancelButton);
            cancelButton.addEventListener('click', function () {
                input.value = '';
                updateCancelButton();
                input.focus();
            });

            updateCancelButton();
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-deposit-resubmission-form]');
        const button = form?.querySelector('[data-deposit-resubmission-button]');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Menghantar...';
        });
    });
</script>
@include('public.partials.customer-service')
@include('public.partials.theme-toggle')
</body>
</html>
