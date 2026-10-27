<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>KKK OS - {{ $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/customer-dashboard.css') }}?v={{ filemtime(public_path('css/customer-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-order-dashboard">
<main>
<header class="order-summary">
    <div class="order-summary-heading">
        <div>
            <p class="eyebrow">KING KAD KAHWIN</p>
            <h1>{{ __('ui.customer_order') }}</h1>
        </div>

        <div class="order-summary-actions">
            <a href="{{ route('public.orders.progress', ['order_id' => $order->order_id]) }}" class="back-home-button">
                <span aria-hidden="true">&larr;</span>
                {{ __('ui.back') }}
            </a>

            <a href="{{ route('home') }}" class="back-home-button">
                <span aria-hidden="true">&larr;</span>
                {{ __('ui.back_to_home') }}
            </a>

            <span class="status-badge">
                {{ $progress['label'] }}
            </span>
        </div>
    </div>

    <div class="order-summary-grid">
        <div>
            <span class="summary-label">Order ID</span>
            <strong>{{ $order->order_id }}</strong>
        </div>

        <div>
            <span class="summary-label">{{ __('ui.package') }}</span>
            <strong>
                {{ $order->package_count }}
                {{ $order->package_count === 1 ? 'Package' : 'Packages' }}
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

    @if ($order->relationLoaded('progressUpdates') && $order->progressUpdates->isNotEmpty())
        <section class="customer-progress-updates" aria-labelledby="customer-progress-updates-title">
            <div class="customer-progress-updates-heading">
                <span>Latest updates</span>
                <h2 id="customer-progress-updates-title">Your Order Progress</h2>
            </div>
            <ol>
                @foreach ($order->progressUpdates as $update)
                    <li>
                        <div>
                            <strong>{{ match ($update->uploader_role) { 'DESIGNER' => 'Designer', 'PRODUCTION' => 'Production', 'OPERATION_MANAGEMENT' => 'OM', default => 'KKK Team' } }}</strong>
                            <time datetime="{{ $update->published_at->toIso8601String() }}">{{ $update->published_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</time>
                        </div>
                        <p>{{ $update->message ?: 'A new progress attachment has been uploaded.' }}</p>
                        @if ($update->hasAttachment())
                            <a href="{{ route('orders.progress-updates.attachment', ['orderId' => $order->order_id, 'progressUpdate' => $update]) }}" target="_blank" rel="noopener">View progress attachment</a>
                        @endif
                    </li>
                @endforeach
            </ol>
        @else
            <p class="customer-progress-updates-empty">Belum ada progress atau proofing yang dikongsi untuk tempahan ini.</p>
        @endif
    </section>
    @if ($order->fulfilmentJob?->method === 'COURIER' && $order->fulfilmentJob?->tracking_number)
        <div class="customer-tracking-card"><span>{{ __('ui.shipping_information') }}</span><strong>{{ $order->fulfilmentJob->courier_provider ?: 'Courier' }}</strong><code>{{ $order->fulfilmentJob->tracking_number }}</code></div>
    @endif
</header>

@if ($order->customer_reference || $order->invoice)
    <section class="deposit-status-card" aria-label="Financial summary">
        <div><span>Customer Reference</span><strong>{{ $order->customer_reference ?: '-' }}</strong></div>
        @if ($order->invoice)
            <div><span>Invoice Total</span><strong>RM {{ number_format((float) $order->invoice->total_amount, 2) }}</strong></div>
            <div><span>Amount Paid</span><strong>RM {{ number_format((float) $order->invoice->amount_paid, 2) }}</strong></div>
            <div><span>Balance Due</span><strong>RM {{ number_format((float) $order->invoice->balance_due, 2) }}</strong></div>
        @endif
        @if ($order->payments->where('status', 'PAID')->isNotEmpty())
            <h2>Payment History</h2>
            <ul>
                @foreach ($order->payments->where('status', 'PAID')->sortByDesc('paid_at') as $payment)
                    <li>{{ $payment->payment_type === 'BOOKING_DEPOSIT' ? 'Booking deposit' : 'Remaining balance' }} — RM {{ number_format((float) $payment->amount, 2) }} — {{ $payment->paid_at?->format('d/m/Y H:i') }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

@php
    $confirmedPreview = data_get($order->confirmation?->confirmed_snapshot, 'live_preview');
@endphp
@if (is_array($confirmedPreview))
    <section class="deposit-status-card" aria-label="Live preview history">
        <h2>Confirmed Live Preview</h2>
        <p>A customer reference record from final confirmation. This is not print-ready artwork or a source file.</p>
        <ul>
            @foreach (($confirmedPreview['package_sides'] ?? []) as $previewSide)
                <li>{{ $previewSide['side'] ?? '-' }} — {{ data_get($previewSide, 'design.design_code', '-') }} — {{ data_get($previewSide, 'design.theme', '-') }}</li>
            @endforeach
        </ul>
    </section>
@endif

@php
    $customerDesignProofJobs = $order->designJobs->filter(function ($job) {
        $artwork = $job->artworkVersions->sortByDesc('version_number')->first();
        $previewFiles = $artwork?->preview_files ?: array_filter([
            $artwork?->preview_storage_path ? ['path' => $artwork->preview_storage_path] : null,
        ]);

        return in_array($job->status, ['DESIGN_READY', 'CORRECTION_REQUESTED', 'DESIGN_APPROVED'], true)
            && collect($previewFiles)->contains(
                fn ($file) => filled(data_get($file, 'watermarked_path')) || filled(data_get($file, 'path'))
            );
    });
    $hasCustomerCourierProof = $order->fulfilmentJob?->method === 'COURIER'
        && $order->fulfilmentJob?->status === 'COMPLETED'
        && filled($order->packingJob?->proof_storage_path);
@endphp
@if ($customerDesignProofJobs->isNotEmpty() || $hasCustomerCourierProof)
    <section class="customer-proof-panel" aria-labelledby="customer-proof-title">
        <div>
            <span>Progress proof</span>
            <h2 id="customer-proof-title">View Order Proof</h2>
        </div>
        <div class="customer-proof-actions">
            @foreach ($customerDesignProofJobs as $designJob)
                <a href="{{ route('orders.artwork.review', ['orderId' => $order->order_id]).'#design-job-'.$designJob->id }}">
                    View Designer Proof · {{ $designJob->side === 'LELAKI' ? 'Groom' : 'Bride' }}
                </a>
            @endforeach
            @if ($hasCustomerCourierProof)
                <a href="{{ route('orders.courier-proof.show', ['orderId' => $order->order_id]) }}" target="_blank" rel="noopener">
                    View Courier Proof
                </a>
            @endif
        </div>
    </section>
@endif

@php($depositPayment = $order->payments->firstWhere('payment_type', 'BOOKING_DEPOSIT'))
@if ($depositPayment)
    <section class="deposit-status-card" data-deposit-status="{{ strtolower($depositPayment->status) }}">
        <div><span>{{ __('ui.deposit_status') }}</span><strong>{{ match ($depositPayment->status) { 'PAID' => __('ui.deposit_confirmed'), 'FAILED' => __('ui.receipt_rejected'), default => __('ui.pending_review') } }}</strong></div>
        @if (session('deposit_status'))<p class="deposit-success">{{ session('deposit_status') }}</p>@endif
        @if ($depositPayment->status === 'PENDING')<p>Your deposit receipt has been received and is being reviewed by Operations Management.</p>@endif
        @if ($depositPayment->status === 'PAID')<p>Your deposit payment has been confirmed. Your order can proceed to design.</p>@endif
        @if ($depositPayment->status === 'FAILED')
            <p><strong>Rejection reason:</strong> {{ $depositPayment->metadata['rejection_reason'] ?? 'The receipt could not be verified.' }}</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('orders.deposit-receipt.update', ['orderId' => $order->order_id]) }}" data-deposit-resubmission-form>
                @csrf
                <label for="replacement-deposit-receipt">{{ __('ui.upload_new_receipt') }}</label>
                <input id="replacement-deposit-receipt" type="file" name="deposit_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required>
                <button type="submit" data-deposit-resubmission-button>{{ __('ui.resubmit_receipt') }}</button>
            </form>
        @endif
    </section>
@endif

@if (session('draft_saved'))
        <div class="notice customer-feedback customer-feedback-success" role="status">
            <strong>Your information has been saved.</strong>
            <span>You can leave now and return using the same dashboard link.</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="errors customer-feedback customer-feedback-error" role="alert">
            <strong>We need a few more details.</strong>
            <span>Please review the fields below and try again.</span>
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
    @php($hasSelectableSinglePackageSide = (int) $order->package_count === 1 && $orderedPackageSides->isEmpty())
    @php($singlePackageSide = old('single_package_side') === 'PEREMPUAN' ? 'PEREMPUAN' : 'LELAKI')
    @php($formPackageSides = $hasSelectableSinglePackageSide ? [$singlePackageSide] : $orderedPackageSides->pluck('side')->all())

    @if (! $isEditable)
        <div class="notice readonly-notice">
            <strong>Order details have been locked.</strong>
            <div>
                Confirmed information is read-only and cannot be changed.
            </div>
        </div>
    @endif

    <section class="customer-view-switcher" aria-label="Choose view">
        <button type="button" class="customer-view-switcher-button is-active" data-customer-view-button="form" aria-pressed="true">
            Order Form
        </button>
        <button type="button" class="customer-view-switcher-button" data-customer-view-button="preview" aria-pressed="false">
            Live Card Preview
        </button>
    </section>

    <section data-customer-view-panel="form">
        <form
            id="customer-order-form"
            method="POST"
            action="{{ route('orders.draft.update', ['orderId' => $order->order_id]) }}"
            enctype="multipart/form-data"
        >
            @csrf

            <div class="order-form-layout">
                <div class="order-form-fields">

        <fieldset class="customer-data-lock" @disabled(! $isEditable)>

        @if ($hasSelectableSinglePackageSide)
            <fieldset class="single-package-side-selection">
                <legend>Wedding Card Package</legend>
                <label for="single-package-side">Which side is this package for?</label>
                <select id="single-package-side" name="single_package_side" data-single-package-side-selector data-selected-side="{{ $singlePackageSide }}">
                    <option value="LELAKI" @selected($singlePackageSide === 'LELAKI')>Groom's Side</option>
                    <option value="PEREMPUAN" @selected($singlePackageSide === 'PEREMPUAN')>Bride's Side</option>
                </select>
                <p class="field-help">Choose the side so we can show the complete card, event and delivery details form.</p>
            </fieldset>
        @endif

        @if ((int) $order->package_count === 2)
            <fieldset class="two-package-settings">
                <legend>Two-Package Settings</legend>
                <p class="field-help">Each event has its own details and design choices.</p>
                <div class="grid">
                    <div>
                        <label for="package-format">Package Type</label>
                        <select id="package-format" name="package_format">
                            <option value="SEPARATE" @selected(old('package_format', $order->package_format) === 'SEPARATE')>Separate Packages</option>
                            <option value="FOLDED" @selected(old('package_format', $order->package_format) === 'FOLDED')>Combined Package – Folded Card</option>
                        </select>
                    </div>
                    <div>
                        <label for="first-event-side">First Event</label>
                        <select id="first-event-side" name="first_event_side">
                            <option value="LELAKI" @selected($firstEventSide === 'LELAKI')>Groom's Side</option>
                            <option value="PEREMPUAN" @selected($firstEventSide === 'PEREMPUAN')>Bride's Side</option>
                        </select>
                    </div>
                    <div>
                        <label for="jumlah-hantaran">Gift Amount</label>
                        <input id="jumlah-hantaran" name="jumlah_hantaran" type="text" maxlength="255" value="{{ old('jumlah_hantaran', $order->jumlah_hantaran) }}" placeholder="Example: RM 10,000.00 or 10 gold dinars">
                        <p class="field-help">Enter this before confirming your order for a complimentary ceremonial cheque mockup.</p>
                    </div>
                </div>
            </fieldset>
        @endif



        <fieldset>
            <legend>Couple Details</legend>
            <div class="grid">
                <div><label>Groom's Full Name</label><input name="couple[groom_name]" placeholder="Example: Muhammad Syafiq Bin Rahim" value="{{ old('couple.groom_name', $couple?->groom_name) }}"></div>
                <div><label>Groom's Short Name</label><input name="couple[groom_abbreviation]" placeholder="Example: Syafiq" value="{{ old('couple.groom_abbreviation', $couple?->groom_abbreviation) }}"></div>
                <div><label>Bride's Full Name</label><input name="couple[bride_name]" placeholder="Example: Nur Awanis Binti Azman" value="{{ old('couple.bride_name', $couple?->bride_name) }}"></div>
                <div><label>Bride's Short Name</label><input name="couple[bride_abbreviation]" placeholder="Example: Awanis" value="{{ old('couple.bride_abbreviation', $couple?->bride_abbreviation) }}"></div>
            </div>
        </fieldset>
        @foreach ($formPackageSides as $side)
            @php($packageSide = $order->packageSides->firstWhere('side', $side))
            @php($event = $packageSide?->event)
            @php($hideFoldedDesign = $order->package_count === 2 && $order->package_format === 'FOLDED' && ! $loop->first)
            <fieldset data-package-side="{{ $side }}">
                <legend data-package-legend>{{ $hasSelectableSinglePackageSide ? 'Wedding Card Details' : ($order->package_count === 2 ? 'Event '.$loop->iteration.' – ' : 'Package – ').($side === 'LELAKI' ? "Groom's Side" : "Bride's Side") }}</legend>
                @php($selectedQuantity = old("sides.$side.card_quantity", $packageSide?->card_quantity))

                <div>
                    <label for="card_quantity_{{ strtolower($side) }}" data-package-quantity-label>
                        {{ $hasSelectableSinglePackageSide ? 'Card Quantity' : 'Card Quantity — '.($side === 'LELAKI' ? "Groom's Side" : "Bride's Side") }}
                    </label>

                    <select
                        id="card_quantity_{{ strtolower($side) }}"
                        name="sides[{{ $side }}][card_quantity]"
                        data-card-quantity-side="{{ $side }}"
                    >
                        <option value="">-- Select quantity --</option>
                        @foreach ($quantityOptions as $quantity)
                            <option
                                value="{{ $quantity }}"
                                @selected((string) $selectedQuantity === (string) $quantity)
                            >
                                {{ $quantity }} cards
                            </option>
                        @endforeach
                    </select>

                    @error("sides.$side.card_quantity")
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
                <div data-folded-second-design="{{ ! $loop->first ? 'true' : 'false' }}" @if ($hideFoldedDesign) hidden @endif>
                <h3>Design</h3>

<div class="grid">
    <div>
        <label>Theme</label>
        @php($selectedTheme = old("sides.$side.design.theme", $packageSide?->design?->theme))

<select name="sides[{{ $side }}][design][theme]">
    <option value="">-- Select theme --</option>

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
        <label>Design Code</label>
        <input
            name="sides[{{ $side }}][design][design_code]"
            placeholder="Example: KKK-001"
            value="{{ old("sides.$side.design.design_code", $packageSide?->design?->design_code) }}"
        >
    </div>

@php($selectedCardTitle = old("sides.$side.design.card_title", $packageSide?->design?->card_title))

<div>
    <label>Event Title</label>

    <select name="sides[{{ $side }}][design][card_title]">
        <option value="">-- Select event title --</option>
        <option value="Walimatul Urus" @selected($selectedCardTitle === 'Walimatul Urus')>Wedding Reception</option>
        <option value="Majlis Perkahwinan" @selected($selectedCardTitle === 'Majlis Perkahwinan')>Wedding Ceremony</option>
        <option value="Kenduri Kesyukuran" @selected($selectedCardTitle === 'Kenduri Kesyukuran')>Thanksgiving Celebration</option>
    </select>
</div>
</div>

<div class="image-upload-field" data-card-image-upload @if (strtoupper((string) $selectedTheme) !== 'PORTRAIT') hidden @endif>
    <label for="card-image-{{ strtolower($side) }}">
        Couple Photo
    </label>

    <p class="field-help">
        For the PORTRAIT theme only. Use a clear portrait-oriented photo, preferably with a 3:4 ratio.
        Landscape or square images will be cropped to 900 × 1200 px (3:4).
        JPG, JPEG, PNG or WEBP format. Maximum 10 MB.
    </p>

    @if ($packageSide?->design?->card_image_path)
        <p class="upload-status">
            Photo uploaded.
            Choose a new file below only if you want to replace it.
        </p>
    @endif

    <div class="card-image-picker">
    <input
        id="card-image-{{ strtolower($side) }}"
        type="file"
        name="sides[{{ $side }}][design][card_image]"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
    >
    <button type="button" class="cancel-card-image" hidden aria-controls="card-image-{{ strtolower($side) }}">Cancel</button>
    </div>

    @error("sides.$side.design.card_image")
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
                </div>
                <section class="additional-products" aria-labelledby="included-design-items-{{ strtolower($side) }}">
                    <h3 id="included-design-items-{{ strtolower($side) }}">Included in the Card Package</h3>
                    <p class="field-help">Each wedding card package includes banner and bunting previews. The designer will use the short names and selected card design theme. Couple photos are used only for the PORTRAIT theme.</p>
                </section>
                <h3>{{ $hasSelectableSinglePackageSide ? 'Parents' : ($side === 'LELAKI' ? "Groom's" : "Bride's').' Parents' }}</h3>
                <p class="field-help">Enter the names of the parents hosting this event.</p>
                <div class="grid">
                    <div><label>Father's Name</label><input name="sides[{{ $side }}][parents][father_name]" placeholder="Example: Mr Rahim Bin Abdullah" value="{{ old("sides.$side.parents.father_name", $packageSide?->parents?->father_name) }}"></div>
                    <div><label>Mother's Name</label><input name="sides[{{ $side }}][parents][mother_name]" placeholder="Example: Mrs Aminah Binti Ismail" value="{{ old("sides.$side.parents.mother_name", $packageSide?->parents?->mother_name) }}"></div>
                </div>
                <h3>Event</h3>
                <div class="grid">
                    @php($selectedDay = old("sides.$side.event.day_name", $event?->day_name))

<div>
    <label>Day</label>

    <select name="sides[{{ $side }}][event][day_name]" data-event-day>
        <option value="">-- Select day --</option>

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
                {{ ['Ahad' => 'Sunday', 'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday'][$day] ?? $day }}
            </option>
        @endforeach
    </select>
</div>
                    <div>
                        <label>Date</label>
                        <input type="date" name="sides[{{ $side }}][event][event_date]" value="{{ old("sides.$side.event.event_date", $event?->event_date?->format('Y-m-d')) }}" data-event-date>
                        <p class="date-picker-help" data-event-date-help>Select a day first to show only matching dates.</p>
                    </div>
                    <div>
                        <label>Hijri Date</label>
                        <input name="sides[{{ $side }}][event][hijri_date]" placeholder="Example: 4 Zulhijjah 1446H" value="{{ old("sides.$side.event.hijri_date", $event?->hijri_date) }}" data-hijri-date>
                        <p class="date-picker-help" data-hijri-date-help>Select a day first to filter matching Hijri dates.</p>
                    </div>
                    <div><label>Meal Time</label><input type="time" name="sides[{{ $side }}][event][meal_time]" value="{{ old("sides.$side.event.meal_time", $event?->meal_time ? substr($event->meal_time, 0, 5) : '') }}"></div>
                    <div><label>Wedding Reception Time</label><input type="time" name="sides[{{ $side }}][event][bersanding_time]" value="{{ old("sides.$side.event.bersanding_time", $event?->bersanding_time ? substr($event->bersanding_time, 0, 5) : '') }}"></div>
                    <div><label>Venue Name</label><input name="sides[{{ $side }}][event][venue_name]" placeholder="Example: Seri Impian Hall" value="{{ old("sides.$side.event.venue_name", $event?->venue_name) }}"></div>
                </div>
                <label>Full Address</label>
                <textarea rows="4" name="sides[{{ $side }}][event][full_address]" placeholder="Example: No. 87, Laluan Taman Meru 8, Taman Meru 2B, 30020 Ipoh, Perak">{{ old("sides.$side.event.full_address", $event?->full_address) }}</textarea>
                <label>Google Maps Link</label>
                <input type="url" name="sides[{{ $side }}][event][google_maps_url]" placeholder="Example: https://maps.app.goo.gl/..." value="{{ old("sides.$side.event.google_maps_url", $event?->google_maps_url) }}">
                <h3>Contact Persons</h3>

@for ($contactNumber = 1; $contactNumber <= 3; $contactNumber++)
    @php($contact = $event?->contacts?->firstWhere('contact_number', $contactNumber))

    <div class="grid">
        <div>
            <label>Contact {{ $contactNumber }} - Name</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_name]"
                placeholder="Example: Ahmad"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_name", $contact?->contact_name) }}"
            >
        </div>

        <div>
            <label>Contact {{ $contactNumber }} - Phone</label>
            <input
                name="sides[{{ $side }}][event][contacts][{{ $contactNumber }}][contact_phone]"
                type="tel"
                pattern="01[0-9]{8,9}"
                inputmode="numeric"
                minlength="10"
                maxlength="11"
                title="Enter a 10- or 11-digit number starting with 01."
                placeholder="Example: 0123456789"
                value="{{ old("sides.$side.event.contacts.$contactNumber.contact_phone", $contact?->contact_phone) }}"
            >
        </div>
    </div>
@endfor
            </fieldset>
        @endforeach

        <fieldset>
    <legend>Delivery / Pickup</legend>

    @php($fulfilmentMethod = old('fulfilment.method', $order->fulfilment?->method))

    <p class="field-help">
        Choose how you would like to receive your completed order.
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
                <strong>Post / Courier</strong><br>
                Your order will be delivered to the address provided.
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
                Collect your order at KKK.
            </span>
        </label>
    </div>

    <div
    id="courier-details"
    class="courier-details"
    @if ($fulfilmentMethod !== 'COURIER') hidden @endif
>
    <h3>Courier Delivery Details</h3>

    <div class="grid">
        <div>
            <label>Recipient Name</label>
            <input
                id="courier-recipient-name"
                name="fulfilment[recipient_name]"
                placeholder="Example: Muhammad Syafiq Bin Rahim"
                value="{{ old('fulfilment.recipient_name', $order->fulfilment?->recipient_name) }}"
            >
        </div>

        <div>
            <label>Recipient Phone</label>
            <input
                id="courier-recipient-phone"
                name="fulfilment[recipient_phone]"
                type="tel"
                pattern="01[0-9]{8,9}"
                inputmode="numeric"
                minlength="10"
                maxlength="11"
                title="Enter a 10- or 11-digit number starting with 01."
                placeholder="Example: 0123456789"
                value="{{ old('fulfilment.recipient_phone', $order->fulfilment?->recipient_phone) }}"
            >
        </div>
    </div>

    <label>Delivery Address</label>
    <textarea
        id="courier-shipping-address"
        rows="4"
        name="fulfilment[shipping_address]"
        placeholder="Example: No. 12, Jalan Melur 3, Taman Melur, 43000 Kajang, Selangor"
    >{{ old('fulfilment.shipping_address', $order->fulfilment?->shipping_address) }}</textarea>
</div>
</fieldset>

    </fieldset>
            </div>
        </div>

@if ($isEditable)
    <div class="form-actions">
        <button type="submit">Save Draft</button>

        <span id="autosave-status" class="autosave-status" data-state="saved" role="status" aria-live="polite">
            <span class="autosave-indicator" aria-hidden="true"></span>
            <span id="autosave-message">All changes saved</span>
        </span>

        <a
            id="review-order-link"
            href="{{ route('orders.review.show', ['orderId' => $order->order_id]) }}"
        >
            Review Details & Continue
        </a>
    </div>
@endif

        </form>
    </section>

    <section data-customer-view-panel="preview" hidden>
        @include('orders.partials.card-preview')
    </section>
</main>
<script type="application/json" id="valid-package-quantity-pairs">@json($validQuantityPairs)</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('customer-order-form');
    const pairsElement = document.getElementById('valid-package-quantity-pairs');

    if (!form || !pairsElement) {
        return;
    }

    const singlePackageSideSelector = form.querySelector('[data-single-package-side-selector]');

    if (singlePackageSideSelector) {
        singlePackageSideSelector.addEventListener('change', function () {
            const previousSide = singlePackageSideSelector.dataset.selectedSide;
            const selectedSide = singlePackageSideSelector.value;

            if (previousSide === selectedSide) {
                return;
            }

            form.querySelectorAll('[name^="sides[' + previousSide + ']"]').forEach(function (field) {
                field.name = field.name.replace('sides[' + previousSide + ']', 'sides[' + selectedSide + ']');
            });

            form.querySelectorAll('[data-package-side="' + previousSide + '"]').forEach(function (section) {
                section.dataset.packageSide = selectedSide;
                section.querySelectorAll('[data-card-quantity-side]').forEach(function (quantityField) {
                    quantityField.dataset.cardQuantitySide = selectedSide;
                });
            });

            singlePackageSideSelector.dataset.selectedSide = selectedSide;
        });
    }

    let validPairs;

    try {
        validPairs = JSON.parse(pairsElement.textContent);
    } catch (error) {
        console.error('Invalid quantity option list.', error);
        return;
    }

    const selects = Array.from(
        form.querySelectorAll('[data-card-quantity-side]')
    );

    if (selects.length === 0) {
        return;
    }

    function pairIsValid() {
        return validPairs.some(function (pair) {
            return selects.every(function (select) {
                const side = select.dataset.cardQuantitySide;
                return String(pair[side] ?? '') === select.value;
            });
        });
    }

    function updateOptions(changedSide = null) {
        if (
            changedSide !== null
            && selects.length === 2
            && selects.every(select => select.value !== '')
            && !pairIsValid()
        ) {
            const otherSelect = selects.find(
                select => select.dataset.cardQuantitySide !== changedSide
            );

            if (otherSelect) {
                otherSelect.value = '';
            }
        }

        selects.forEach(function (select) {
            const side = select.dataset.cardQuantitySide;
            const otherSelect = selects.find(
                candidate => candidate !== select
            );

            const allowedValues = new Set(
                validPairs
                    .filter(function (pair) {
                        return !otherSelect
                            || otherSelect.value === ''
                            || String(pair[otherSelect.dataset.cardQuantitySide] ?? '')
                                === otherSelect.value;
                    })
                    .map(pair => String(pair[side] ?? ''))
            );

            Array.from(select.options).forEach(function (option) {
                if (option.value !== '') {
                    option.disabled = !allowedValues.has(option.value);
                }
            });
        });
    }

    selects.forEach(function (select) {
        select.addEventListener('change', function () {
            updateOptions(select.dataset.cardQuantitySide);
        });
    });

    updateOptions();
});
</script>
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
        error.textContent = 'Please enter ' + message + '.';

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
                label: "the groom's name"
            },
            {
                selector: '[name="couple[bride_name]"]',
                label: "the bride's name"
            },
            {
                selector: '[name="fulfilment[method]"]:checked',
                label: 'delivery method',
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
                        error.textContent = 'Please select a delivery method.';

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
                    ? "Groom's Package"
                    : "Bride's Package";

                const sideRequiredFields = [
                    ['design][design_code]', 'design code'],
                    ['parents][father_name]', "father's name"],
                    ['parents][mother_name]', "mother's name"],
                    ['event][event_date]', 'event date'],
                    ['event][meal_time]', 'event / meal time'],
                    ['event][venue_name]', 'venue name'],
                    ['event][full_address]', 'full address'],

                    ['event][contacts][1][contact_name]', 'contact 1 name'],
                    ['event][contacts][1][contact_phone]', 'contact 1 phone'],

                    ['event][contacts][2][contact_name]', 'contact 2 name'],
                    ['event][contacts][2][contact_phone]', 'contact 2 phone'],

                    ['event][contacts][3][contact_name]', 'contact 3 name'],
                    ['event][contacts][3][contact_phone]', 'contact 3 phone']
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
         * Courier fields are required only when the customer selects courier delivery.
         */
        const fulfilmentMethod = document.querySelector(
            '[name="fulfilment[method]"]:checked'
        );

        if (fulfilmentMethod && fulfilmentMethod.value === 'COURIER') {
            [
                ['[name="fulfilment[recipient_name]"]', 'recipient name'],
                ['[name="fulfilment[recipient_phone]"]', 'recipient phone'],
                ['[name="fulfilment[shipping_address]"]', 'delivery address']
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
                        throw new Error(errors || payload.message || 'We could not save these changes.');
                    });
                }

                savedRevision = Math.max(savedRevision, revision);

                if (savedRevision === draftRevision) {
                    setStatus('All changes have been saved', 'saved');
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
                        'Unable to save. ' + (error.message || 'Check your internet connection, then select Save Draft.'),
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
            setStatus('Unsaved changes', 'pending');
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
                reviewLink.textContent = 'Saving details...';

                queueSave(true)
                    .then(function () {
                        window.location.assign(reviewLink.href);
                    })
                    .catch(function () {
                        reviewLink.removeAttribute('aria-disabled');
                        reviewLink.classList.remove('is-loading');
                        reviewLink.textContent = 'Review Details & Continue';
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

        const viewButtons = document.querySelectorAll('[data-customer-view-button]');
        const viewPanels = document.querySelectorAll('[data-customer-view-panel]');

        function showCustomerView(view) {
            viewPanels.forEach(function (panel) {
                panel.hidden = panel.dataset.customerViewPanel !== view;
            });

            viewButtons.forEach(function (button) {
                const active = button.dataset.customerViewButton === view;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                showCustomerView(button.dataset.customerViewButton);

                if (button.dataset.customerViewButton === 'preview') {
                    preview.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
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
                return 'EVENT DATE';
            }

            const date = new Date(raw + 'T00:00:00');
            return new Intl.DateTimeFormat('en-MY', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            }).format(date);
        }

        function jawiCardTitle(title) {
            const translations = {
                'Walimatul Urus': 'Wedding Reception',
                'Majlis Perkahwinan': 'Wedding Ceremony',
                'Kenduri Kesyukuran': 'Thanksgiving Celebration'
            };

            return translations[title] || translations['Walimatul Urus'];
        }

        function syncPreviewScope(scope, side) {
            if (!scope) {
                return;
            }

            const prefix = 'sides[' + side + ']';
            const groomName = value('couple[groom_name]', 'GROOM NAME');
            const brideName = value('couple[bride_name]', 'BRIDE NAME');
            const groomDisplay = value('couple[groom_abbreviation]', groomName);
            const brideDisplay = value('couple[bride_abbreviation]', brideName);

            setText(scope, 'groom_name', groomName);
            setText(scope, 'bride_name', brideName);
            setText(scope, 'groom_display', groomDisplay);
            setText(scope, 'bride_display', brideDisplay);
            const cardTitle = value(prefix + '[design][card_title]', 'Walimatul Urus');
            setText(scope, 'card_title', ({ 'Walimatul Urus': 'Wedding Reception', 'Majlis Perkahwinan': 'Wedding Ceremony', 'Kenduri Kesyukuran': 'Thanksgiving Celebration' })[cardTitle] || cardTitle);
            setText(scope, 'card_title_jawi', jawiCardTitle(cardTitle));
            setText(scope, 'father_name', value(prefix + '[parents][father_name]', "FATHER'S NAME"));
            setText(scope, 'mother_name', value(prefix + '[parents][mother_name]', "MOTHER'S NAME"));
            setText(scope, 'day_name', ({ Ahad: 'Sunday', Isnin: 'Monday', Selasa: 'Tuesday', Rabu: 'Wednesday', Khamis: 'Thursday', Jumaat: 'Friday', Sabtu: 'Saturday' })[value(prefix + '[event][day_name]', '')] || 'DAY');
            setText(scope, 'event_date', formatDate(value(prefix + '[event][event_date]', '')));
            setText(scope, 'hijri_date', value(prefix + '[event][hijri_date]', ''));
            setText(scope, 'meal_time', value(prefix + '[event][meal_time]', '-'));
            setText(scope, 'bersanding_time', value(prefix + '[event][bersanding_time]', '-'));
            setText(scope, 'venue_name', value(prefix + '[event][venue_name]', 'VENUE NAME'));
            setText(scope, 'full_address', value(prefix + '[event][full_address]', 'Full event address'));

            for (let number = 1; number <= 3; number += 1) {
                const contactPrefix = prefix + '[event][contacts][' + number + ']';
                setText(scope, 'contact_' + number + '_name', value(contactPrefix + '[contact_name]', 'Contact ' + number));
                setText(scope, 'contact_' + number + '_phone', value(contactPrefix + '[contact_phone]', '-'));
            }

            const theme = value(prefix + '[design][theme]', 'songket').toLowerCase();
            scope.querySelectorAll('[data-live-product-theme], .wedding-card-front').forEach(function (item) {
                item.setAttribute('data-theme', theme);
            });
        }

        function syncCard(side) {
            preview.querySelectorAll('[data-card-preview="' + side + '"], [data-live-product-preview="' + side + '"]').forEach(function (scope) {
                syncPreviewScope(scope, side);
            });
        }

        function showSelection() {
            preview.querySelectorAll('[data-card-preview]').forEach(function (card) {
                card.classList.toggle('is-hidden', card.dataset.cardPreview !== activeSide);
                card.querySelectorAll('[data-card-face]').forEach(function (face) {
                    face.classList.toggle('is-hidden', face.dataset.cardFace !== activeFace);
                });
            });

            preview.querySelectorAll('[data-live-product-preview]').forEach(function (products) {
                products.classList.toggle('is-hidden', products.dataset.liveProductPreview !== activeSide);
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
                    if (legend) legend.textContent = 'Event ' + (index + 1) + ' – ' + (side === 'LELAKI' ? "Groom's Side" : "Bride's Side");
                }
            });

            if (isFoldedCardPreview) {
                const tabs = preview.querySelector('[data-folded-preview-tabs]');
                const pages = [
                    { side: order[0], face: 'front', label: 'Page 1 · Card Front' },
                    { side: order[0], face: 'back', label: 'Page 2 · Card Back · ' + (order[0] === 'LELAKI' ? 'Groom' : 'Bride') },
                    { side: order[1], face: 'back', label: 'Page 3 · Card Back · ' + (order[1] === 'LELAKI' ? 'Groom' : 'Bride') },
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
                        if (label) label.textContent = 'Event ' + (index + 1) + ' – ' + (side === 'LELAKI' ? 'Groom' : 'Bride');
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
