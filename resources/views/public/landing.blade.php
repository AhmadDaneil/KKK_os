<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Beautiful wedding cards, easy online ordering and live previews.">
    <title>KingKadKahwin — Wedding Cards Made for You</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-landing">
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="KingKadKahwin home">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Main navigation">
            <a href="#kelebihan">Benefits</a>
            <a href="#cara-tempah">How to Order</a>
            <a href="#soalan-lazim">FAQs</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <h1>Celebrate your special day with a card that feels <em>uniquely yours.</em></h1>
                <p class="hero-text">Chat with us on WhatsApp to choose your package and pay the booking fee. After payment is confirmed, you can complete your order and preview your card in the Customer Dashboard.</p>
                @if ($activeDraft)
                    <div class="landing-resume-order" role="status">
                        <strong>Booking payment confirmed</strong>
                        <p>Your booking is ready. Complete your details or track your order progress below.</p>
                        <div class="hero-actions">
                            <a class="button button-primary" href="{{ route('orders.dashboard', ['orderId' => $activeDraft->order_id]) }}#customer-order-form">Complete Order Details →</a>
                            <a class="button button-secondary" href="{{ route('public.orders.progress', ['order_id' => $activeDraft->order_id]) }}">Track Progress →</a>
                        </div>
                    </div>
                @endif
                <div class="hero-actions">
                    @unless ($activeDraft)
                        <a class="button button-primary" href="{{ route('public.orders.create') }}"><span>Chat to Book</span><span class="button-arrow" aria-hidden="true">→</span></a>
                        <a class="button button-secondary" href="{{ route('public.orders.progress') }}"><span>Track Order</span></a>
                    @endunless
                    <a class="button button-secondary" href="{{ route('orders.access.create') }}"><span>Continue Existing Order</span><span class="button-arrow" aria-hidden="true">→</span></a>
                </div>
                <ul class="trust-list" aria-label="Key benefits">
                    <li><span>✓</span> Live preview</li>
                    <li><span>✓</span> Automatic saving</li>
                    <li><span>✓</span> Online order tracking</li>
                </ul>
            </div>

            <div class="hero-art" aria-label="KingKadKahwin wedding card samples">
                <div class="card-runner card-runner-back">
                    <article class="sample-card sample-card-back sample-card-image">
                        <img src="{{ asset('images/landing/card-nostalgia-ckn-008.jpg') }}"
                            alt="Nostalgia wedding card design"
                            loading="eager"
                            decoding="async"
                            draggable="false">
                        <span class="landing-card-watermark" aria-hidden="true">
                            <span>KING KAD KAHWIN · PREVIEW</span>
                            <span>KING KAD KAHWIN · PREVIEW</span>
                        </span>
                    </article>
                </div>
                <div class="card-runner card-runner-front">
                    <article class="sample-card sample-card-front sample-card-image">
                        <img src="{{ asset('images/landing/card-desa-ckd-008.jpg') }}"
                            alt="Countryside wedding card design"
                            loading="eager"
                            decoding="async"
                            draggable="false">
                        <span class="landing-card-watermark" aria-hidden="true">
                            <span>KING KAD KAHWIN · PREVIEW</span>
                            <span>KING KAD KAHWIN · PREVIEW</span>
                        </span>
                    </article>
                </div>
                <div class="card-runner card-runner-garden">
                    <article class="sample-card sample-card-garden sample-card-image">
                        <img src="{{ asset('images/landing/card-garden-purple.png') }}"
                            alt="Purple garden wedding card design"
                            loading="eager"
                            decoding="async"
                            draggable="false">
                        <span class="landing-card-watermark" aria-hidden="true">
                            <span>KING KAD KAHWIN · PREVIEW</span>
                            <span>KING KAD KAHWIN · PREVIEW</span>
                        </span>
                    </article>
                </div>
                <div class="card-runner card-runner-islamic">
                    <article class="sample-card sample-card-islamic sample-card-image">
                        <img src="{{ asset('images/landing/card-islamic-gold.png') }}"
                            alt="Gold Islamic wedding card design"
                            loading="lazy"
                            decoding="async"
                            draggable="false">
                        <span class="landing-card-watermark" aria-hidden="true">
                            <span>KING KAD KAHWIN · PREVIEW</span>
                            <span>KING KAD KAHWIN · PREVIEW</span>
                        </span>
                    </article>
                </div>
            </div>
        </section>

        <section class="feature-strip" id="kelebihan">
            <article><span>01</span><div><h2>Design choices</h2><p>Themes for every celebration, from minimal to classic.</p></div></article>
            <article><span>02</span><div><h2>Easy review</h2><p>See names and event details on your card as you complete the form.</p></div></article>
            <article><span>03</span><div><h2>Clear updates</h2><p>Follow design, printing, packing and delivery progress.</p></div></article>
        </section>

        <section class="journey" id="cara-tempah">
            <div class="section-intro">
                <p class="eyebrow">From idea to finished card</p>
                <h2>Ordering made simple.</h2>
            </div>
            <ol>
                <li><b>1</b><div><h3>Choose with Wabot</h3><p>Confirm your package, collection and booking amount in WhatsApp.</p></div></li>
                <li><b>2</b><div><h3>Pay the booking fee</h3><p>Use the secure payment link sent in your chat. One package is RM50; two packages are RM100.</p></div></li>
                <li><b>3</b><div><h3>Complete your order</h3><p>After payment confirmation, Wabot sends your order reference and secure dashboard link. Enter your wedding details and review the live preview there.</p></div></li>
                <li><b>4</b><div><h3>Follow production</h3><p>Confirm your details, pay the remaining balance when due and track progress through delivery or pickup.</p></div></li>
            </ol>
        </section>

        <section class="customer-information" id="soalan-lazim">
            <div class="section-intro">
                <p class="eyebrow">Before you order</p>
                <h2>Important information and FAQs.</h2>
            </div>
            <div class="faq-grid">
                <details><summary>How do payments work?</summary><p>Pay the RM50 or RM100 booking fee using the link Wabot sends you. Your dashboard becomes available after that payment is confirmed. The remaining balance is due after design approval and before production.</p></details>
                <details><summary>How do I request a design correction?</summary><p>Select the affected artwork, describe the changes and submit proof of correction payment on the Track Order page.</p></details>
                <details><summary>How long will my order take?</summary><p>Timing depends on the number of packages, corrections, quantity and delivery method. Use your Order ID and phone number to see current progress.</p></details>
                <details><summary>Are banners and bunting available?</summary><p>Yes. You can specify sizes, quantities, materials, orientation and additional instructions in the order form.</p></details>
            </div>
        </section>

        <section class="customer-policies" id="polisi">
            <article><h2>Privacy</h2><p>Order information is used to prepare, discuss and deliver your order.</p><a href="{{ route('public.privacy') }}">Read our privacy notice</a></article>
            <article id="terma"><h2>Order Terms</h2><p>Check all names, dates, times, addresses and phone numbers before final confirmation. Changes after confirmation may incur correction fees.</p><a href="{{ route('public.terms') }}">Read our order terms</a></article>
            <article id="pemulangan"><h2>Cancellations and Refunds</h2><p>Contact Customer Support promptly. Eligibility depends on the current design and production stage.</p></article>
        </section>
    </main>

    @include('public.partials.site-footer')
    <script>
        document.querySelectorAll('.sample-card-image').forEach(function (card) {
            card.addEventListener('contextmenu', function (event) { event.preventDefault(); });
            card.addEventListener('dragstart', function (event) { event.preventDefault(); });
        });

        const faqGrid = document.querySelector('.faq-grid');
        const faqCards = faqGrid ? Array.from(faqGrid.querySelectorAll('details')) : [];
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (faqGrid && faqCards.length && 'IntersectionObserver' in window && ! prefersReducedMotion) {
            faqGrid.classList.add('faq-motion-ready');

            const faqObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (! entry.isIntersecting) {
                        return;
                    }

                    const cardIndex = faqCards.indexOf(entry.target);
                    window.setTimeout(function () {
                        entry.target.classList.add('is-visible');
                    }, Math.max(cardIndex, 0) * 110);
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.18, rootMargin: '0px 0px -40px' });

            faqCards.forEach(function (card) {
                faqObserver.observe(card);
            });
        }
    </script>
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
    @include('partials.malay-validation')
</body>
</html>
