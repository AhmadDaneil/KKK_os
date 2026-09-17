<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>KKK OS - {{ $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/customer-dashboard.css') }}">
</head>
<body>
<main>
<header class="order-summary">
    <div class="order-summary-heading">
        <div>
            <p class="eyebrow">KING KAD KAHWIN</p>
            <h1>Tempahan Anda</h1>
        </div>

        <span class="status-badge">
            {{ $progress['label'] }}
        </span>
    </div>

    <div class="order-summary-grid">
        <div>
            <span class="summary-label">Order ID</span>
            <strong>{{ $order->order_id }}</strong>
        </div>

        <div>
            <span class="summary-label">Pakej</span>
            <strong>
                {{ $order->package_count }}
                Pakej
            </strong>
        </div>

        @if ($order->customer_name)
            <div>
                <span class="summary-label">Nama Pelanggan</span>
                <strong>{{ $order->customer_name }}</strong>
            </div>
        @endif

        @if ($order->customer_phone)
            <div>
                <span class="summary-label">No. Telefon</span>
                <strong>{{ $order->customer_phone }}</strong>
            </div>
        @endif

        @if ($order->customer_email)
            <div>
                <span class="summary-label">Email</span>
                <strong>{{ $order->customer_email }}</strong>
            </div>
        @endif
    </div>

    <section class="progress-panel" data-progress-tone="{{ $progress['tone'] }}">
        <div class="progress-heading">
            <div>
                <span class="summary-label">Kemajuan Tempahan</span>
                <strong>{{ $progress['label'] }}</strong>
            </div>
            <span class="progress-percentage">{{ $progress['percentage'] }}%</span>
        </div>

        <div
            class="progress-track"
            role="progressbar"
            aria-label="Kemajuan tempahan"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $progress['percentage'] }}"
        >
            <span class="progress-fill" style="width: {{ $progress['percentage'] }}%"></span>
        </div>

        <div class="progress-stages" aria-label="Peringkat tempahan">
            @foreach ($progress['stages'] as $stage)
                <div class="progress-stage @if ($stage['complete']) is-complete @endif">
                    <span class="stage-dot" aria-hidden="true"></span>
                    <span>{{ $stage['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="order-status-message">
        {{ $progress['message'] }}
    </div>
</header>

    @if (in_array($order->status, [
    'DETAILS_CONFIRMED',
    'READY_FOR_DESIGN',
    'DESIGN_IN_PROGRESS',
    'DESIGN_READY',
    'CORRECTION_REQUESTED',
    'DESIGN_APPROVED',
], true))
    <section class="dashboard-action-card">
        <h2>Artwork Tempahan</h2>

        @if ($order->status === 'DETAILS_CONFIRMED')
            <p>
                Maklumat tempahan anda telah disahkan.
                Artwork anda akan disediakan oleh designer.
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
                Designer sedang menyediakan artwork tempahan anda.
            </p>

            <span class="artwork-progress-label">
                Design sedang disediakan
            </span>

        @elseif ($order->status === 'DESIGN_READY')
            <p>
                Artwork anda telah tersedia. Sila semak artwork sebelum membuat
                kelulusan atau meminta pembetulan.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Semak Artwork
            </a>

        @elseif ($order->status === 'CORRECTION_REQUESTED')
            <p>
                Permintaan pembetulan anda sedang diproses. Anda masih boleh
                melihat status semakan artwork.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Lihat Status Artwork
            </a>

        @else
            <p>
                Artwork tempahan anda telah diluluskan.
            </p>

            <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]) }}">
                Lihat Artwork
            </a>
        @endif
    </section>
@endif

@if (session('draft_saved'))
        <div class="notice">Draft berjaya disimpan. Anda boleh keluar dan sambung semula melalui link dashboard yang sama.</div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Sila semak maklumat berikut:</strong>
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

        <fieldset>
            <legend>Maklumat Pasangan</legend>
            <div class="grid">
                <div><label>Nama Pengantin Lelaki</label><input name="couple[groom_name]" value="{{ old('couple.groom_name', $couple?->groom_name) }}"></div>
                <div><label>Singkatan Pengantin Lelaki</label><input name="couple[groom_abbreviation]" value="{{ old('couple.groom_abbreviation', $couple?->groom_abbreviation) }}"></div>
                <div><label>Nama Pengantin Perempuan</label><input name="couple[bride_name]" value="{{ old('couple.bride_name', $couple?->bride_name) }}"></div>
                <div><label>Singkatan Pengantin Perempuan</label><input name="couple[bride_abbreviation]" value="{{ old('couple.bride_abbreviation', $couple?->bride_abbreviation) }}"></div>
            </div>
        </fieldset>
        <fieldset>
    <legend>Pasangan Kedua (Jika Ada)</legend>

    <p class="field-help">
        Isi bahagian ini hanya jika majlis melibatkan dua pasangan pengantin.
        Jika tidak berkenaan, biarkan kosong.
    </p>

    <div class="grid">
        <div>
            <label>Nama Pengantin Lelaki Kedua</label>
            <input
                name="second_couple[groom_name]"
                value="{{ old('second_couple.groom_name', $secondCouple?->groom_name) }}"
            >
        </div>

        <div>
            <label>Singkatan Pengantin Lelaki Kedua</label>
            <input
                name="second_couple[groom_abbreviation]"
                value="{{ old('second_couple.groom_abbreviation', $secondCouple?->groom_abbreviation) }}"
            >
        </div>

        <div>
            <label>Nama Pengantin Perempuan Kedua</label>
            <input
                name="second_couple[bride_name]"
                value="{{ old('second_couple.bride_name', $secondCouple?->bride_name) }}"
            >
        </div>

        <div>
            <label>Singkatan Pengantin Perempuan Kedua</label>
            <input
                name="second_couple[bride_abbreviation]"
                value="{{ old('second_couple.bride_abbreviation', $secondCouple?->bride_abbreviation) }}"
            >
        </div>
    </div>
