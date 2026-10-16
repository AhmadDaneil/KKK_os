<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>
<body class="login-page login-page--admin">
    <main class="login-shell">
        <section class="login-story" aria-label="King Kad Kahwin">
            <div class="login-brand"><img class="login-mark login-mark--image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="47" height="47" decoding="async"><div><strong>King Kad Kahwin</strong><span>OPERATIONS SYSTEM</span></div></div>
            <div class="login-story-content"><span class="login-eyebrow">SECURE ACCESS</span><h2>New password. <br>Access restored.</h2><p>Choose a strong password and keep it secure.</p></div>
            <div class="login-story-footer"><span class="login-dot"></span> One team. One purpose.</div>
            <div class="login-orbit login-orbit--one" aria-hidden="true"></div><div class="login-orbit login-orbit--two" aria-hidden="true"></div>
        </section>
        <section class="login-panel" aria-labelledby="reset-password-title">
            <div class="login-heading"><span class="login-eyebrow">RESET PASSWORD</span><h1 id="reset-password-title">New password<span>.</span></h1><p>Your password must be at least 8 characters long.</p></div>
            @if ($errors->any())<div class="login-alert" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.password.update') }}" class="login-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="login-field"><label for="email">Admin Email</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
                <div class="login-field"><label for="password">New Password</label><input id="password" type="password" name="password" required autocomplete="new-password"></div>
                <div class="login-field"><label for="password_confirmation">Confirm New Password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
                <button type="submit" class="login-submit">Save Password <span aria-hidden="true">→</span></button>
            </form>
        </section>
    </main>
    <footer class="login-footer">KING KAD KAHWIN <span>·</span> Your team workspace</footer>
    @include('partials.malay-validation')
</body>
</html>
