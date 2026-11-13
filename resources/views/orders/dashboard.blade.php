<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>KKK OS - {{ $order->displayCustomerReference() ?: $order->order_id }}</title>
    <link rel="stylesheet" href="{{ asset('css/customer-dashboard.css') }}?v={{ filemtime(public_path('css/customer-dashboard.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-preview-layout.css') }}?v={{ filemtime(public_path('css/customer-preview-layout.css')) }}">
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
            <a href="{{ route('public.orders.progress', ['order_id' => $order->displayCustomerReference() ?: $order->order_id]) }}" class="back-home-button">
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
            <span class="summary-label">{{ $order->displayCustomerReference() ? 'Customer Reference' : 'Order ID' }}</span>
            <strong>{{ $order->displayCustomerReference() ?: $order->order_id }}</strong>
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
            <p class="customer-progress-updates-empty">No progress updates or proofs have been shared for this order yet.</p>
        @endif
    </section>
    @if ($order->fulfilmentJob?->method === 'COURIER' && $order->fulfilmentJob?->tracking_number)
        <div class="customer-tracking-card"><span>{{ __('ui.shipping_information') }}</span><strong>{{ $order->fulfilmentJob->courier_provider ?: 'Courier' }}</strong><code>{{ $order->fulfilmentJob->tracking_number }}</code></div>
    @endif
</header>

