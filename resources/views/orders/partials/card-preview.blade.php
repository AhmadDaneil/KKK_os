@php
    $isFoldedCard = $order->package_count === 2 && $order->package_format === 'FOLDED';
@endphp

<section class="live-preview" aria-labelledby="live-preview-title" data-live-preview data-folded-card-preview="{{ $isFoldedCard ? 'true' : 'false' }}">
    <div class="live-preview-body" id="live-preview-panel" data-live-preview-body>
        <div class="live-preview-heading">
            <div>
                <p class="eyebrow">LIVE PREVIEW</p>
                <h2 id="live-preview-title">Live Card Preview</h2>
                @if ($hasSelectableSinglePackageSide)
                    <p class="preview-note" data-single-package-preview-note>Sample layout — choose the package side in the order form.</p>
                @endif
            </div>
            <span>Updates as you type</span>
        </div>

    <div class="preview-page-tabs" role="tablist" aria-label="Choose folded card page" data-folded-preview-tabs @if (! $isFoldedCard) hidden @endif>
            @php
                $firstPackageSide = $previewPackageSides->first();
            @endphp
            @if ($firstPackageSide)
                <button type="button" class="preview-page-tab is-active" data-preview-folded-side="{{ $firstPackageSide->side }}" data-preview-folded-face="front">
                    Page 1 · Card Front
                </button>
            @endif
            @foreach ($previewPackageSides as $packageSide)
                <button type="button" class="preview-page-tab" data-preview-folded-side="{{ $packageSide->side }}" data-preview-folded-face="back">
                    Page {{ $loop->iteration + 1 }} · Card Back · {{ $packageSide->side === 'LELAKI' ? 'Groom' : 'Bride' }}
                </button>
            @endforeach
    </div>

    @if ($order->packageSides->count() > 1)
        <div class="preview-side-tabs" role="tablist" aria-label="Choose package to preview" data-preview-separate-tabs @if ($isFoldedCard) hidden @endif>
            @foreach ($previewPackageSides as $packageSide)
                <button type="button" class="preview-side-tab @if ($loop->first) is-active @endif" data-preview-side-target="{{ $packageSide->side }}">
                    <span data-preview-tab-label>Event {{ $loop->iteration }} – {{ $packageSide->side === 'LELAKI' ? 'Groom' : 'Bride' }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div class="preview-face-tabs" role="tablist" aria-label="Choose card side" data-preview-separate-face-tabs @if ($isFoldedCard) hidden @endif>
        <button type="button" class="preview-face-tab is-active" data-preview-face-target="front">Front</button>
        <button type="button" class="preview-face-tab" data-preview-face-target="back">Back</button>
    </div>

    @foreach ($previewPackageSides as $packageSide)
        @php
            $jawiCardTitle = match ($packageSide->design?->card_title) {
                'Majlis Perkahwinan' => 'Wedding Ceremony',
                'Kenduri Kesyukuran' => 'Thanksgiving Celebration',
                default => 'Wedding Reception',
            };
        @endphp
        <div class="card-preview-wrap @if (! $loop->first) is-hidden @endif" data-card-preview="{{ $packageSide->side }}">
            <article class="wedding-card wedding-card-front" data-card-face="front" data-theme="{{ strtolower($packageSide->design?->theme ?: 'songket') }}">
                <div class="card-ornament ornament-top" aria-hidden="true"></div>
                <div class="card-front-copy">
                    <p class="card-title" data-preview-field="card_title">{{ $jawiCardTitle }}</p>
                    <div class="card-couple-names">
                        @php
                            $hostIsGroom = $packageSide->side === 'LELAKI';
                        @endphp
                        <span data-preview-field="{{ $hostIsGroom ? 'groom_display' : 'bride_display' }}">{{ $hostIsGroom ? ($couple?->groom_abbreviation ?: $couple?->groom_name ?: 'Groom') : ($couple?->bride_abbreviation ?: $couple?->bride_name ?: 'Bride') }}</span>
                        <b>&amp;</b>
                        <span data-preview-field="{{ $hostIsGroom ? 'bride_display' : 'groom_display' }}">{{ $hostIsGroom ? ($couple?->bride_abbreviation ?: $couple?->bride_name ?: 'Bride') : ($couple?->groom_abbreviation ?: $couple?->groom_name ?: 'Groom') }}</span>
                    </div>
                    <div class="card-date-lockup">
                        <span class="calendar-symbol" aria-hidden="true">▣</span>
                        <strong data-preview-field="day_name">{{ ['Ahad' => 'Sunday', 'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday'][$packageSide->event?->day_name ?? ''] ?? 'DAY' }}</strong>
                        <span data-preview-field="event_date">{{ $packageSide->event?->event_date?->format('d F Y') ?: 'EVENT DATE' }}</span>
                    </div>
                </div>
                <div class="card-ornament ornament-bottom" aria-hidden="true"></div>
            </article>

            <article class="wedding-card wedding-card-back is-hidden" data-card-face="back">
                <header class="card-invitation-header">
                    <span class="arabic-mark" lang="en" dir="ltr" data-preview-field="card_title_jawi">{{ $jawiCardTitle }}</span>
                    <p>With heartfelt gratitude, we</p>
                    <strong data-preview-field="father_name">{{ $packageSide->parents?->father_name ?: "FATHER'S NAME" }}</strong>
                    <span>&amp;</span>
                    <strong data-preview-field="mother_name">{{ $packageSide->parents?->mother_name ?: "MOTHER'S NAME" }}</strong>
                    <p>invite you to celebrate the wedding of our beloved child</p>
                    <strong data-preview-field="{{ $hostIsGroom ? 'groom_name' : 'bride_name' }}">{{ $hostIsGroom ? ($couple?->groom_name ?: 'GROOM NAME') : ($couple?->bride_name ?: 'BRIDE NAME') }}</strong>
                    <span>&amp;</span>
                    <strong data-preview-field="{{ $hostIsGroom ? 'bride_name' : 'groom_name' }}">{{ $hostIsGroom ? ($couple?->bride_name ?: 'BRIDE NAME') : ($couple?->groom_name ?: 'GROOM NAME') }}</strong>
                </header>

                <div class="card-info-grid">
                    <section><small>Date</small><b data-preview-field="day_name">{{ ['Ahad' => 'Sunday', 'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday'][$packageSide->event?->day_name ?? ''] ?? 'DAY' }}</b><strong data-preview-field="event_date">{{ $packageSide->event?->event_date?->format('d/m/Y') ?: '-' }}</strong><span data-preview-field="hijri_date">{{ $packageSide->event?->hijri_date ?: '' }}</span></section>
                    <section><small>Schedule</small><b>Meal</b><span data-preview-field="meal_time">{{ $packageSide->event?->meal_time ? substr($packageSide->event->meal_time, 0, 5) : '-' }}</span><b>Reception</b><span data-preview-field="bersanding_time">{{ $packageSide->event?->bersanding_time ? substr($packageSide->event->bersanding_time, 0, 5) : '-' }}</span></section>
                    <section class="card-contacts">
                        <small>Contacts</small>
                        @for ($contactNumber = 1; $contactNumber <= 3; $contactNumber++)
                            @php
                                $contact = $packageSide->event?->contacts?->firstWhere('contact_number', $contactNumber);
                            @endphp
                            <b data-preview-field="contact_{{ $contactNumber }}_name">{{ $contact?->contact_name ?: 'Contact '.$contactNumber }}</b>
                            <span data-preview-field="contact_{{ $contactNumber }}_phone">{{ $contact?->contact_phone ?: '-' }}</span>
                        @endfor
                    </section>
                </div>

                <div class="card-location">
                    <div class="qr-placeholder" aria-hidden="true"><span></span></div>
                    <div><b>Event Location</b><strong data-preview-field="venue_name">{{ $packageSide->event?->venue_name ?: 'VENUE NAME' }}</strong><p data-preview-field="full_address">{{ $packageSide->event?->full_address ?: 'Full event address' }}</p></div>
                </div>
                <footer>
                    <span>Your presence will make this celebration even more memorable.</span>
                    <span class="card-social-handle"><span class="card-social-logo" aria-hidden="true">◎</span> @kingkadkahwin</span>
                </footer>
            </article>
        </div>
    @endforeach

    @foreach ($previewPackageSides as $packageSide)
        <section class="live-product-previews @if (! $loop->first) is-hidden @endif" data-live-product-preview="{{ $packageSide->side }}">
            <div class="live-product-heading">
                <div>
                    <p class="eyebrow">Included in your package</p>
                    <h3>{{ $order->package_type === 'PUTERI_MANIS' ? 'Banner, Bunting, Arrow Posters, Sticker & Wooden Hanger' : 'Banner, Bunting, Arrow Posters & Sticker' }}</h3>
                </div>
                <span>{{ $packageSide->side === 'LELAKI' ? "Groom's Side" : "Bride's Side" }}</span>
            </div>

            <div class="live-product-grid{{ $order->package_type === 'PUTERI_MANIS' ? ' live-product-grid--puteri-manis' : '' }}">
                <article class="live-product live-product--banner" data-live-product-theme>
                    <span class="live-product-label">Banner</span>
                    <p data-preview-field="card_title">Wedding Reception</p>
                    <strong><span data-preview-field="groom_display">Groom</span> <i>&amp;</i> <span data-preview-field="bride_display">Bride</span></strong>
                    <small data-preview-field="event_date">Event Date</small>
                </article>

                <article class="live-product live-product--banting" data-live-product-theme>
                    <span class="live-product-label">Bunting</span>
                    <p data-preview-field="card_title">Wedding Reception</p>
                    <strong><span data-preview-field="groom_display">Groom</span><i>&amp;</i><span data-preview-field="bride_display">Bride</span></strong>
                    <small><span data-preview-field="day_name">DAY</span> · <span data-preview-field="event_date">Event Date</span></small>
                </article>

                <article class="live-product live-product--arrow live-product--arrow-left" data-live-product-theme>
                    <span class="live-product-label">Left Arrow Poster</span>
                    <p>Wedding Invitation</p>
                    <strong><span data-preview-field="groom_display">Groom</span> <i>&amp;</i> <span data-preview-field="bride_display">Bride</span></strong>
                    <svg class="arrow-shape" viewBox="0 0 600 250" aria-hidden="true" focusable="false">
                        <path d="M22 125 260 20v52h315v106H260v52Z" fill="#363636" stroke="#fff" stroke-width="15" stroke-linejoin="round" />
                    </svg>
                </article>

                <article class="live-product live-product--arrow live-product--arrow-right" data-live-product-theme>
                    <span class="live-product-label">Right Arrow Poster</span>
                    <p>Wedding Invitation</p>
                    <strong><span data-preview-field="groom_display">Groom</span> <i>&amp;</i> <span data-preview-field="bride_display">Bride</span></strong>
                    <svg class="arrow-shape" viewBox="0 0 600 250" aria-hidden="true" focusable="false">
                        <path d="M22 125 260 20v52h315v106H260v52Z" fill="#363636" stroke="#fff" stroke-width="15" stroke-linejoin="round" />
                    </svg>
                </article>

                @if ($order->package_type === 'PUTERI_MANIS')
                    <article class="live-product live-product--hanger">
                        <span class="live-product-label">Wooden Hanger</span>
                        <svg
                            class="hanger-illustration"
                            viewBox="0 0 720 390"
                            role="img"
                            aria-labelledby="hanger-preview-title-{{ strtolower($packageSide->side) }}"
                        >
                            <title id="hanger-preview-title-{{ strtolower($packageSide->side) }}">Wooden wedding hanger preview</title>
                            <defs>
                                <linearGradient id="hanger-wood-{{ strtolower($packageSide->side) }}" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#f1d39a" />
                                    <stop offset=".48" stop-color="#c89652" />
                                    <stop offset="1" stop-color="#a96f35" />
                                </linearGradient>
                                <clipPath id="hanger-body-{{ strtolower($packageSide->side) }}">
                                    <path d="M286 114 Q360 76 434 114 L650 250 Q673 264 662 291 Q649 318 620 304 L360 185 L100 304 Q71 318 58 291 Q47 264 70 250 Z" />
                                </clipPath>
                            </defs>

                            <path class="hanger-hook" d="M360 107 V63 C360 37 382 24 401 31 C425 40 424 67 408 82 C400 90 390 94 380 96" />
                            <path class="hanger-hook-inner" d="M360 104 V64 C360 42 380 32 397 38 C414 45 415 63 405 75" />
                            <path class="hanger-body" d="M286 114 Q360 76 434 114 L650 250 Q673 264 662 291 Q649 318 620 304 L360 185 L100 304 Q71 318 58 291 Q47 264 70 250 Z" fill="url(#hanger-wood-{{ strtolower($packageSide->side) }})" />
                            <g class="hanger-grain" clip-path="url(#hanger-body-{{ strtolower($packageSide->side) }})" aria-hidden="true">
                                <path d="M74 269 Q210 208 345 112" />
                                <path d="M95 296 Q224 237 349 143" />
                                <path d="M646 269 Q510 208 375 112" />
                                <path d="M625 296 Q496 237 371 143" />
                            </g>
                            <path class="hanger-crossbar" d="M91 308 Q360 298 629 308" />

                            <g class="hanger-engraving" aria-hidden="true">
                                <path class="hanger-flourish" d="M299 131 Q319 117 339 128 Q350 134 360 126 Q370 134 381 128 Q401 117 421 131 M309 129 Q313 120 320 123 M334 126 Q331 117 325 118 M386 126 Q389 117 395 118 M411 129 Q407 120 400 123" />
                                <circle cx="360" cy="124" r="2.5" />
                            </g>
                            <text class="hanger-couple-names" x="360" y="157" text-anchor="middle">
                                <tspan data-preview-field="groom_display">Groom</tspan>
                                <tspan class="hanger-name-ampersand"> &amp; </tspan>
                                <tspan data-preview-field="bride_display">Bride</tspan>
                            </text>
                            <text class="hanger-event-date" x="360" y="180" text-anchor="middle" data-preview-field="event_date">Event Date</text>
                        </svg>
                    </article>
                @endif

                <article class="live-product live-product--sticker" data-live-product-theme>
                    <span class="live-product-label">Sticker</span>
                    <p>Thank You</p>
                    <strong><span data-preview-field="groom_display">Groom</span> <i>&amp;</i> <span data-preview-field="bride_display">Bride</span></strong>
                    <small data-preview-field="event_date">Event Date</small>
                </article>
            </div>
        </section>
    @endforeach

    <p class="preview-note">This live preview is a layout guide. Final printed results may vary slightly depending on the selected design.</p>
    </div>
</section>
