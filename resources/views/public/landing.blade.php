<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Kad kahwin yang mudah ditempah, direka dengan teliti dan boleh disemak secara online.">
    <title>KingKadKahwin — Kad Kahwin Anda, Direka Dengan Teliti</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
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
            <a class="nav-progress" href="{{ route('public.orders.progress') }}">Semak Progress</a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">Kad kahwin 4 × 6 • Mudah • Tersusun</p>
                <h1>Raikan hari istimewa dengan kad yang terasa <em>benar-benar milik anda.</em></h1>
                <p class="hero-text">Pilih tema, lengkapkan maklumat majlis dan lihat preview kad secara langsung. Kami uruskan perjalanan daripada design hingga kad siap dihantar.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ route('public.orders.create') }}"><span>Tempah Sekarang</span><span class="button-arrow" aria-hidden="true">→</span></a>
                    <a class="button button-secondary" href="{{ route('public.orders.progress') }}"><span>Semak Progress</span></a>
                </div>
                <ul class="trust-list" aria-label="Kelebihan utama">
                    <li><span>✓</span> Live preview</li>
                    <li><span>✓</span> Autosave maklumat</li>
                    <li><span>✓</span> Progress online</li>
                </ul>
            </div>

            <div class="hero-art" aria-label="Contoh kad kahwin KingKadKahwin">
                <div class="botanical botanical-one">✦</div>
                <div class="card-runner card-runner-back">
                    <article class="sample-card sample-card-back">
                        <small>WALIMATUL URUS</small>
                        <strong>Syafiq<br><i>&amp;</i><br>Awanis</strong>
                        <span>SABTU</span>
                        <b>16 SEPTEMBER 2026</b>
                    </article>
                </div>
                <div class="card-runner card-runner-front">
                    <article class="sample-card sample-card-front">
                        <div class="card-pattern"></div>
                        <small>Dengan penuh kesyukuran</small>
                        <h2>Syafiq <i>&amp;</i> Awanis</h2>
                        <div class="mini-details">
                            <b>16</b>
                            <span>SEPTEMBER<br>2026</span>
                        </div>
                        <p>Raikan cinta, abadikan kenangan.</p>
                    </article>
                </div>
                <div class="botanical botanical-two">✦</div>
            </div>
        </section>

        <section class="feature-strip" id="kelebihan">
            <article><span>01</span><div><h2>Pilihan design</h2><p>Tema untuk pelbagai gaya majlis, daripada minimal hingga klasik.</p></div></article>
            <article><span>02</span><div><h2>Semakan lebih mudah</h2><p>Lihat nama dan maklumat majlis pada kad sambil mengisi borang.</p></div></article>
            <article><span>03</span><div><h2>Status yang jelas</h2><p>Ikuti progress design, cetakan, packing dan penghantaran.</p></div></article>
        </section>

        <section class="journey" id="cara-tempah">
            <div class="section-intro">
                <p class="eyebrow">Daripada idea kepada kad sebenar</p>
                <h2>Tempahan yang ringkas dan mudah difahami.</h2>
            </div>
            <ol>
                <li><b>1</b><div><h3>Isi maklumat</h3><p>Pilih pakej dan lengkapkan butiran pengantin serta majlis.</p></div></li>
                <li><b>2</b><div><h3>Semak & sahkan</h3><p>Lihat preview, betulkan maklumat dan sahkan apabila tepat.</p></div></li>
                <li><b>3</b><div><h3>Kami siapkan</h3><p>Pasukan kami mengurus design, cetakan dan pembungkusan.</p></div></li>
                <li><b>4</b><div><h3>Ikuti progress</h3><p>Masukkan Order ID pada bila-bila masa untuk melihat perkembangan.</p></div></li>
            </ol>
        </section>

        <section class="final-cta">
            <div><p class="eyebrow">Hari bahagia bermula di sini</p><h2>Sedia mencipta kad kahwin anda?</h2></div>
            <a class="button button-light" href="{{ route('public.orders.create') }}"><span>Mulakan Tempahan</span><span class="button-arrow" aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-main">
            <div>
                <a class="brand footer-brand" href="{{ route('home') }}"><span class="brand-mark">K</span><b>KingKadKahwin</b></a>
                <p>Design yang indah. Proses yang tenang.</p>
            </div>

            <div class="footer-social">
                <p>Ikuti kami</p>
                <div class="social-links">
                    @foreach ([
                        ['key' => 'instagram', 'label' => 'Instagram'],
                        ['key' => 'facebook', 'label' => 'Facebook'],
                        ['key' => 'tiktok', 'label' => 'TikTok'],
                        ['key' => 'whatsapp', 'label' => 'WhatsApp'],
                    ] as $social)
                        @if (config('kingkadkahwin.social.'.$social['key']))
                            <a class="social-link social-{{ $social['key'] }}" href="{{ config('kingkadkahwin.social.'.$social['key']) }}" target="_blank" rel="noopener noreferrer" aria-label="KingKadKahwin di {{ $social['label'] }}">
                                <span aria-hidden="true">
                                    @if ($social['key'] === 'instagram')
                                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.5" cy="6.5" r="1" class="social-icon-fill"></circle></svg>
                                    @elseif ($social['key'] === 'facebook')
                                        <svg viewBox="0 0 24 24"><path class="social-icon-fill" d="M14 8h3V4.3c-.5-.1-2.2-.3-4.1-.3C9 4 6.3 6.4 6.3 10.8V14H3v4h3.3v6h4V18h3.4l.6-4h-4v-2.8C10.3 9.3 10.8 8 14 8Z"></path></svg>
                                    @elseif ($social['key'] === 'whatsapp')
                                        <svg viewBox="0 0 24 24"><path d="M20 11.7a8 8 0 0 1-11.8 7l-4.2 1.1 1.1-4.1A8 8 0 1 1 20 11.7Z"></path><path d="M8.4 7.8c.2-.5.5-.5.8-.5h.5c.2 0 .4.1.5.4l.8 1.9c.1.3.1.5-.1.7l-.6.8c-.2.2-.1.4 0 .6.7 1.3 1.7 2.3 3 2.9.2.1.4.1.6-.1l.9-1.1c.2-.2.4-.3.7-.2l1.9.9c.3.1.5.3.5.5 0 .3-.2 1.6-1.1 2.2-.8.6-1.8.8-3 .4-1.1-.3-2.5-1-4.2-2.5-1.4-1.3-2.4-2.8-2.8-3.9-.4-1-.1-2.2.3-2.7l.3-.3Z"></path></svg>
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
            <span>Kad indah untuk hari yang bermakna.</span>
        </div>
    </footer>
</body>
</html>
