<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk Admin — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body class="login-page login-page--admin">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand">
                <span class="login-mark">KKK</span>
                <div><strong>King Kad Kahwin</strong><span>SISTEM OPERASI</span></div>
            </div>
            <div class="login-story-content">
                <span class="login-eyebrow">PANDANGAN MENYELURUH</span>
                <h2>Urus pasukan. <br>Gerakkan operasi.</h2>
                <p>Satukan pengurusan staf dan pantau perjalanan operasi dalam satu ruang yang teratur.</p>
                <div class="login-tags"><span>Pengurusan staf</span><span>Pemantauan operasi</span></div>
            </div>
            <div class="login-story-footer"><span class="login-dot"></span> Satu pasukan. Satu tujuan.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div>
            <div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-heading">
                <span class="login-eyebrow">SELAMAT KEMBALI</span>
                <h1 id="login-title">Log Masuk Admin<span>.</span></h1>
                <p>Log masuk untuk mengurus akaun staf dan memantau keseluruhan operasi.</p>
            </div>
            @if ($errors->any())
                <div class="login-alert" role="alert">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="login-alert login-alert--success" role="status">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('admin.login.store') }}" class="login-form">
                @csrf
                <div class="login-field">
                    <label for="email">E-mel Admin</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@kingkadkahwin.com" required autofocus autocomplete="email">
                </div>
                <div class="login-field">
                    <label for="password">Kata Laluan</label>
                    <div class="password-field">
                        <input id="password" type="password" name="password" placeholder="Masukkan kata laluan anda" required autocomplete="current-password">
                        <button type="button" class="password-visibility-toggle" data-password-toggle aria-controls="password" aria-label="Tunjukkan kata laluan" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.75"></circle></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-submit">Log Masuk <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help"><a href="{{ route('admin.password.request') }}">Lupa kata laluan?</a></p>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Ruang kerja pasukan anda</footer>
    @include('partials.malay-validation')
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const passwordInput = document.getElementById(button.getAttribute('aria-controls'));
                const willShowPassword = passwordInput.type === 'password';

                passwordInput.type = willShowPassword ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(willShowPassword));
                button.setAttribute('aria-label', willShowPassword ? 'Sembunyikan kata laluan' : 'Tunjukkan kata laluan');
            });
        });
    </script>
</body>
</html>
