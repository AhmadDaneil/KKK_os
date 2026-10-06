<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kad kahwin yang mudah ditempah, direka dengan teliti dan boleh disemak secara online.">
    <title>KingKadKahwin — Kad Kahwin Anda, Direka Dengan Teliti</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="KingKadKahwin halaman utama">
            <span class="brand-mark">K</span>
            <span><b>KingKadKahwin</b><small>Kad indah, kenangan bermakna</small></span>
        </a>
        <nav aria-label="Navigasi utama">
            <a href="#kelebihan">Kelebihan</a>
            <a href="#cara-tempah">Cara Tempah</a>
            <a href="#soalan-lazim">Soalan Lazim</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <h1>Raikan hari istimewa dengan kad yang terasa <em>benar-benar milik anda.</em></h1>
                <p class="hero-text">Pilih tema, lengkapkan maklumat majlis dan lihat pratonton kad secara langsung. Kami uruskan proses daripada design hingga kad siap dihantar.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ route('public.orders.create') }}"><span>Tempah Sekarang</span><span class="button-arrow" aria-hidden="true">→</span></a>
                    <a class="button button-secondary" href="{{ route('public.orders.progress') }}"><span>Semak Progress</span></a>
                </div>
                <ul class="trust-list" aria-label="Kelebihan utama">
                    <li><span>✓</span> Pratonton langsung</li>
                    <li><span>✓</span> Simpan automatik</li>
                    <li><span>✓</span> Progress dalam talian</li>
                </ul>
            </div>

            <div class="hero-art" aria-label="Contoh kad kahwin KingKadKahwin">
                <div class="card-runner card-runner-back">
                    <article class="sample-card sample-card-back sample-card-image">
                        <img src="{{ asset('images/landing/card-nostalgia-ckn-008.jpg') }}"
                            alt="Kad kahwin tema Nostalgia"
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
                            alt="Kad kahwin tema Desa"
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
                            alt="Kad kahwin tema taman bunga ungu"
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
                            alt="Kad kahwin tema Islamik emas"
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
            <article><span>01</span><div><h2>Pilihan design</h2><p>Tema untuk pelbagai gaya majlis, daripada minimal hingga klasik.</p></div></article>
            <article><span>02</span><div><h2>Semakan lebih mudah</h2><p>Lihat nama dan maklumat majlis pada kad sambil mengisi borang.</p></div></article>
            <article><span>03</span><div><h2>Status yang jelas</h2><p>Ikuti progress design, cetakan, pembungkusan dan penghantaran.</p></div></article>
        </section>

        <section class="journey" id="cara-tempah">
            <div class="section-intro">
                <p class="eyebrow">Daripada idea kepada kad sebenar</p>
                <h2>Tempahan yang ringkas dan mudah difahami.</h2>
            </div>
            <ol>
                <li><b>1</b><div><h3>Isi maklumat</h3><p>Pilih pakej dan lengkapkan butiran pengantin serta majlis.</p></div></li>
                <li><b>2</b><div><h3>Semak & sahkan</h3><p>Lihat pratonton, betulkan maklumat dan sahkan apabila tepat.</p></div></li>
                <li><b>3</b><div><h3>Kami siapkan</h3><p>Pasukan kami mengurus design, cetakan dan pembungkusan.</p></div></li>
                <li><b>4</b><div><h3>Ikuti progress</h3><p>Masukkan ID Tempahan pada bila-bila masa untuk melihat perkembangan.</p></div></li>
            </ol>
        </section>

        <section class="customer-information" id="soalan-lazim">
            <div class="section-intro">
                <p class="eyebrow">Sebelum anda menempah</p>
                <h2>Maklumat penting dan soalan lazim.</h2>
            </div>
            <div class="faq-grid">
                <details open><summary>Bagaimanakah bayaran dibuat?</summary><p>Bayaran pertama hanyalah deposit untuk memulakan kerja design. Bayaran baki dibuat selepas anda menyemak dan meluluskan hasil design, sebelum proses pengeluaran dimulakan.</p></details>
                <details><summary>Bagaimanakah pembetulan hasil design dibuat?</summary><p>Anda boleh memilih hasil design yang terlibat, menulis arahan pembetulan dan menghantar bukti bayaran pembetulan melalui halaman Semak Progress.</p></details>
                <details><summary>Berapa lama proses tempahan?</summary><p>Tempoh bergantung pada jumlah pakej, pembetulan, kuantiti dan kaedah penghantaran. Progress semasa dipaparkan menggunakan ID Tempahan serta nombor telefon anda.</p></details>
                <details><summary>Adakah banner dan banting disokong?</summary><p>Ya. Anda boleh menyatakan saiz, kuantiti, bahan, orientasi dan arahan tambahan dalam borang tempahan.</p></details>
            </div>
        </section>

        <section class="customer-policies" id="polisi">
            <article id="privasi"><h2>Privasi</h2><p>Maklumat tempahan digunakan untuk menyiapkan, menghubungi dan menghantar tempahan anda. ID Tempahan dan nombor telefon diperlukan untuk melihat maklumat sensitif.</p></article>
            <article id="terma"><h2>Terma Tempahan</h2><p>Pastikan semua nama, tarikh, masa, alamat dan nombor telefon diperiksa sebelum pengesahan akhir. Perubahan selepas pengesahan mungkin melibatkan caj pembetulan.</p></article>
            <article id="pemulangan"><h2>Pembatalan dan Pemulangan</h2><p>Hubungi Khidmat Pelanggan dengan segera. Kelayakan pembatalan atau pemulangan bergantung pada tahap design dan pengeluaran semasa.</p></article>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-main">
            <div>
                <a class="brand footer-brand" href="{{ route('home') }}"><span class="brand-mark">K</span><b>KingKadKahwin</b></a>
                <p>Reka bentuk yang indah. Proses yang tenang.</p>
            </div>

            <div class="footer-social">
                <p>Ikuti kami</p>
                <div class="social-links">
                    @foreach ([
                        ['key' => 'instagram', 'label' => 'Instagram'],
                        ['key' => 'facebook', 'label' => 'Facebook'],
                        ['key' => 'tiktok', 'label' => 'TikTok'],
                    ] as $social)
                        @if (config('kingkadkahwin.social.'.$social['key']))
                            <a class="social-link social-{{ $social['key'] }}" href="{{ config('kingkadkahwin.social.'.$social['key']) }}" target="_blank" rel="noopener noreferrer" aria-label="KingKadKahwin di {{ $social['label'] }}">
                                <span aria-hidden="true">
                                    @if ($social['key'] === 'instagram')
                                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" class="social-icon-fill"></circle></svg>
                                    @elseif ($social['key'] === 'facebook')
                                        <svg viewBox="0 0 24 24"><path class="social-icon-fill" d="M14 8h3V4.3c-.5-.1-2.2-.3-4.1-.3C9 4 6.3 6.4 6.3 10.8V14H3v4h3.3v6h4V18h3.4l.6-4h-4v-2.8C10.3 9.3 10.8 8 14 8Z"></path></svg>
                                    @else
                                        <svg viewBox="0 0 24 24"><path class="social-icon-fill" d="M14.5 3c.4 2.4 1.8 3.9 4.5 4.1v3.1a8.3 8.3 0 0 1-4.5-1.3v6.2a6.1 6.1 0 1 1-5.3-6v3.3a2.9 2.9 0 1 0 2.1 2.8V3h3.2Z"></path></svg>
                                    @endif
                                </span>
                                {{ $social['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} KingKadKahwin</span>
            <span><a href="#privasi">Privasi</a> · <a href="#terma">Terma Tempahan</a> · <a href="#pemulangan">Pembatalan & Pemulangan</a></span>
        </div>
    </footer>
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
