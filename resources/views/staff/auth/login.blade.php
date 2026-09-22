<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body class="login-page login-page--staff">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand">
                <span class="login-mark">KKK</span>
                <div><strong>King Kad Kahwin</strong><span>OPERATIONS SYSTEM</span></div>
            </div>
            <div class="login-story-content">
                <span class="login-eyebrow">BERSAMA, KITA JAYAKAN</span>
                <h2>Setiap tugasan, <br>bermula di sini.</h2>
                <p>Dari idea hingga hasil akhir, urus kerja harian dan cipta sesuatu yang bermakna bersama pasukan.</p>
                <div class="login-tags"><span>Operation</span><span>Design</span><span>Production</span></div>
            </div>
            <div class="login-story-footer"><span class="login-dot"></span> Satu pasukan. Satu tujuan.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div>
            <div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-heading">
                <span class="login-eyebrow">SELAMAT KEMBALI</span>
                <h1 id="login-title">Staff Login<span>.</span></h1>
                <p>Log masuk untuk mengakses tugasan dan meneruskan kerja anda.</p>
            </div>
            @if ($errors->any())
                <div class="login-alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('staff.login.store') }}" class="login-form">
                @csrf
                <div class="login-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@kingkadkahwin.com" required autofocus autocomplete="email">
                </div>
                <div class="login-field">
                    <label for="password">Kata Laluan</label>
                    <input id="password" type="password" name="password" placeholder="Masukkan kata laluan anda" required autocomplete="current-password">
                </div>
                <button type="submit" class="login-submit">Log Masuk <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help">Masalah untuk log masuk? Hubungi pentadbir sistem.</p>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Ruang kerja pasukan anda</footer>
</body>
</html>