@if ($order->customer_reference || $order->invoice || $order->payments->isNotEmpty())
    @php
        $paidPayments = $order->payments->where('status', 'PAID');
        $bookingPaidAmount = $paidPayments
            ->where('payment_type', 'BOOKING_DEPOSIT')
            ->sum(fn ($payment) => (float) $payment->amount);
        $hasFinalOrderTotal = $order->pricingSnapshot !== null;
    @endphp
    <section class="deposit-status-card" aria-label="Financial summary">
        <div><span>{{ $order->displayCustomerReference() ? 'Customer Reference' : 'Order ID' }}</span><strong>{{ $order->displayCustomerReference() ?: $order->order_id }}</strong></div>
        <div><span>Booking Paid</span><strong>RM {{ number_format($bookingPaidAmount, 2) }}</strong></div>
        @if ($hasFinalOrderTotal)
            <div><span>Order Total</span><strong>RM {{ number_format((float) $order->pricingSnapshot->total_amount, 2) }}</strong></div>
            <div><span>Total Payments</span><strong>RM {{ number_format((float) ($order->invoice?->amount_paid ?? $paidPayments->sum(fn ($payment) => (float) $payment->amount)), 2) }}</strong></div>
            <div><span>Balance Due</span><strong>RM {{ number_format((float) ($order->invoice?->balance_due ?? $order->pricingSnapshot->outstanding_amount), 2) }}</strong></div>
        @else
            <div><span>Order Total</span><strong>Pending / To Be Calculated</strong></div>
            <div><span>Balance</span><strong>Pending / To Be Calculated</strong></div>
        @endif
        @if ($paidPayments->isNotEmpty())
            <h2>Payment History</h2>
            <ul>
                @foreach ($paidPayments->sortByDesc('paid_at') as $payment)
                    <li>{{ $payment->payment_type === 'BOOKING_DEPOSIT' ? 'Booking deposit' : 'Remaining balance' }} — RM {{ number_format((float) $payment->amount, 2) }} — {{ $payment->paid_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</li>
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
    $hasCustomerCourierProof = $order->fulfilmentJob?->method === 'COURIER'
        && $order->fulfilmentJob?->status === 'COMPLETED'
        && filled($order->packingJob?->proof_storage_path);
@endphp
@if ($hasCustomerCourierProof)
    <section class="customer-proof-panel" aria-labelledby="customer-proof-title">
        <div>
            <span>Delivery confirmation</span>
            <h2 id="customer-proof-title">View Courier Proof</h2>
        </div>
        <div class="customer-proof-actions">
            <a href="{{ route('orders.courier-proof.show', ['orderId' => $order->order_id]) }}" target="_blank" rel="noopener">
                Open courier proof
            </a>
        </div>
    </section>
@endif

@php
    $depositPayment = $order->payments->firstWhere('payment_type', 'BOOKING_DEPOSIT');
@endphp
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

    @php
        $couple = $order->couples->firstWhere('couple_number', 1);
        $secondCouple = $order->couples->firstWhere('couple_number', 2);
        $isEditable = $order->status === 'DETAILS_INCOMPLETE';
        $firstEventSide = old('first_event_side', $order->first_event_side ?: 'LELAKI');
        $orderedPackageSides = $order->packageSides->sortBy(fn ($item) => $item->side === $firstEventSide ? 0 : 1)->values();
        $hasSelectableSinglePackageSide = $isEditable && (int) $order->package_count === 1 && $orderedPackageSides->isEmpty();
        $needsSinglePackageSide = $hasSelectableSinglePackageSide;
        $singlePackageSide = old('single_package_side') === 'PEREMPUAN' ? 'PEREMPUAN' : 'LELAKI';
        $selectedSinglePackageSide = in_array(old('single_package_side'), ['LELAKI', 'PEREMPUAN'], true) ? old('single_package_side') : '';
        $formPackageSides = $hasSelectableSinglePackageSide ? [$singlePackageSide] : $orderedPackageSides->pluck('side')->all();
    @endphp
    @php
        $previewPackageSides = $hasSelectableSinglePackageSide
            ? collect([$singlePackageSide, $singlePackageSide === 'LELAKI' ? 'PEREMPUAN' : 'LELAKI'])
                ->map(fn ($side) => (object) ['side' => $side, 'design' => null, 'parents' => null, 'event' => null])
            : $orderedPackageSides;
    @endphp

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
            data-single-package-setup="{{ $needsSinglePackageSide ? 'true' : 'false' }}"
            data-design-code-options="{{ json_encode(config('kingkadkahwin.design_codes', [])) }}"
        >
            @csrf

            <div class="order-form-layout">
                <div class="order-form-fields">

        <fieldset class="customer-data-lock" @disabled(! $isEditable)>

        @if ($hasSelectableSinglePackageSide)
            <fieldset class="single-package-side-selection">
                <legend>Wedding Card Package</legend>
                <label for="single-package-side">Which side is this package for?</label>
                <select id="single-package-side" name="single_package_side" data-single-package-side-selector data-selected-side="{{ $singlePackageSide }}" required>
                    <option value="" @selected($selectedSinglePackageSide === '')>Choose a side</option>
                    <option value="LELAKI" @selected($selectedSinglePackageSide === 'LELAKI')>Groom's Side</option>
                    <option value="PEREMPUAN" @selected($selectedSinglePackageSide === 'PEREMPUAN')>Bride's Side</option>
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
            @php
                $packageSide = $order->packageSides->firstWhere('side', $side);
                $event = $packageSide?->event;
                $hideFoldedDesign = $order->package_count === 2 && $order->package_format === 'FOLDED' && ! $loop->first;
            @endphp
            <fieldset data-package-side="{{ $side }}">
                <legend data-package-legend>{{ $hasSelectableSinglePackageSide ? 'Wedding Card Details' : ($order->package_count === 2 ? 'Event '.$loop->iteration.' – ' : 'Package – ').($side === 'LELAKI' ? "Groom's Side" : "Bride's Side") }}</legend>
                @php
                    $selectedQuantity = old("sides.$side.card_quantity", $packageSide?->card_quantity);
                @endphp

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
        @php
            $selectedTheme = old("sides.$side.design.theme", $packageSide?->design?->theme);
            $selectedDesignCode = old("sides.$side.design.design_code", $packageSide?->design?->design_code);
            $designCodeOptions = config('kingkadkahwin.design_codes', []);
            $selectedDesignCodes = $designCodeOptions[strtoupper((string) $selectedTheme)] ?? [];
            $normalizedSelectedDesignCode = strtoupper((string) $selectedDesignCode);
        @endphp

<select name="sides[{{ $side }}][design][theme]">
    <option value="">-- Select theme --</option>

    @foreach ([
        'AKIKAH',
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
        <label for="design-code-{{ strtolower($side) }}">Design Code</label>
        <select
            id="design-code-{{ strtolower($side) }}"
            name="sides[{{ $side }}][design][design_code]"
            data-design-code-select
            @disabled($selectedTheme === null || $selectedTheme === '')
        >
            <option value="">-- Select design code --</option>
            @foreach ($selectedDesignCodes as $designCode)
                <option value="{{ $designCode }}" @selected($normalizedSelectedDesignCode === $designCode)>
                    {{ $designCode }}
                </option>
            @endforeach
            @if ($selectedDesignCode && ! in_array($normalizedSelectedDesignCode, $selectedDesignCodes, true))
                <option value="{{ $selectedDesignCode }}" selected>
                    {{ $selectedDesignCode }} (current code)
                </option>
            @endif
        </select>
    </div>

@php
    $selectedCardTitle = old("sides.$side.design.card_title", $packageSide?->design?->card_title);
    $normalizedCardTitle = strtoupper(trim((string) $selectedCardTitle));
    $selectedEventTitleChoice = match ($normalizedCardTitle) {
        'WALIMATULURUS', 'WALIMATUL URUS', 'MAJLIS PERKAHWINAN' => 'MAJLIS PERKAHWINAN',
        'KENDURI KESYUKURAN', 'MAJLIS KESYUKURAN' => 'MAJLIS KESYUKURAN',
        default => filled($selectedCardTitle) ? 'MAJLIS PERKAHWINAN' : '',
    };
@endphp

<div data-event-title-controls>
    <label for="event-title-choice-{{ strtolower($side) }}">Event Title</label>
    <select
        id="event-title-choice-{{ strtolower($side) }}"
        name="sides[{{ $side }}][design][card_title_choice]"
        data-event-title-choice
    >
        <option value="">-- Select event title --</option>
        <option value="MAJLIS PERKAHWINAN" @selected($selectedEventTitleChoice === 'MAJLIS PERKAHWINAN')>MAJLIS PERKAHWINAN</option>
        <option value="MAJLIS KESYUKURAN" @selected($selectedEventTitleChoice === 'MAJLIS KESYUKURAN')>MAJLIS KESYUKURAN</option>
    </select>

    <input
        type="hidden"
        name="sides[{{ $side }}][design][card_title]"
        value="{{ $selectedEventTitleChoice }}"
        data-event-title-value
    >
</div>
</div>

<div class="image-upload-field" data-card-image-upload data-has-card-image="{{ filled($packageSide?->design?->card_image_path) ? 'true' : 'false' }}" data-saved-photo-url="{{ route('orders.customer-photo-preview', ['orderId' => $order->order_id, 'side' => $side]) }}">
    <label for="card-image-{{ strtolower($side) }}">
        Couple Photo
    </label>

    <p class="field-help" data-card-image-guidance>
        @if (strtoupper((string) $selectedTheme) === 'PORTRAIT')
            A clear customer photo is required to continue with the PORTRAIT theme. It will be placed into the Portrait design items.
        @else
            Photo upload is optional for this theme. If uploaded, it will be used only for Banner and Bunting. Leave it blank to use the supplied templates.
        @endif
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
                    <p class="field-help">Banner and bunting are included automatically. An uploaded photo is used throughout PORTRAIT design items; for other themes it is used only in Banner and Bunting. Without a photo, the supplied templates are used.</p>
                </section>
                <h3>{{ $hasSelectableSinglePackageSide ? 'Parents' : ($side === 'LELAKI' ? "Groom's Parents" : "Bride's Parents") }}</h3>
                <p class="field-help">Enter the names of the parents hosting this event.</p>
                <div class="grid">
                    <div><label>Father's Name</label><input name="sides[{{ $side }}][parents][father_name]" placeholder="Example: Mr Rahim Bin Abdullah" value="{{ old("sides.$side.parents.father_name", $packageSide?->parents?->father_name) }}"></div>
                    <div><label>Mother's Name</label><input name="sides[{{ $side }}][parents][mother_name]" placeholder="Example: Mrs Aminah Binti Ismail" value="{{ old("sides.$side.parents.mother_name", $packageSide?->parents?->mother_name) }}"></div>
                </div>
                <h3>Event</h3>
                <div class="grid">
                    @php
                        $selectedDay = old("sides.$side.event.day_name", $event?->day_name);
                    @endphp

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
    @php
        $contact = $event?->contacts?->firstWhere('contact_number', $contactNumber);
    @endphp

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

    @php
        $fulfilmentMethod = old('fulfilment.method', $order->fulfilment?->method);
    @endphp

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
        <button type="submit">{{ $needsSinglePackageSide ? 'Continue to event details' : 'Save Draft' }}</button>

        <span id="autosave-status" class="autosave-status" data-state="saved" role="status" aria-live="polite">
            <span class="autosave-indicator" aria-hidden="true"></span>
            <span id="autosave-message">All changes saved</span>
        </span>

        @unless ($needsSinglePackageSide)
            <a
                id="review-order-link"
                href="{{ route('orders.review.show', ['orderId' => $order->order_id]) }}"
            >
                Review Details & Continue
            </a>
        @endunless
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

        document
            .querySelectorAll('fieldset[data-package-side]')
            .forEach(function (fieldset) {
                const side = fieldset.dataset.packageSide;
                const themeField = fieldset.querySelector(
                    '[name="sides[' + side + '][design][theme]"]'
                );

                if ((themeField?.value ?? '').toUpperCase() !== 'PORTRAIT') {
                    return;
                }

                const uploadField = fieldset.querySelector('[data-card-image-upload]');
                const photoInput = uploadField?.querySelector('input[type="file"]');
                const hasSavedPhoto = uploadField?.dataset.hasCardImage === 'true';
                const hasSelectedPhoto = Boolean(photoInput && photoInput.files.length > 0);

                if (!hasSavedPhoto && !hasSelectedPhoto) {
                    const sideLabel = side === 'LELAKI'
                        ? "Groom's Package"
                        : "Bride's Package";

                    addRequiredError(
                        photoInput,
                        'a customer photo for the PORTRAIT design on ' + sideLabel
                    );

                    valid = false;
                }
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
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    if (!response.ok || payload.saved !== true) {
                        const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                        throw new Error(errors || payload.message || 'We could not save these changes.');
                    }

                    if (includeFiles) {
                        form.querySelectorAll('[data-card-image-upload]').forEach(function (uploadField) {
                            const photoInput = uploadField.querySelector('input[type="file"]');

                            if (photoInput && photoInput.files.length > 0) {
                                uploadField.dataset.hasCardImage = 'true';
                            }
                        });

                    }

                    savedRevision = Math.max(savedRevision, revision);

                    if (savedRevision === draftRevision) {
                        setStatus('All changes have been saved', 'saved');
                    }
                });
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
            const singlePackageSideSelector = form.querySelector('[data-single-package-side-selector]');
            if (singlePackageSideSelector && !singlePackageSideSelector.value) {
                setStatus('Choose the package side before saving your details.', 'pending');
                return;
            }

            const includeFiles = event.target instanceof HTMLInputElement && event.target.type === 'file';

            window.clearTimeout(autosaveTimer);
            draftRevision += 1;
            setStatus('Unsaved changes', 'pending');
            autosaveTimer = window.setTimeout(function () {
                queueSave(includeFiles).catch(function () {});
            }, 800);
        }

        form.querySelectorAll('[data-event-title-choice]').forEach(function (choice) {
            const controls = choice.closest('[data-event-title-controls]');
            const titleValue = controls.querySelector('[data-event-title-value]');

            function syncEventTitle() {
                titleValue.value = choice.value;
                titleValue.dispatchEvent(new Event('input', { bubbles: true }));
            }

            choice.addEventListener('change', syncEventTitle);
            syncEventTitle();
        });

        form.addEventListener('input', scheduleAutosave);
        form.addEventListener('change', scheduleAutosave);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(autosaveTimer);

            const submitButton = event.submitter instanceof HTMLButtonElement ? event.submitter : null;

            if (submitButton) {
                submitButton.disabled = true;
            }

            setStatus('Saving draft...', 'saving');

            queueSave(true)
                .then(function () {
                    if (savedRevision === draftRevision) {
                        setStatus('Draft saved', 'saved');
                    }
                })
                .catch(function () {})
                .finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
        });

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

        function formatTime12Hour(raw) {
            const value = String(raw || '').trim();
            const match = value.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);

            if (!match) {
                return value || '-';
            }

            const hour = Number(match[1]);
            const minute = Number(match[2]);

            if (hour > 23 || minute > 59) {
                return value;
            }

            return (hour % 12 || 12) + ':' + match[2] + ' ' + (hour >= 12 ? 'PM' : 'AM');
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

        function displayCardTitle(title) {
            const normalizedTitle = String(title || '').trim().toUpperCase();
            const legacyTranslations = {
                'WALIMATULURUS': 'Wedding Ceremony',
                'WALIMATUL URUS': 'Wedding Ceremony',
                'MAJLIS PERKAHWINAN': 'Wedding Ceremony',
                'KENDURI KESYUKURAN': 'Thanksgiving Celebration',
                'MAJLIS KESYUKURAN': 'Thanksgiving Celebration'
            };

            return legacyTranslations[normalizedTitle] || 'Wedding Ceremony';
        }

        function designCardTitle(title) {
            const normalizedTitle = String(title || '').trim().toUpperCase().replace(/\s+/g, ' ');
            const malayTitles = {
                'WALIMATULURUS': 'MAJLIS PERKAHWINAN',
                'WALIMATUL URUS': 'MAJLIS PERKAHWINAN',
                'MAJLIS PERKAHWINAN': 'MAJLIS PERKAHWINAN',
                'KENDURI KESYUKURAN': 'MAJLIS KESYUKURAN',
                'MAJLIS KESYUKURAN': 'MAJLIS KESYUKURAN'
            };

            return malayTitles[normalizedTitle] || 'MAJLIS PERKAHWINAN';
        }

        function jawiCardTitle(title) {
            return displayCardTitle(title);
        }

        const artworkThemes = new Set(['akikah', 'arch', 'desa', 'garden', 'islamic', 'nostalgia', 'portrait']);
        const artworkBase = @json(asset('images/designs'));
        const artworkDesignCodes = JSON.parse(form.dataset.designCodeOptions || '{}');
        const unavailableArtwork = {
            'CKP-003': ['banner'],
        };

        function syncArtworkImage(image, source, label) {
            image.hidden = !source;
            image.alt = source ? label : '';

            if (source && image.getAttribute('src') !== source) {
                image.setAttribute('src', source);
            } else if (!source) {
                image.removeAttribute('src');
            }
        }

        function syncArtworkPhotoMask(item, source) {
            if (source) {
                item.style.setProperty('--preview-photo-mask', 'url("' + source + '")');
            } else {
                item.style.removeProperty('--preview-photo-mask');
            }
        }

        function syncDesignArtwork(scope, theme, designCode) {
            const normalizedCode = designCode.trim().toUpperCase();
            const themeName = theme.toUpperCase();
            const hasArtwork = artworkThemes.has(theme) && (artworkDesignCodes[themeName] || []).includes(normalizedCode);
            const isCatalogArtwork = hasArtwork && theme !== 'desa';
            const assetBase = hasArtwork
                ? artworkBase + '/' + theme + '/' + normalizedCode + '/'
                : '';
            const front = scope.matches('[data-card-preview]')
                ? scope.querySelector('[data-card-face="front"]')
                : null;
            const back = scope.matches('[data-card-preview]')
                ? scope.querySelector('[data-card-face="back"]')
                : null;

            if (front) {
                const artwork = front.querySelector('[data-desa-card-artwork]');
                front.dataset.designCode = hasArtwork ? normalizedCode : '';
                const nameOverlay = front.querySelector('[data-desa-card-name-overlay]');
                const genericNameCopy = nameOverlay.querySelector('[data-desa-generic-copy]');
                const customNameCopy = nameOverlay.querySelector('[data-desa-card-custom-copy]');
                const genericCopy = front.querySelector('.card-front-copy');
                const ornaments = front.querySelectorAll('.card-ornament');

                nameOverlay.hidden = !hasArtwork;
                genericNameCopy.hidden = hasArtwork;
                customNameCopy.hidden = !hasArtwork;
                genericCopy.hidden = hasArtwork;
                front.toggleAttribute('data-design-artwork', hasArtwork);
                front.toggleAttribute('data-catalog-artwork', isCatalogArtwork);
                ornaments.forEach(function (ornament) {
                    ornament.hidden = hasArtwork;
                });
                syncArtworkImage(artwork, hasArtwork ? assetBase + 'card-front.webp' : '', normalizedCode + ' ' + themeName + ' card front');
                syncArtworkPhotoMask(front, hasArtwork && theme === 'portrait' ? assetBase + 'card-front-photo-mask.webp' : '');
            }

            if (back) {
                const artwork = back.querySelector('[data-desa-card-back-artwork]');
                back.dataset.designCode = hasArtwork ? normalizedCode : '';
                const liveNames = back.querySelector('[data-desa-card-back-live-names]');
                const genericNameCopy = liveNames.querySelector('[data-desa-generic-copy]');
                const customNameCopy = liveNames.querySelector('[data-desa-card-back-custom-copy]');
                const placeholder = back.querySelector('[data-desa-back-placeholder]');

                liveNames.hidden = !hasArtwork;
                genericNameCopy.hidden = hasArtwork;
                customNameCopy.hidden = !hasArtwork;
                placeholder.hidden = hasArtwork;
                back.toggleAttribute('data-design-artwork', hasArtwork);
                back.toggleAttribute('data-catalog-artwork', isCatalogArtwork);
                syncArtworkImage(artwork, hasArtwork ? assetBase + 'card-back.webp' : '', normalizedCode + ' ' + themeName + ' card back');
            }

            scope.querySelectorAll('[data-desa-product]').forEach(function (product) {
                const type = product.dataset.desaProduct;
                const artwork = product.querySelector('[data-desa-product-artwork]');
                const nameOverlay = product.querySelector('[data-desa-product-name-overlay]');
                const genericNameCopy = nameOverlay.querySelector('[data-desa-generic-copy]');
                const productCustomNameCopy = nameOverlay.querySelector('[data-desa-product-custom-copy]');
                const placeholder = product.querySelector('[data-desa-product-placeholder]');
                const unavailableMessage = product.querySelector('[data-artwork-unavailable-message]');
                const isUnavailable = hasArtwork && (unavailableArtwork[normalizedCode] || []).includes(type);
                const isVipCard = type === 'vip-card';
                const shouldShowArtwork = hasArtwork
                    && (!isVipCard || theme === 'portrait')
                    && !isUnavailable;
                product.dataset.designCode = shouldShowArtwork ? normalizedCode : '';
                product.toggleAttribute('data-design-artwork', shouldShowArtwork);
                product.toggleAttribute('data-catalog-artwork', shouldShowArtwork && isCatalogArtwork);
                product.toggleAttribute('data-artwork-unavailable', isUnavailable);
                if (unavailableMessage) {
                    unavailableMessage.hidden = !isUnavailable;
                }

                product.hidden = (type === 'card-back' || isVipCard) && !shouldShowArtwork;
                nameOverlay.hidden = !shouldShowArtwork;
                genericNameCopy.hidden = shouldShowArtwork;
                productCustomNameCopy.hidden = !shouldShowArtwork;
                placeholder.hidden = shouldShowArtwork || isUnavailable;
                syncArtworkImage(artwork, shouldShowArtwork ? assetBase + type + '.webp' : '', normalizedCode + ' ' + themeName + ' ' + type.replaceAll('-', ' ') + ' design');
                const hasPhotoMask = shouldShowArtwork && theme === 'portrait' && type !== 'wooden-hanger' && type !== 'card-back';
                syncArtworkPhotoMask(product, hasPhotoMask ? assetBase + type + '-photo-mask.webp' : '');
            });
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
            const cardTitle = value(prefix + '[design][card_title]', 'MAJLIS PERKAHWINAN');
            setText(scope, 'card_title', displayCardTitle(cardTitle));
            setText(scope, 'card_title_jawi', jawiCardTitle(cardTitle));
            setText(scope, 'card_title_malay', designCardTitle(cardTitle));
            setText(scope, 'father_name', value(prefix + '[parents][father_name]', "FATHER'S NAME"));
            setText(scope, 'mother_name', value(prefix + '[parents][mother_name]', "MOTHER'S NAME"));
            const eventDay = value(prefix + '[event][day_name]', '');
            setText(scope, 'day_name', ({ Ahad: 'Sunday', Isnin: 'Monday', Selasa: 'Tuesday', Rabu: 'Wednesday', Khamis: 'Thursday', Jumaat: 'Friday', Sabtu: 'Saturday' })[eventDay] || 'DAY');
            setText(scope, 'day_name_malay', ({ Ahad: 'AHAD', Isnin: 'ISNIN', Selasa: 'SELASA', Rabu: 'RABU', Khamis: 'KHAMIS', Jumaat: 'JUMAAT', Sabtu: 'SABTU' })[eventDay] || 'HARI');
            const rawEventDate = value(prefix + '[event][event_date]', '');
            const parsedEventDate = rawEventDate ? new Date(rawEventDate + 'T00:00:00') : null;
            setText(scope, 'event_date', formatDate(rawEventDate));
            setText(scope, 'event_date_day', parsedEventDate
                ? new Intl.DateTimeFormat('en-MY', { day: 'numeric' }).format(parsedEventDate)
                : 'DD');
            setText(scope, 'event_date_month_year', parsedEventDate
                ? new Intl.DateTimeFormat('en-MY', { month: 'long', year: 'numeric' }).format(parsedEventDate).toUpperCase()
                : 'BULAN TAHUN');
            setText(scope, 'hijri_date', value(prefix + '[event][hijri_date]', ''));
            setText(scope, 'meal_time', formatTime12Hour(value(prefix + '[event][meal_time]', '')));
            setText(scope, 'bersanding_time', formatTime12Hour(value(prefix + '[event][bersanding_time]', '')));
            setText(scope, 'venue_name', value(prefix + '[event][venue_name]', 'VENUE NAME'));
            setText(scope, 'full_address', value(prefix + '[event][full_address]', 'Full event address'));

            for (let number = 1; number <= 3; number += 1) {
                const contactPrefix = prefix + '[event][contacts][' + number + ']';
                setText(scope, 'contact_' + number + '_name', value(contactPrefix + '[contact_name]', 'Contact ' + number));
                setText(scope, 'contact_' + number + '_phone', value(contactPrefix + '[contact_phone]', '-'));
            }

            const theme = value(prefix + '[design][theme]', 'songket').toLowerCase();
            const designCode = value(prefix + '[design][design_code]', '');
            scope.querySelectorAll('[data-live-product-theme], .wedding-card-front').forEach(function (item) {
                item.setAttribute('data-theme', theme);
            });
            syncDesignArtwork(scope, theme, designCode);
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

        const singlePackageSideSelector = form.querySelector('[data-single-package-side-selector]');
        if (singlePackageSideSelector) {
            const sidePreviewNote = preview.querySelector('[data-single-package-preview-note]');
            const updateSidePreviewNote = function () {
                if (!sidePreviewNote) {
                    return;
                }

                const sideLabel = singlePackageSideSelector.value === 'PEREMPUAN' ? "Bride's side" : "Groom's side";
                sidePreviewNote.textContent = singlePackageSideSelector.value
                    ? 'Previewing ' + sideLabel + '.'
                    : 'Sample layout — choose the package side in the order form.';
            };

            singlePackageSideSelector.addEventListener('change', function () {
                activeSide = singlePackageSideSelector.value || singlePackageSideSelector.dataset.selectedSide;
                updateSidePreviewNote();
                syncCard(activeSide);
                showSelection();
            });

            updateSidePreviewNote();
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
                const photoInput = uploadField.querySelector('input[type="file"]');
                const hasPhoto = uploadField.dataset.hasCardImage === 'true'
                    || Boolean(photoInput && photoInput.files.length > 0);
                const guidance = uploadField.querySelector('[data-card-image-guidance]');

                uploadField.hidden = false;

                if (!guidance) {
                    return;
                }

                if (theme === 'PORTRAIT') {
                    guidance.textContent = hasPhoto
                        ? 'A customer photo is attached for the PORTRAIT design. ' +
                            'JPG, JPEG, PNG or WEBP format. Maximum 10 MB.'
                        : 'Upload a clear customer photo to continue with the PORTRAIT theme. ' +
                            'JPG, JPEG, PNG or WEBP format. Maximum 10 MB.';
                } else {
                    guidance.textContent = hasPhoto
                        ? 'The uploaded photo will be used only for Banner and Bunting. ' +
                            'JPG, JPEG, PNG or WEBP format. Maximum 10 MB.'
                        : 'Photo upload is optional. If uploaded, it will be used only for Banner and Bunting. ' +
                            'Leave it blank to use the supplied templates. JPG, JPEG, PNG or WEBP format. Maximum 10 MB.';
                }
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
            if (
                event.target.name?.endsWith('[design][theme]')
                || event.target.matches?.('[data-card-image-upload] input[type="file"]')
            ) {
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-order-form');

        if (!form) {
            return;
        }

        let designCodeOptions = {};

        try {
            designCodeOptions = JSON.parse(form.dataset.designCodeOptions || '{}');
        } catch (error) {
            return;
        }

        form.querySelectorAll('select[name$="[design][theme]"]').forEach(function (themeSelect) {
            const packageSide = themeSelect.closest('fieldset[data-package-side]');
            const designCodeSelect = packageSide?.querySelector('[data-design-code-select]');

            if (!designCodeSelect) {
                return;
            }

            themeSelect.addEventListener('change', function () {
                const selectedCode = designCodeSelect.value;
                const theme = themeSelect.value.trim().toUpperCase();
                const codes = designCodeOptions[theme] || [];

                designCodeSelect.replaceChildren(new Option('-- Select design code --', ''));
                codes.forEach(function (code) {
                    designCodeSelect.add(new Option(code, code));
                });

                designCodeSelect.disabled = theme === '';

                if (codes.includes(selectedCode)) {
                    designCodeSelect.value = selectedCode;
                }
            });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-order-form');
        const preview = document.querySelector('[data-live-preview]');

        if (!form || !preview) {
            return;
        }

        const watermarkLabel = (preview.dataset.previewWatermark || 'CUSTOMER PREVIEW').trim();

        function protectPreviewSurfaces() {
            preview.querySelectorAll('.wedding-card, .live-product[data-preview-artwork-slot]').forEach(function (surface) {
                if (surface.querySelector('[data-preview-watermark-overlay]')) {
                    return;
                }

                const overlay = document.createElement('div');
                overlay.className = 'live-preview-watermark';
                overlay.setAttribute('data-preview-watermark-overlay', '');
                overlay.setAttribute('aria-hidden', 'true');

                for (let row = 0; row < 4; row += 1) {
                    const line = document.createElement('span');
                    line.className = 'live-preview-watermark-line';
                    line.textContent = 'KINGKADKAHWIN · CUSTOMER PREVIEW · ' + watermarkLabel;
                    overlay.append(line);
                }

                surface.append(overlay);
            });

            preview.querySelectorAll('img').forEach(function (image) {
                image.draggable = false;
            });
        }

        protectPreviewSurfaces();

        preview.addEventListener('contextmenu', function (event) {
            event.preventDefault();
        });

        preview.addEventListener('dragstart', function (event) {
            if (event.target instanceof HTMLImageElement) {
                event.preventDefault();
            }
        });

        preview.addEventListener('selectstart', function (event) {
            event.preventDefault();
        });

        preview.addEventListener('copy', function (event) {
            event.preventDefault();
        });

        document.addEventListener('keydown', function (event) {
            const isPreviewShortcut = (event.ctrlKey || event.metaKey)
                && ['s', 'p'].includes(event.key.toLowerCase());
            const previewHasFocus = preview.contains(document.activeElement);
            const pointerIsOverPreview = preview.matches(':hover');

            if (isPreviewShortcut && (previewHasFocus || pointerIsOverPreview)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        const customerPhotoPreviewUrls = new Map();

        function setImageSource(image, source, shouldShow) {
            if (!shouldShow || !source) {
                image.hidden = true;
                return;
            }

            if (image.getAttribute('src') !== source) {
                image.setAttribute('src', source);
            }

            image.hidden = false;
        }

        function updateSidePhotoPreview(side) {
            const packageSide = form.querySelector('fieldset[data-package-side="' + side + '"]');
            const uploadField = packageSide?.querySelector('[data-card-image-upload]');
            const photoInput = uploadField?.querySelector('input[type="file"]');
            const selectedFile = photoInput?.files?.[0];
            const theme = (packageSide?.querySelector('[name="sides[' + side + '][design][theme]"]')?.value || '').toUpperCase();

            if (!uploadField) {
                return;
            }

            let photoUrl = '';

            if (selectedFile) {
                let current = customerPhotoPreviewUrls.get(side);

                if (!current || current.file !== selectedFile) {
                    if (current) {
                        URL.revokeObjectURL(current.url);
                    }

                    current = {
                        file: selectedFile,
                        url: URL.createObjectURL(selectedFile),
                    };
                    customerPhotoPreviewUrls.set(side, current);
                }

                photoUrl = current.url;
            } else if (uploadField.dataset.hasCardImage === 'true') {
                photoUrl = uploadField.dataset.savedPhotoUrl || '';
            } else {
                const current = customerPhotoPreviewUrls.get(side);

                if (current) {
                    URL.revokeObjectURL(current.url);
                    customerPhotoPreviewUrls.delete(side);
                }
            }

            const cardPreview = preview.querySelector('[data-card-preview="' + side + '"]');
            const portraitImage = cardPreview?.querySelector('[data-live-photo-preview="portrait"]');
            const frontCard = portraitImage?.closest('.wedding-card-front');
            const showPortraitPhoto = Boolean(photoUrl) && theme === 'PORTRAIT';

            if (portraitImage) {
                setImageSource(portraitImage, photoUrl, showPortraitPhoto);
            }

            cardPreview?.querySelectorAll('[data-live-photo-preview="artwork"]').forEach(function (image) {
                setImageSource(image, photoUrl, showPortraitPhoto);
            });

            frontCard?.classList.toggle('has-customer-photo', showPortraitPhoto);

            const productPreview = preview.querySelector('[data-live-product-preview="' + side + '"]');

            productPreview?.querySelectorAll('[data-live-photo-preview="item"]').forEach(function (image) {
                const product = image.closest('.live-product');
                const isBannerOrBunting = product?.classList.contains('live-product--banner')
                    || product?.classList.contains('live-product--banting');
                const showProductPhoto = Boolean(photoUrl) && (theme === 'PORTRAIT' || isBannerOrBunting)
                    && !product?.hasAttribute('data-artwork-unavailable');

                setImageSource(image, photoUrl, showProductPhoto);

                if (product) {
                    product.classList.toggle('has-live-customer-photo', showProductPhoto);
                }
            });

            productPreview?.querySelectorAll('[data-live-photo-preview="artwork"]').forEach(function (image) {
                setImageSource(image, photoUrl, showPortraitPhoto);
            });
        }

        function updateAllPhotoPreviews() {
            form.querySelectorAll('[data-card-image-upload]').forEach(function (uploadField) {
                const side = uploadField.closest('[data-package-side]')?.dataset.packageSide;

                if (side) {
                    updateSidePhotoPreview(side);
                }
            });
        }

        form.addEventListener('change', function (event) {
            if (
                event.target.matches?.('[data-card-image-upload] input[type="file"]')
                || event.target.name?.endsWith('[design][theme]')
            ) {
                const side = event.target.closest('[data-package-side]')?.dataset.packageSide;

                if (side) {
                    updateSidePhotoPreview(side);
                }
            }
        });

        window.addEventListener('pagehide', function () {
            customerPhotoPreviewUrls.forEach(function (photo) {
                URL.revokeObjectURL(photo.url);
            });
            customerPhotoPreviewUrls.clear();
        });

        updateAllPhotoPreviews();
    });
</script>
@include('public.partials.customer-service')
@include('public.partials.theme-toggle')
</body>
</html>
