<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Semakan Akhir - {{ $review['order_id'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/final-review.css') }}">
</head>
<body>
    @php
        $secondCouple = $review['second_couple'] ?? [];
        $hasSecondCouple = collect($secondCouple)->filter(fn ($value) => filled($value))->isNotEmpty();

        $fulfilmentLabel = match ($review['fulfilment']['method'] ?? null) {
            'COURIER' => 'Pos / Courier',
            'PICKUP' => 'Self Pickup di KKK',
            default => $review['fulfilment']['method'] ?? '-',
        };
    @endphp

    <main class="final-review-page">
        <header class="review-header">
            <div class="review-header-top">
                <div>
                    <p class="eyebrow">Semakan Maklumat Tempahan</p>
                    <h1>Semakan Akhir</h1>
                </div>

                <span class="order-badge">{{ $review['order_id'] }}</span>
            </div>

            <p class="review-intro">
                Sila semak semua maklumat di bawah dengan teliti sebelum membuat pengesahan akhir.
                Maklumat yang telah disahkan akan dikunci daripada suntingan customer.
            </p>
        </header>

        @if ($errors->any())
            <section class="review-card">
                <h2>Terdapat perkara yang perlu disemak</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="review-card">
            <h2>Ringkasan Tempahan</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Order ID</span>
                    <span class="review-value">{{ $review['order_id'] }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Jumlah Pakej</span>
                    <span class="review-value">{{ $review['package_count'] }}</span>
                </div>
            </div>
        </section>

        <section class="review-card">
            <h2>Pasangan Utama</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Nama Pengantin Lelaki</span>
                    <span class="review-value">{{ $review['couple']['groom_name'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Singkatan Pengantin Lelaki</span>
                    <span class="review-value">{{ $review['couple']['groom_abbreviation'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Nama Pengantin Perempuan</span>
                    <span class="review-value">{{ $review['couple']['bride_name'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Singkatan Pengantin Perempuan</span>
                    <span class="review-value">{{ $review['couple']['bride_abbreviation'] ?: '-' }}</span>
                </div>
            </div>
        </section>

        @if ($hasSecondCouple)
            <section class="review-card">
                <h2>Pasangan Kedua</h2>
                <p class="review-intro">
                    Maklumat ini adalah untuk rujukan designer dan tidak digunakan dalam standard Photoshop Auto Merge V1.
                </p>

                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Nama Pengantin Lelaki</span>
                        <span class="review-value">{{ $secondCouple['groom_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Singkatan Pengantin Lelaki</span>
                        <span class="review-value">{{ $secondCouple['groom_abbreviation'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Nama Pengantin Perempuan</span>
                        <span class="review-value">{{ $secondCouple['bride_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Singkatan Pengantin Perempuan</span>
                        <span class="review-value">{{ $secondCouple['bride_abbreviation'] ?: '-' }}</span>
                    </div>
                </div>
            </section>
        @endif

        @foreach ($review['package_sides'] as $side)
            @php
                $sideLabel = $side['side'] === 'LELAKI' ? 'Lelaki' : 'Perempuan';
                $event = $side['event'];
                $design = $side['design'];
                $parents = $side['parents'];
            @endphp

            <section class="review-card package-card">
                <div class="package-heading">
                    <h2>Pakej {{ $sideLabel }}</h2>
                    <span class="side-badge">{{ $side['side'] }}</span>
                </div>

                <h3>Design Kad</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Tema</span>
                        <span class="review-value">{{ $design['theme'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Kod Design</span>
                        <span class="review-value">{{ $design['design_code'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Tajuk Majlis</span>
                        <span class="review-value">{{ $design['card_title'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Gambar Pengantin</span>
                        @if ($design['has_card_image'])
                            <span class="upload-status">Gambar telah dimuat naik</span>
                        @else
                            <span class="upload-status not-uploaded">Tiada gambar dimuat naik</span>
                        @endif
                    </div>
                </div>

                <h3>Maklumat Ibu Bapa</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Nama Bapa</span>
                        <span class="review-value">{{ $parents['father_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Nama Ibu</span>
                        <span class="review-value">{{ $parents['mother_name'] ?: '-' }}</span>
                    </div>
                </div>

                <h3>Maklumat Majlis</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Hari</span>
                        <span class="review-value">{{ $event['day_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Tarikh Majlis</span>
                        <span class="review-value">{{ $event['event_date'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Tarikh Hijri</span>
                        <span class="review-value">{{ $event['hijri_date'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Masa Jamuan Makan</span>
                        <span class="review-value">{{ $event['meal_time'] ? substr($event['meal_time'], 0, 5) : '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Masa Bersanding</span>
                        <span class="review-value">{{ $event['bersanding_time'] ? substr($event['bersanding_time'], 0, 5) : '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Tempat Majlis</span>
                        <span class="review-value">{{ $event['venue_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Alamat Majlis</span>
                        <span class="review-value">{{ $event['full_address'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Google Maps</span>
                        @if ($event['google_maps_url'])
                            <a class="maps-link" href="{{ $event['google_maps_url'] }}" target="_blank" rel="noopener noreferrer">
                                Buka lokasi Google Maps
                            </a>
                        @else
                            <span class="review-value empty-value">-</span>
                        @endif
                    </div>
                </div>

                <h3>Contact Person</h3>
                <ol class="contact-list">
                    @foreach ($event['contacts'] as $contact)
                        <li>
                            <span class="contact-number">Contact {{ $contact['contact_number'] }}</span>
                            <strong>{{ $contact['name'] ?: '-' }}</strong>
                            <span> — {{ $contact['phone'] ?: '-' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach

        <section class="review-card">
            <h2>Penghantaran / Pengambilan</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Kaedah</span>
                    <span class="review-value">{{ $fulfilmentLabel }}</span>
                </div>

                @if (($review['fulfilment']['method'] ?? null) === 'COURIER')
                    <div class="review-item">
                        <span class="review-label">Nama Penerima</span>
                        <span class="review-value">{{ $review['fulfilment']['recipient_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Telefon Penerima</span>
                        <span class="review-value">{{ $review['fulfilment']['recipient_phone'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Alamat Penghantaran</span>
                        <span class="review-value">{{ $review['fulfilment']['shipping_address'] ?: '-' }}</span>
                    </div>
                @endif
            </div>
        </section>

        <section class="review-card confirmation-card">
            <h2>Pengesahan Akhir</h2>

            <p class="confirmation-note">
                Selepas anda mengesahkan maklumat ini, maklumat tempahan akan dikunci daripada suntingan customer.
                Pastikan semua nama, tarikh, masa, alamat, design dan nombor telefon adalah betul.
            </p>

            <form method="POST" action="{{ route('orders.confirm.store', ['orderId' => $review['order_id']]) }}">
                @csrf

                <label class="confirmation-checkbox">
                    <input
                        type="checkbox"
                        name="responsibility_acknowledged"
                        value="1"
                        required
                        @checked(old('responsibility_acknowledged'))
                    >
                    <span>
                        Saya telah menyemak semua maklumat di atas dan mengesahkan bahawa maklumat tersebut adalah betul.
                    </span>
                </label>

                <div class="review-actions">
                    <a class="back-link" href="{{ route('orders.dashboard', ['orderId' => $review['order_id']]) }}">
                        Kembali & Betulkan Maklumat
                    </a>

                    <button class="confirm-button" type="submit">
                        Sahkan Maklumat Tempahan
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>