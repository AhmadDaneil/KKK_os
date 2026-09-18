<aside class="live-preview" aria-labelledby="live-preview-title">
    <div class="live-preview-heading">
        <div>
            <p class="eyebrow">Live Preview</p>
            <h2 id="live-preview-title">Kad 4 × 6</h2>
        </div>
        <span>Anggaran paparan</span>
    </div>

    @if ($order->packageSides->count() > 1)
        <div class="preview-side-tabs" role="tablist" aria-label="Pilih pakej untuk preview">
            @foreach ($orderedPackageSides as $packageSide)
                <button type="button" class="preview-side-tab @if ($loop->first) is-active @endif" data-preview-side-target="{{ $packageSide->side }}">
                    <span data-preview-tab-label>Majlis {{ $loop->iteration }} – {{ $packageSide->side === 'LELAKI' ? 'Lelaki' : 'Perempuan' }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div class="preview-face-tabs" role="tablist" aria-label="Pilih muka kad">
        <button type="button" class="preview-face-tab is-active" data-preview-face-target="front">Hadapan</button>
        <button type="button" class="preview-face-tab" data-preview-face-target="back">Belakang</button>
    </div>

    @foreach ($orderedPackageSides as $packageSide)
        @php
            $jawiCardTitle = match ($packageSide->design?->card_title) {
                'Majlis Perkahwinan' => 'مجليس ڤركهوينن',
                'Kenduri Kesyukuran' => 'کندوري کشوکورن',
                default => 'وليمة العروس',
            };
        @endphp
        <div class="card-preview-wrap @if (! $loop->first) is-hidden @endif" data-card-preview="{{ $packageSide->side }}">
            <article class="wedding-card wedding-card-front" data-card-face="front" data-theme="{{ strtolower($packageSide->design?->theme ?: 'songket') }}">
                <div class="card-ornament ornament-top" aria-hidden="true"></div>
                <div class="card-front-copy">
                    <p class="card-title" data-preview-field="card_title">{{ $packageSide->design?->card_title ?: 'Walimatul Urus' }}</p>
                    <div class="card-couple-names">
                        @php($hostIsGroom = $packageSide->side === 'LELAKI')
                        <span data-preview-field="{{ $hostIsGroom ? 'groom_display' : 'bride_display' }}">{{ $hostIsGroom ? ($couple?->groom_abbreviation ?: $couple?->groom_name ?: 'Nama Pengantin') : ($couple?->bride_abbreviation ?: $couple?->bride_name ?: 'Nama Pengantin') }}</span>
                        <b>&amp;</b>
                        <span data-preview-field="{{ $hostIsGroom ? 'bride_display' : 'groom_display' }}">{{ $hostIsGroom ? ($couple?->bride_abbreviation ?: $couple?->bride_name ?: 'Pasangan') : ($couple?->groom_abbreviation ?: $couple?->groom_name ?: 'Pasangan') }}</span>
                    </div>
                    <div class="card-date-lockup">
                        <span class="calendar-symbol" aria-hidden="true">▣</span>
                        <strong data-preview-field="day_name">{{ $packageSide->event?->day_name ?: 'HARI' }}</strong>
                        <span data-preview-field="event_date">{{ $packageSide->event?->event_date?->format('d F Y') ?: 'TARIKH MAJLIS' }}</span>
                    </div>
                </div>
                <div class="card-ornament ornament-bottom" aria-hidden="true"></div>
            </article>

            <article class="wedding-card wedding-card-back is-hidden" data-card-face="back">
                <header class="card-invitation-header">
                    <span class="arabic-mark" lang="ms-Arab" dir="rtl" data-preview-field="card_title_jawi">{{ $jawiCardTitle }}</span>
                    <p>Dengan penuh kesyukuran, kami</p>
                    <strong data-preview-field="father_name">{{ $packageSide->parents?->father_name ?: 'NAMA BAPA' }}</strong>
                    <span>&amp;</span>
                    <strong data-preview-field="mother_name">{{ $packageSide->parents?->mother_name ?: 'NAMA IBU' }}</strong>
                    <p>menjemput Dato’ / Datin / Tuan / Puan / Encik / Cik ke majlis perkahwinan anakanda kami</p>
                    <strong data-preview-field="{{ $hostIsGroom ? 'groom_name' : 'bride_name' }}">{{ $hostIsGroom ? ($couple?->groom_name ?: 'NAMA PENGANTIN') : ($couple?->bride_name ?: 'NAMA PENGANTIN') }}</strong>
                    <span>&amp;</span>
                    <strong data-preview-field="{{ $hostIsGroom ? 'bride_name' : 'groom_name' }}">{{ $hostIsGroom ? ($couple?->bride_name ?: 'NAMA PASANGAN') : ($couple?->groom_name ?: 'NAMA PASANGAN') }}</strong>
                </header>

                <div class="card-info-grid">
                    <section><small>Tarikh</small><b data-preview-field="day_name">{{ $packageSide->event?->day_name ?: 'HARI' }}</b><strong data-preview-field="event_date">{{ $packageSide->event?->event_date?->format('d/m/Y') ?: '-' }}</strong><span data-preview-field="hijri_date">{{ $packageSide->event?->hijri_date ?: '' }}</span></section>
                    <section><small>Aturcara</small><b>Jamuan</b><span data-preview-field="meal_time">{{ $packageSide->event?->meal_time ? substr($packageSide->event->meal_time, 0, 5) : '-' }}</span><b>Bersanding</b><span data-preview-field="bersanding_time">{{ $packageSide->event?->bersanding_time ? substr($packageSide->event->bersanding_time, 0, 5) : '-' }}</span></section>
                    <section class="card-contacts"><small>Hubungi</small>@for ($contactNumber = 1; $contactNumber <= 3; $contactNumber++) @php($contact = $packageSide->event?->contacts?->firstWhere('contact_number', $contactNumber)) <b data-preview-field="contact_{{ $contactNumber }}_name">{{ $contact?->contact_name ?: 'Ahli '.$contactNumber }}</b><span data-preview-field="contact_{{ $contactNumber }}_phone">{{ $contact?->contact_phone ?: '-' }}</span>@endfor</section>
                </div>

                <div class="card-location">
                    <div class="qr-placeholder" aria-hidden="true"><span></span></div>
                    <div><b>Lokasi Majlis</b><strong data-preview-field="venue_name">{{ $packageSide->event?->venue_name ?: 'NAMA TEMPAT' }}</strong><p data-preview-field="full_address">{{ $packageSide->event?->full_address ?: 'Alamat penuh majlis' }}</p></div>
                </div>
                <footer>Semoga dengan kehadiran para hadirin akan memeriahkan lagi majlis ini.</footer>
            </article>
        </div>
    @endforeach

    <p class="preview-note">Preview ini membantu semakan susun atur. Hasil cetakan sebenar mungkin berbeza sedikit mengikut design yang dipilih.</p>
</aside>