</fieldset>

        @foreach ($order->packageSides->sortBy('side') as $packageSide)
            @php($side = $packageSide->side)
            @php($event = $packageSide->event)
            <fieldset data-package-side="{{ $side }}">
                <legend>Pakej {{ ucfirst(strtolower($side)) }}</legend>
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
    </div>

    <div>
        <label>Kod Design</label>
        <input
            name="sides[{{ $side }}][design][design_code]"
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

<div class="image-upload-field">
    <label for="card-image-{{ strtolower($side) }}">
        Gambar Pengantin
    </label>

    <p class="field-help">
        Muat naik jika design yang dipilih memerlukan gambar pengantin.
        Format JPG, JPEG, PNG atau WEBP. Maksimum 10 MB.
    </p>

    @if ($packageSide->design?->card_image_path)
        <p class="upload-status">
            Gambar telah dimuat naik.
            Pilih fail baharu di bawah hanya jika anda mahu menggantikannya.
        </p>
    @endif

    <input
        id="card-image-{{ strtolower($side) }}"
        type="file"
        name="sides[{{ $side }}][design][card_image]"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
    >

    @error("sides.$side.design.card_image")
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
                <h3>Ibu Bapa</h3>
                <div class="grid">
                    <div><label>Nama Bapa</label><input name="sides[{{ $side }}][parents][father_name]" value="{{ old("sides.$side.parents.father_name", $packageSide->parents?->father_name) }}"></div>
                    <div><label>Nama Ibu</label><input name="sides[{{ $side }}][parents][mother_name]" value="{{ old("sides.$side.parents.mother_name", $packageSide->parents?->mother_name) }}"></div>
                </div>
                <h3>Majlis</h3>
                <div class="grid">
                    @php($selectedDay = old("sides.$side.event.day_name", $event?->day_name))

<div>
    <label>Hari</label>

    <select name="sides[{{ $side }}][event][day_name]">
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
                    <div><label>Tarikh</label><input type="date" name="sides[{{ $side }}][event][event_date]" value="{{ old("sides.$side.event.event_date", $event?->event_date?->format('Y-m-d')) }}"></div>
                    <div><label>Tarikh Hijri</label><input name="sides[{{ $side }}][event][hijri_date]" value="{{ old("sides.$side.event.hijri_date", $event?->hijri_date) }}"></div>
                    <div><label>Masa Makan</label><input type="time" name="sides[{{ $side }}][event][meal_time]" value="{{ old("sides.$side.event.meal_time", $event?->meal_time ? substr($event->meal_time, 0, 5) : '') }}"></div>
                    <div><label>Masa Bersanding</label><input type="time" name="sides[{{ $side }}][event][bersanding_time]" value="{{ old("sides.$side.event.bersanding_time", $event?->bersanding_time ? substr($event->bersanding_time, 0, 5) : '') }}"></div>
                    <div><label>Nama Tempat</label><input name="sides[{{ $side }}][event][venue_name]" value="{{ old("sides.$side.event.venue_name", $event?->venue_name) }}"></div>
                </div>
                <label>Alamat Penuh</label>
                <textarea rows="4" name="sides[{{ $side }}][event][full_address]">{{ old("sides.$side.event.full_address", $event?->full_address) }}</textarea>
                <label>Google Maps URL</label>
                <input type="url" name="sides[{{ $side }}][event][google_maps_url]" value="{{ old("sides.$side.event.google_maps_url", $event?->google_maps_url) }}">
                <h3>Contact Person</h3>

@for ($contactNumber = 1; $contactNumber <= 3; $contactNumber++)
    @php($contact = $event?->contacts?->firstWhere('contact_number', $contactNumber))

    <div class="grid">
        <div>
            <label>Contact {{ $contactNumber }} - Nama</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_name]"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_name", $contact?->contact_name) }}"
            >
        </div>

        <div>
            <label>Contact {{ $contactNumber }} - Telefon</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_phone]"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_phone", $contact?->contact_phone) }}"
            >
        </div>
    </div>
