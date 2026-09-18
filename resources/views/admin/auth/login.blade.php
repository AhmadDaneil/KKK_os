<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-login-page">
    <main class="admin-login-card">
        <div class="admin-login-mark">KKK</div>
        <p class="admin-eyebrow">KingKadKahwin</p>
        <h1>Admin Login</h1>
        <p class="admin-login-copy">Log masuk untuk mengurus akaun staff dan memantau keseluruhan operasi.</p>

        @if ($errors->any())<div class="admin-alert admin-alert-error">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <label for="email">Email Admin</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            <label for="password">Kata Laluan</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            <button class="admin-button admin-button-primary admin-login-submit" type="submit">Log Masuk</button>
        </form>
        <a class="admin-login-alt" href="{{ route('staff.login') }}">Pergi ke Staff Login</a>
    </main>
</body>
</html>
