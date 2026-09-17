<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk Staff - KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body class="staff-login-page">
    <main class="staff-login-card">
        <div class="staff-login-brand">
            <span class="staff-login-kicker">King Kad Kahwin</span>
            <h1>Staff Login</h1>
            <p>Log masuk untuk mengakses tugasan Operation Management, Design dan Production.</p>
        </div>

        @if ($errors->any())
            <div class="staff-alert staff-alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('staff.login.store') }}">
            @csrf
            <div class="staff-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>
            <div class="staff-field">
                <label for="password">Kata Laluan</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="staff-button staff-button-primary staff-login-submit">Log Masuk</button>
        </form>
    </main>
</body>
</html>
