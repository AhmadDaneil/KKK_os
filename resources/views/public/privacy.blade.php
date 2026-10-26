<!doctype html>
<html lang="ms" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <meta name="description" content="Notis privasi KingKadKahwin menerangkan bagaimana maklumat pelanggan dikumpul dan digunakan.">
    <title>Notis Privasi / Privacy Notice — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="brand-landing brand-privacy">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}" aria-label="KingKadKahwin home">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Privacy notice navigation">
            <a class="header-link nav-progress" href="{{ route('home') }}">← Back to Home</a>
            <a class="header-link nav-progress" href="{{ route('public.orders.progress') }}">Track Order</a>
        </nav>
    </header>

    <main class="customer-legal-page">
        <header class="customer-legal-page-heading">
            <p class="eyebrow">Customer information</p>
            <h1>Notis Privasi <span aria-hidden="true">/</span> Privacy Notice</h1>
            <p>Ketahui cara maklumat anda digunakan untuk mengurus pesanan KingKadKahwin. / Learn how your information is used to manage your KingKadKahwin order.</p>
            <p class="customer-legal-updated">Dikemas kini / Last updated: 26 October 2026</p>
            <nav class="customer-legal-language-nav" aria-label="Choose language / Pilih bahasa">
                <a href="#notis-bm">Bahasa Melayu</a>
                <a href="#privacy-notice">English</a>
            </nav>
        </header>

        <article class="customer-legal-document" id="notis-bm" lang="ms" aria-labelledby="privacy-title-ms">
            <header>
                <p class="eyebrow">Bahasa Melayu</p>
                <h2 id="privacy-title-ms">Notis Perlindungan Data Peribadi</h2>
                <p>Notis ini menerangkan bagaimana MASSA EMPIRE, yang menjalankan perkhidmatan KingKadKahwin, mengendalikan data peribadi anda bagi urusan komersial dan tempahan.</p>
            </header>

            <section>
                <h3>1. Siapa kami dan cara menghubungi kami</h3>
                <p><strong>Pengguna data:</strong> {{ config('invoice.business_name') }} (No. pendaftaran {{ config('invoice.registration_number') }})</p>
                <p><strong>Alamat:</strong> {{ config('invoice.business_address') }}</p>
                <p><strong>E-mel:</strong> <a href="mailto:{{ config('invoice.business_email') }}">{{ config('invoice.business_email') }}</a></p>
                <p>Untuk pertanyaan atau permintaan berkaitan data peribadi, hubungi kami melalui e-mel di atas atau <a href="{{ config('kingkadkahwin.social.whatsapp') }}" target="_blank" rel="noopener noreferrer">Customer Support di WhatsApp</a>. Kami mungkin meminta maklumat yang munasabah untuk mengesahkan identiti anda sebelum memproses permintaan.</p>
            </section>

            <section>
                <h3>2. Data yang kami kumpul</h3>
                <p>Bergantung pada pesanan dan perkhidmatan yang anda gunakan, data boleh merangkumi:</p>
                <ul>
                    <li>nama, nombor telefon, e-mel dan rujukan atau ID pesanan;</li>
                    <li>butiran kad dan majlis seperti nama pasangan, nama ibu bapa atau kenalan, tarikh, masa, lokasi, alamat dan arahan reka bentuk;</li>
                    <li>butiran penerima dan penghantaran atau pengambilan;</li>
                    <li>maklumat transaksi seperti jumlah, kaedah, status, rujukan bayaran serta resit atau bukti bayaran yang anda hantar;</li>
                    <li>fail, gambar, proof reka bentuk dan rekod kemajuan pesanan yang berkaitan; dan</li>
                    <li>mesej atau maklumat yang anda berikan kepada pasukan sokongan berkenaan pesanan.</li>
                </ul>
            </section>

            <section>
                <h3>3. Sumber dan tujuan penggunaan data</h3>
                <p>Data biasanya diberikan oleh anda melalui WhatsApp, borang pesanan, dashboard atau komunikasi dengan pasukan kami. Kami juga boleh menerima maklumat status transaksi daripada penyedia bayaran, atau maklumat penghantaran daripada penyedia kurier, apabila berkaitan.</p>
                <p>Kami menggunakan data untuk menerima dan mengurus tempahan, menyediakan reka bentuk dan produk, menghubungi anda tentang pesanan, mengesahkan dan merekod bayaran, mengurus penghantaran atau pengambilan, berkongsi kemajuan yang diluluskan untuk pelanggan, menyediakan sokongan, serta memenuhi kewajipan undang-undang dan rekod perniagaan.</p>
                <p>Jika anda memberikan data peribadi orang lain, contohnya pasangan, ibu bapa atau kenalan majlis, pastikan anda berhak memberikannya dan telah memaklumkan mereka jika diperlukan.</p>
            </section>

            <section>
                <h3>4. Data wajib dan pilihan anda</h3>
                <p>Maklumat yang ditandakan wajib atau diperlukan untuk langkah tertentu perlu diberikan supaya kami boleh memproses pesanan, menghubungi anda atau menghantar produk. Jika maklumat wajib tidak diberikan, kami mungkin tidak dapat meneruskan langkah berkaitan. Maklumat pilihan boleh tidak diberikan tanpa menjejaskan bahagian perkhidmatan yang tidak memerlukannya.</p>
                <p>Jika kami ingin menggunakan data untuk promosi, kami akan memberi pilihan yang sesuai dan anda boleh meminta untuk menghentikan mesej promosi.</p>
            </section>

            <section>
                <h3>5. Pendedahan kepada pihak lain</h3>
                <p>Kami tidak menjual data peribadi pelanggan. Kami hanya berkongsi data yang diperlukan bagi tujuan yang diterangkan dalam notis ini, termasuk dengan:</p>
                <ul>
                    <li>kakitangan yang diberi kuasa untuk mengurus pesanan;</li>
                    <li>penyedia perkhidmatan komunikasi atau pemesejan, termasuk WhatsApp/Wabot apabila digunakan;</li>
                    <li>penyedia bayaran seperti Billplz jika anda memilih kaedah bayaran tersebut;</li>
                    <li>penyedia hosting, sistem dan simpanan fail yang menyokong operasi laman web;</li>
                    <li>penyedia kurier atau penghantaran apabila pesanan dihantar; dan</li>
                    <li>pihak berkuasa atau pihak lain apabila dikehendaki oleh undang-undang.</li>
                </ul>
                <p>Penyedia perkhidmatan hanya boleh menggunakan data setakat yang diperlukan untuk menyediakan perkhidmatan mereka. Jika pemprosesan oleh penyedia melibatkan lokasi di luar Malaysia, kami akan mengambil langkah yang dikehendaki oleh undang-undang yang terpakai.</p>
            </section>

            <section>
                <h3>6. Penyimpanan dan keselamatan</h3>
                <p>Kami menyimpan data hanya selama yang diperlukan untuk mengurus pesanan, menyediakan sokongan, menyimpan rekod perniagaan atau memenuhi kewajipan undang-undang dan menyelesaikan pertikaian. Apabila data tidak lagi diperlukan, kami akan mengambil langkah yang munasabah untuk memadam atau melupuskannya dengan selamat.</p>
                <p>Kami menggunakan kawalan akses dan langkah keselamatan yang munasabah untuk mengurangkan risiko kehilangan, penyalahgunaan atau akses tanpa kebenaran. Tiada kaedah penghantaran atau penyimpanan elektronik boleh dijamin selamat sepenuhnya.</p>
            </section>

            <section>
                <h3>7. Hak dan permintaan anda</h3>
                <p>Tertakluk kepada undang-undang yang terpakai, anda boleh meminta akses kepada atau pembetulan data peribadi anda, bertanya tentang cara data diproses, serta menarik balik persetujuan atau meminta had pemprosesan apabila berkenaan. Hubungi kami menggunakan butiran di atas. Kami akan menilai dan menjawab permintaan mengikut keperluan undang-undang.</p>
            </section>

            <section>
                <h3>8. Kuki, sesi dan perubahan notis</h3>
                <p>Laman web boleh menggunakan sesi dan teknologi yang diperlukan untuk fungsi, keselamatan dan pilihan paparan seperti tema. Kami akan mengemas kini notis ini apabila amalan pemprosesan atau keperluan berkaitan berubah. Versi terkini akan dipaparkan pada halaman ini.</p>
                <p>Notis ini bertujuan memaklumkan cara data dikendalikan; ia bukan persetujuan umum bagi semua pemprosesan. Jika persetujuan berasingan diperlukan, kami akan memintanya secara berasingan.</p>
            </section>
        </article>

        <article class="customer-legal-document" id="privacy-notice" lang="en" aria-labelledby="privacy-title-en">
            <header>
                <p class="eyebrow">English</p>
                <h2 id="privacy-title-en">Personal Data Protection Notice</h2>
                <p>This notice explains how MASSA EMPIRE, operating the KingKadKahwin service, handles your personal data in connection with our commercial services and orders.</p>
            </header>

            <section>
                <h3>1. Who we are and how to contact us</h3>
                <p><strong>Data user:</strong> {{ config('invoice.business_name') }} (Registration No. {{ config('invoice.registration_number') }})</p>
                <p><strong>Address:</strong> {{ config('invoice.business_address') }}</p>
                <p><strong>Email:</strong> <a href="mailto:{{ config('invoice.business_email') }}">{{ config('invoice.business_email') }}</a></p>
                <p>For questions or requests about your personal data, contact us at the email above or through <a href="{{ config('kingkadkahwin.social.whatsapp') }}" target="_blank" rel="noopener noreferrer">Customer Support on WhatsApp</a>. We may ask for reasonable information to verify your identity before handling a request.</p>
            </section>

            <section>
                <h3>2. Personal data we collect</h3>
                <p>Depending on your order and the services you use, this may include:</p>
                <ul>
                    <li>your name, phone number, email address and order reference or ID;</li>
                    <li>card and event details such as the couple's names, parents' or contact persons' names, dates, times, venues, addresses and design instructions;</li>
                    <li>recipient and delivery or collection details;</li>
                    <li>transaction information such as amounts, methods, status, payment references, receipts or payment proof you submit;</li>
                    <li>files, images, design proofs and order progress records; and</li>
                    <li>messages or information you give our support team about an order.</li>
                </ul>
            </section>

            <section>
                <h3>3. Sources and purposes</h3>
                <p>We usually receive data from you through WhatsApp, order forms, the customer dashboard or communications with our team. Where relevant, we may also receive transaction status from a payment provider or delivery information from a courier.</p>
                <p>We use data to accept and manage orders, prepare designs and products, contact you about an order, verify and record payments, arrange delivery or collection, share customer-approved progress, provide support, maintain business records and meet legal obligations or resolve disputes.</p>
                <p>If you provide another person's personal data, such as your partner's, parents' or event contact's details, make sure you are entitled to provide it and have informed them where required.</p>
            </section>

            <section>
                <h3>4. Required data and your choices</h3>
                <p>Information marked as required, or needed for a particular step, must be provided for us to process that order step, contact you or deliver the product. If required information is missing, we may not be able to continue with that step. Optional information can be withheld without affecting service steps that do not need it.</p>
                <p>If we use data for promotions, we will provide an appropriate choice and you may ask us to stop promotional messages.</p>
            </section>

            <section>
                <h3>5. Disclosure to others</h3>
                <p>We do not sell customers' personal data. We share only what is needed for the purposes described in this notice, including with:</p>
                <ul>
                    <li>staff authorised to manage orders;</li>
                    <li>communications or messaging providers, including WhatsApp/Wabot where used;</li>
                    <li>payment providers such as Billplz if you choose that payment method;</li>
                    <li>hosting, system and file-storage providers that support the website;</li>
                    <li>courier or delivery providers when an order is shipped; and</li>
                    <li>authorities or others where required by law.</li>
                </ul>
                <p>Service providers may use data only as needed to provide their services. If a provider processes data outside Malaysia, we will take steps required by applicable law.</p>
            </section>

            <section>
                <h3>6. Retention and security</h3>
                <p>We keep data only for as long as needed to manage orders, provide support, maintain business records, meet legal obligations or resolve disputes. When data is no longer needed, we will take reasonable steps to delete or securely dispose of it.</p>
                <p>We use reasonable access controls and security measures to reduce the risk of loss, misuse or unauthorised access. No electronic transmission or storage method can be guaranteed completely secure.</p>
            </section>

            <section>
                <h3>7. Your rights and requests</h3>
                <p>Subject to applicable law, you may request access to or correction of your personal data, ask how it is processed, and withdraw consent or request limits on processing where applicable. Contact us using the details above. We will assess and respond to requests as required by law.</p>
            </section>

            <section>
                <h3>8. Cookies, sessions and notice updates</h3>
                <p>The website may use sessions and technologies needed for functionality, security and display preferences such as your selected theme. We will update this notice when our processing practices or relevant requirements change. The latest version will be available on this page.</p>
                <p>This notice explains how data is handled; it is not blanket consent for every type of processing. Where separate consent is required, we will request it separately.</p>
            </section>
        </article>
    </main>

    @include('public.partials.site-footer')
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
</body>
</html>
