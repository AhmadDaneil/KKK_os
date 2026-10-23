<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Admin Password — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>
<body class="login-page login-page--admin">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand"><img class="login-mark login-mark--image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="47" height="47" decoding="async"><div><strong>King Kad Kahwin</strong><span>OPERATIONS SYSTEM</span></div></div>
            <div class="login-story-content"><span class="login-eyebrow">SECURE ACCESS</span><h2>Reset access. <br>Continue operations.</h2><p>A secure link will be sent to an active admin email address.</p></div>
            <div class="login-story-footer"><span class="login-dot"></span> One team. One purpose.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div><div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="forgot-password-title">
            <div class="login-heading"><span class="login-eyebrow">FORGOT PASSWORD</span><h1 id="forgot-password-title">Reset access<span>.</span></h1><p>Enter your admin email to receive a reset link.</p></div>
            @if (session('status'))<div class="login-alert login-alert--success" role="status">{{ session('status') }}</div>@endif
            @error('email')<div class="login-alert" role="alert">{{ $message }}</div>@enderror
            @if (config('mail.default') === 'log')<div class="login-alert login-alert--info" role="status">Local email is logged instead of delivered. After requesting a link, open <code>storage/logs/laravel.log</code> and use the latest admin password reset link.</div>@endif
            <form method="POST" action="{{ route('admin.password.email') }}" class="login-form">
                @csrf
                <div class="login-field"><label for="email">Admin Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="name@kingkadkahwin.com" required autofocus autocomplete="email"></div>
                <button type="submit" class="login-submit">Send Reset Link <span aria-hidden="true">→</span></button>
            </form>
            <p class="login-help"><a href="{{ route('admin.login') }}">Back to Admin Login</a></p>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Your team workspace</footer>
    @include('partials.malay-validation')
</body>
</html>
