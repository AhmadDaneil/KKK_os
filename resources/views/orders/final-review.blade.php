<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Final Review - {{ $review['order_id'] }}</title>
    <link rel="stylesheet" href="{{ asset('css/final-review.css') }}?v={{ filemtime(public_path('css/final-review.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-customer-flow">
    @php
        $secondCouple = $review['second_couple'] ?? [];
        $hasSecondCouple = collect($secondCouple)->filter(fn ($value) => filled($value))->isNotEmpty();

        $fulfilmentLabel = match ($review['fulfilment']['method'] ?? null) {
            'COURIER' => 'Post / Courier',
            'PICKUP' => 'Self Pickup at KKK',
            default => $review['fulfilment']['method'] ?? '-',
        };
    @endphp

    <main class="final-review-page">
        <header class="review-header">
            <div class="review-header-top">
                <div>
                    <p class="eyebrow">Review Your Order Details</p>
                    <h1>Final Review</h1>
                </div>

                <span class="order-badge">{{ $review['order_id'] }}</span>
            </div>

            <p class="review-intro">
                Please review all details carefully before final confirmation.
                Confirmed details will be locked from further customer edits.
            </p>
        </header>

        @if ($errors->any())
            <section class="review-card">
                <h2>Some details need your attention</h2>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="review-card">
            <h2>Order Summary</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Order ID</span>
                    <span class="review-value">{{ $review['order_id'] }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Number of Packages</span>
                    <span class="review-value">{{ $review['package_count'] }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Card Quantity</span>
                    <span class="review-value">{{ $review['card_quantity'] ?? '-' }}</span>
                </div>

                @if ($review['package_count'] === 2)
                    <div class="review-item"><span class="review-label">Package Type</span><span class="review-value">{{ $review['package_format'] === 'FOLDED' ? 'Combined Package – Folded Card' : 'Separate Packages' }}</span></div>
                    <div class="review-item"><span class="review-label">First Event</span><span class="review-value">{{ $review['first_event_side'] === 'LELAKI' ? "Groom's Side" : "Bride's Side" }}</span></div>
                    <div class="review-item"><span class="review-label">Gift Amount</span><span class="review-value">{{ $review['jumlah_hantaran'] ?: '-' }}</span></div>
                @endif
            </div>
        </section>

        <section class="review-card">
            <h2>Couple</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Groom's Full Name</span>
                    <span class="review-value">{{ $review['couple']['groom_name'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Groom's Short Name</span>
                    <span class="review-value">{{ $review['couple']['groom_abbreviation'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Bride's Full Name</span>
                    <span class="review-value">{{ $review['couple']['bride_name'] ?: '-' }}</span>
                </div>

                <div class="review-item">
                    <span class="review-label">Bride's Short Name</span>
                    <span class="review-value">{{ $review['couple']['bride_abbreviation'] ?: '-' }}</span>
                </div>
            </div>
        </section>

        @if ($hasSecondCouple)
            <section class="review-card">
                <h2>Second Couple</h2>
                <p class="review-intro">
                    These details are for the designer's reference and are not used in Photoshop Auto Merge V1.
                </p>

                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Groom's Full Name</span>
                        <span class="review-value">{{ $secondCouple['groom_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Groom's Short Name</span>
                        <span class="review-value">{{ $secondCouple['groom_abbreviation'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Bride's Full Name</span>
                        <span class="review-value">{{ $secondCouple['bride_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Bride's Short Name</span>
                        <span class="review-value">{{ $secondCouple['bride_abbreviation'] ?: '-' }}</span>
                    </div>
                </div>
            </section>
        @endif

            @foreach ($review['package_sides'] as $side)
            @php
                $sideLabel = $side['side'] === 'LELAKI' ? "Groom's Side" : "Bride's Side";
                $event = $side['event'];
                $design = $side['design'];
                $parents = $side['parents'];
            @endphp

            <section class="review-card package-card">
                <div class="package-heading">
                    <h2>{{ $review['package_count'] === 2 ? 'Event '.$loop->iteration.' – ' : 'Package – ' }}{{ $sideLabel }}</h2>
                    <span class="side-badge">{{ $side['side'] === 'LELAKI' ? 'Groom' : 'Bride' }}</span>
                </div>

                <h3>Card Design</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Theme</span>
                        <span class="review-value">{{ $design['theme'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Design Code</span>
                        <span class="review-value">{{ $design['design_code'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Event Title</span>
                        <span class="review-value">{{ match ($design['card_title']) { 'Walimatul Urus' => 'Wedding Reception', 'Majlis Perkahwinan' => 'Wedding Ceremony', 'Kenduri Kesyukuran' => 'Thanksgiving Celebration', default => $design['card_title'] ?: '-' } }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Couple Photo</span>
                        @if ($design['has_card_image'])
                            <span class="upload-status">Photo uploaded</span>
                        @else
                            <span class="upload-status not-uploaded">No photo uploaded</span>
                        @endif
                    </div>
                </div>

                <h3>Included in the Card Package</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Banner & bunting</span>
                        <span class="review-value">Included automatically. The preview uses the short names and selected card design theme. Couple photos are used only for the PORTRAIT theme.</span>
                    </div>
                </div>

                <h3>Parents' Details</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Father's Name</span>
                        <span class="review-value">{{ $parents['father_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Mother's Name</span>
                        <span class="review-value">{{ $parents['mother_name'] ?: '-' }}</span>
                    </div>
                </div>

                <h3>Event Details</h3>
                <div class="review-grid">
                    <div class="review-item">
                        <span class="review-label">Day</span>
                        <span class="review-value">{{ ['Ahad' => 'Sunday', 'Isnin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 'Khamis' => 'Thursday', 'Jumaat' => 'Friday', 'Sabtu' => 'Saturday'][$event['day_name'] ?? ''] ?? '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Event Date</span>
                        <span class="review-value">{{ $event['event_date'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Hijri Date</span>
                        <span class="review-value">{{ $event['hijri_date'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Meal Time</span>
                        <span class="review-value">{{ $event['meal_time'] ? substr($event['meal_time'], 0, 5) : '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Wedding Reception Time</span>
                        <span class="review-value">{{ $event['bersanding_time'] ? substr($event['bersanding_time'], 0, 5) : '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Event Venue</span>
                        <span class="review-value">{{ $event['venue_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Event Address</span>
                        <span class="review-value">{{ $event['full_address'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Google Maps</span>
                        @if ($event['google_maps_url'])
                            <a class="maps-link" href="{{ $event['google_maps_url'] }}" target="_blank" rel="noopener noreferrer">
                                Open location in Google Maps
                            </a>
                        @else
                            <span class="review-value empty-value">-</span>
                        @endif
                    </div>
                </div>

                <h3>Contact Persons</h3>
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
            <h2>Delivery / Pickup</h2>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Method</span>
                    <span class="review-value">{{ $fulfilmentLabel }}</span>
                </div>

                @if (($review['fulfilment']['method'] ?? null) === 'COURIER')
                    <div class="review-item">
                        <span class="review-label">Recipient Name</span>
                        <span class="review-value">{{ $review['fulfilment']['recipient_name'] ?: '-' }}</span>
                    </div>

                    <div class="review-item">
                        <span class="review-label">Recipient Phone</span>
                        <span class="review-value">{{ $review['fulfilment']['recipient_phone'] ?: '-' }}</span>
                    </div>

                    <div class="review-item full-width">
                        <span class="review-label">Delivery Address</span>
                        <span class="review-value">{{ $review['fulfilment']['shipping_address'] ?: '-' }}</span>
                    </div>
                @endif
            </div>
        </section>

        <section class="review-card payment-card">
            <div class="payment-heading">
                <div>
                    <p class="eyebrow">Order Payment</p>
                    <h2>Deposit Payment</h2>
                </div>
                <span class="deposit-amount">
                    RM {{ number_format((float) $review['pricing']['deposit_amount'], 2) }}
                </span>
            </div>

            <div class="review-grid">
                <div class="review-item">
                    <span class="review-label">Card package price</span>
                    <span class="review-value">RM {{ number_format((float) $review['pricing']['package_amount'], 2) }}</span>
                </div>
                @if ((float) $review['pricing']['postage_amount'] > 0)
                    <div class="review-item">
                        <span class="review-label">{{ $review['pricing']['delivery_label'] }}</span>
                        <span class="review-value">RM {{ number_format((float) $review['pricing']['postage_amount'], 2) }}</span>
                    </div>
                @endif
                <div class="review-item">
                    <span class="review-label">Order total</span>
                    <span class="review-value">RM {{ number_format((float) $review['pricing']['total_amount'], 2) }}</span>
                </div>
            </div>

            <div class="deposit-notice">
                <strong>This is the deposit payment only.</strong>
                <p>The remaining balance is due after the design has been approved and you have been notified.</p>
            </div>

            @if ($order->booking_payment_status === 'PAID')
                <div class="deposit-notice">
                    <strong>Booking payment has been confirmed.</strong>
                    <p>The remaining balance is due after design approval.</p>
                </div>
            @else
                <div class="deposit-notice">
                    <strong>Booking payment is still outstanding.</strong>
                    <p>Complete your booking payment before final confirmation.</p>
                    <div class="payment-method-actions">
                        <form method="POST" action="{{ route('orders.booking-payment.store', ['orderId' => $order->order_id]) }}">
                            @csrf
                            <input type="hidden" name="payment_method" value="BILLPLZ">
                            <button class="confirm-button" type="submit">
                                Pay booking deposit · RM {{ number_format((float) $review['pricing']['deposit_amount'], 2) }} · Billplz
                            </button>
                        </form>
                        @if (config('services.billplz.duitnow_qr_enabled'))
                            <form method="POST" action="{{ route('orders.booking-payment.store', ['orderId' => $order->order_id]) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="DUITNOW_QR">
                                <button class="confirm-button" type="submit">
                                    Pay booking deposit · RM {{ number_format((float) $review['pricing']['deposit_amount'], 2) }} · DuitNow QR
                                </button>
                            </form>
                        @endif
                    </div>
                    @error('payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    @error('payment_method')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
            @endif
        </section>

        <section class="review-card confirmation-card">
            <h2>Final Confirmation</h2>

            <p class="confirmation-note">
                After confirmation, these order details can no longer be edited by the customer.
                Make sure all names, dates, times, addresses, design choices and phone numbers are correct.
            </p>

            <form id="final-confirmation-form" method="POST" action="{{ route('orders.confirm.store', ['orderId' => $review['order_id']]) }}">
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
                        I have reviewed all the details above and confirm that they are correct.
                    </span>
                </label>
                @error('responsibility_acknowledged')<p class="field-error" role="alert">{{ $message }}</p>@enderror

                <label class="confirmation-checkbox">
                    <input
                        type="checkbox"
                        name="post_confirmation_liability_acknowledged"
                        value="1"
                        required
                        @checked(old('post_confirmation_liability_acknowledged'))
                    >
                    <span>
                        I understand and agree that KingKadKahwin is not responsible for errors in the information found after this final confirmation.
                    </span>
                </label>
                @error('post_confirmation_liability_acknowledged')<p class="field-error" role="alert">{{ $message }}</p>@enderror

                <div class="review-actions">
                    <a class="back-link" href="{{ route('orders.dashboard', ['orderId' => $review['order_id']]) }}">
                        Back & Edit Details
                    </a>

                    <button id="final-confirmation-submit" class="confirm-button" type="submit">
                        Confirm Order Details
                    </button>
                </div>
            </form>
        </section>
    </main>

    <dialog id="final-confirmation-dialog" class="final-confirmation-dialog" aria-labelledby="final-confirmation-dialog-title">
        <div class="final-confirmation-dialog-icon" aria-hidden="true">!</div>
        <h2 id="final-confirmation-dialog-title">Confirm and lock these details?</h2>
        <p>After confirmation, the customer cannot edit the order details. Please make sure everything is correct.</p>
        <div class="final-confirmation-dialog-actions">
            <button id="cancel-final-confirmation" type="button">No, review again</button>
            <button id="confirm-final-confirmation" class="is-primary" type="button">Yes, confirm order</button>
        </div>
    </dialog>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const confirmationForm = document.getElementById('final-confirmation-form');
            const submitButton = document.getElementById('final-confirmation-submit');
            const confirmationDialog = document.getElementById('final-confirmation-dialog');
            const confirmButton = document.getElementById('confirm-final-confirmation');
            const cancelConfirmationButton = document.getElementById('cancel-final-confirmation');
            let finalConfirmationApproved = false;

            confirmationForm.addEventListener('submit', function (event) {
                if (finalConfirmationApproved) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Confirming...';

                    return;
                }

                event.preventDefault();
                confirmationDialog.showModal();
            });

            cancelConfirmationButton.addEventListener('click', function () {
                confirmationDialog.close();
            });

            confirmButton.addEventListener('click', function () {
                finalConfirmationApproved = true;
                confirmationDialog.close();
                confirmationForm.requestSubmit(submitButton);
            });
        });
    </script>
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
