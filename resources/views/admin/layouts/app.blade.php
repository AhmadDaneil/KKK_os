<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin Dashboard') — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="admin-brand-mark">KKK</span>
            <span><strong>KKK OS</strong><small>Administration</small></span>
        </a>

        <nav class="admin-nav" aria-label="Admin navigation">
            <p>Management</p>
            <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])><span>OV</span>Overview</a>
            <a href="{{ route('admin.staff.index') }}" @class(['is-active' => request()->routeIs('admin.staff.*')])><span>ST</span>Staff & Akses</a>
            <p>Operations</p>
            <a href="{{ route('staff.orders.index') }}"><span>OR</span>Semua Orders</a>
            <a href="{{ route('staff.orders.index', ['attention' => 'pending_payment']) }}"><span>RM</span>Semakan Bayaran</a>
            <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}"><span>DE</span>Design Queue</a>
            <a href="{{ route('staff.orders.index', ['workstream' => 'printing']) }}"><span>PR</span>Printing Queue</a>
            <a href="{{ route('staff.orders.index', ['workstream' => 'packing']) }}"><span>PA</span>Packing Queue</a>
        </nav>

        <div class="admin-profile">
            <span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></div>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <div><p>KingKadKahwin</p><h1>@yield('heading', 'Admin Dashboard')</h1></div>
            <div class="admin-topbar-actions">
                <a href="{{ route('staff.dashboard') }}" class="admin-button admin-button-secondary">Staff Dashboard</a>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="admin-button" type="submit">Log Keluar</button></form>
            </div>
        </header>

        <main class="admin-main">
            @if (session('admin_success'))
                <div class="admin-alert admin-alert-success">{{ session('admin_success') }}</div>
            @endif
            @if ($errors->any())
                <div class="admin-alert admin-alert-error"><strong>Tindakan tidak dapat disimpan.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
