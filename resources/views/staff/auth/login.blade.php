<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>
<body class="login-page login-page--staff">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand">
                <img class="login-mark login-mark--image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="47" height="47" decoding="async">
                <div><strong>King Kad Kahwin</strong><span>OPERATIONS SYSTEM</span></div>
            </div>
            <div class="login-story-content">
                <span class="login-eyebrow">WORKING TOGETHER</span>
                <h2>Every task <br>starts here.</h2>
                <p>From the first idea to the final product, manage your daily work with your team.</p>
                <div class="login-tags"><span>Operation</span><span>Design</span><span>Production</span></div>
            </div>
            <div class="login-story-footer"><span class="login-dot"></span> One team. One purpose.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div>
            <div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-heading">
                <span class="login-eyebrow">WELCOME BACK</span>
                <h1 id="login-title">Staff Login<span>.</span></h1>
                <p>Log in to access your tasks and continue your work.</p>
            </div>
            @if ($errors->any())
                <div class="login-alert" role="alert">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('staff.login.store') }}" class="login-form">
                @csrf
                <div class="login-field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="name@kingkadkahwin.com" required autofocus autocomplete="email">
                </div>
                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-visibility-toggle" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.75"></circle></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="login-submit">Log In <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help">Having trouble logging in? Contact the system administrator.</p>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Your team workspace</footer>
    @include('partials.malay-validation')
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const passwordInput = document.getElementById(button.getAttribute('aria-controls'));
                const willShowPassword = passwordInput.type === 'password';

                passwordInput.type = willShowPassword ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(willShowPassword));
                button.setAttribute('aria-label', willShowPassword ? 'Hide password' : 'Show password');
            });
        });
    </script>
</body>
</html>
