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
                        ['key' => 'instagram', 'label' => 'Instagram', 'mark' => 'IG'],
                        ['key' => 'facebook', 'label' => 'Facebook', 'mark' => 'f'],
                        ['key' => 'tiktok', 'label' => 'TikTok', 'mark' => '♪'],
                        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'mark' => 'WA'],
                    ] as $social)
                        @if (config('kingkadkahwin.social.'.$social['key']))
                            <a href="{{ config('kingkadkahwin.social.'.$social['key']) }}" target="_blank" rel="noopener noreferrer" aria-label="KingKadKahwin di {{ $social['label'] }}">
                                <span aria-hidden="true">{{ $social['mark'] }}</span>
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