@endfor
            </fieldset>
        @endforeach

        <fieldset>
    <legend>Penghantaran / Pickup</legend>

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
                <strong>Pos / Courier</strong><br>
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
                <strong>Self Pickup</strong><br>
                Ambil sendiri tempahan di KKK.
            </span>
        </label>
    </div>

    <div
    id="courier-details"
    class="courier-details"
    @if ($fulfilmentMethod !== 'COURIER') hidden @endif
>
    <h3>Maklumat Penghantaran Courier</h3>

    <div class="grid">
        <div>
            <label>Nama Penerima</label>
            <input
                id="courier-recipient-name"
                name="fulfilment[recipient_name]"
                value="{{ old('fulfilment.recipient_name', $order->fulfilment?->recipient_name) }}"
            >
        </div>

        <div>
            <label>Telefon Penerima</label>
            <input
                id="courier-recipient-phone"
                name="fulfilment[recipient_phone]"
                value="{{ old('fulfilment.recipient_phone', $order->fulfilment?->recipient_phone) }}"
            >
        </div>
    </div>

    <label>Alamat Penghantaran</label>
    <textarea
        id="courier-shipping-address"
        rows="4"
        name="fulfilment[shipping_address]"
    >{{ old('fulfilment.shipping_address', $order->fulfilment?->shipping_address) }}</textarea>
</div>
</fieldset>

    </fieldset>
            </div>
        </div>

@if ($isEditable)
    <div class="form-actions">
        <button type="submit">Simpan Draft</button>

        <span id="autosave-status" class="autosave-status" role="status" aria-live="polite">
            Perubahan disimpan secara automatik
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
                label: 'Kaedah Fulfilment',
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
                        error.textContent = 'Sila pilih Kaedah Fulfilment.';

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

                    ['event][contacts][1][contact_name]', 'Contact 1 - Nama'],
                    ['event][contacts][1][contact_phone]', 'Contact 1 - Telefon'],

                    ['event][contacts][2][contact_name]', 'Contact 2 - Nama'],
                    ['event][contacts][2][contact_phone]', 'Contact 2 - Telefon'],

                    ['event][contacts][3][contact_name]', 'Contact 3 - Nama'],
                    ['event][contacts][3][contact_phone]', 'Contact 3 - Telefon']
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

        if (!form || !reviewLink || !status) {
            return;
        }

        let autosaveTimer;
        let saveQueue = Promise.resolve();

        function setStatus(message, state) {
            status.textContent = message;
            status.dataset.state = state;
        }

        function saveDraft(includeFiles) {
            const formData = new FormData(form);

            if (!includeFiles) {
                form.querySelectorAll('input[type="file"]').forEach(function (input) {
                    formData.delete(input.name);
                });
            }

            setStatus('Menyimpan...', 'saving');

            return fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Autosave failed');
                }

                setStatus('Semua perubahan telah disimpan', 'saved');
            }).catch(function (error) {
                setStatus('Simpanan automatik gagal. Sila tekan Simpan Draft.', 'error');
                throw error;
            });
        }

        function queueSave(includeFiles) {
            saveQueue = saveQueue
                .catch(function () {})
                .then(function () {
                    return saveDraft(includeFiles);
                });

            return saveQueue;
        }

        function scheduleAutosave(event) {
            if (event.target.type === 'file') {
                setStatus('Tekan Simpan Draft atau Seterusnya untuk memuat naik fail', 'pending');
                return;
            }

            window.clearTimeout(autosaveTimer);
            setStatus('Perubahan belum disimpan', 'pending');
            autosaveTimer = window.setTimeout(function () {
                queueSave(false).catch(function () {});
            }, 800);
        }

        form.addEventListener('input', scheduleAutosave);
        form.addEventListener('change', scheduleAutosave);

        reviewLink.addEventListener('click', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            event.preventDefault();
            window.clearTimeout(autosaveTimer);
            reviewLink.setAttribute('aria-disabled', 'true');

            queueSave(true)
                .then(function () {
                    window.location.assign(reviewLink.href);
                })
                .catch(function () {
                    reviewLink.removeAttribute('aria-disabled');
                });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-order-form');
        const preview = document.querySelector('.live-preview');

        if (!form || !preview) {
            return;
        }

        let activeSide = preview.querySelector('[data-card-preview]:not(.is-hidden)')?.dataset.cardPreview;
        let activeFace = 'front';

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

        form.addEventListener('input', function () {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                syncCard(card.dataset.cardPreview);
            });
        });

        form.addEventListener('change', function () {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                syncCard(card.dataset.cardPreview);
            });
        });

        preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
            syncCard(card.dataset.cardPreview);
        });
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
</body>
</html>
