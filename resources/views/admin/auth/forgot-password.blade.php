<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Laluan Admin — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body class="login-page login-page--admin">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand"><span class="login-mark">KKK</span><div><strong>King Kad Kahwin</strong><span>SISTEM OPERASI</span></div></div>
            <div class="login-story-content"><span class="login-eyebrow">AKSES SELAMAT</span><h2>Tetapkan semula. <br>Teruskan operasi.</h2><p>Pautan yang selamat akan dihantar ke e-mel admin yang aktif.</p></div>
            <div class="login-story-footer"><span class="login-dot"></span> Satu pasukan. Satu tujuan.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div><div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="forgot-password-title">
            <div class="login-heading"><span class="login-eyebrow">LUPA KATA LALUAN</span><h1 id="forgot-password-title">Reset akses<span>.</span></h1><p>Masukkan e-mel admin untuk menerima pautan tetapan semula.</p></div>
            @if (session('status'))<div class="login-alert login-alert--success" role="status">{{ session('status') }}</div>@endif
            @error('email')<div class="login-alert" role="alert">{{ $message }}</div>@enderror
            <form method="POST" action="{{ route('admin.password.email') }}" class="login-form">
                @csrf
                <div class="login-field"><label for="email">E-mel Admin</label><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@kingkadkahwin.com" required autofocus autocomplete="email"></div>
                <button type="submit" class="login-submit">Hantar Pautan Reset <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help"><a href="{{ route('admin.login') }}">Kembali ke Log Masuk Admin</a></p>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Ruang kerja pasukan anda</footer>
    @include('partials.malay-validation')
</body>
</html>
