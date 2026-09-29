<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard Admin') — KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="admin-brand-mark">KKK</span>
            <span><strong>KKK OS</strong><small>{{ __('ui.administration') }}</small></span>
        </a>

        <nav class="admin-nav" aria-label="Navigasi admin">
            <p>{{ __('ui.management') }}</p>
            <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])><span>OV</span>{{ __('ui.overview') }}</a>
            <a href="{{ route('admin.dashboard') }}#sales-analysis"><span>SA</span>{{ __('ui.sales_analysis') }}</a>
            <a href="{{ route('admin.staff.index') }}" @class(['is-active' => request()->routeIs('admin.staff.*')])><span>ST</span>{{ __('ui.staff_access') }}</a>
            <p>{{ __('ui.operations') }}</p>
            <a href="{{ route('admin.orders.index') }}"><span>OR</span>{{ __('ui.all_orders') }}</a>
            <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}"><span>RM</span>{{ __('ui.payment_review') }}</a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}"><span>DE</span>{{ __('ui.design_queue') }}</a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}"><span>PR</span>{{ __('ui.production_queue') }}</a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}"><span>PA</span>{{ __('ui.packing_queue') }}</a>
        </nav>

        <div class="admin-profile">
            <span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><small>Pentadbir</small></div>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <div><p>KingKadKahwin</p><h1>@yield('heading', 'Dashboard Admin')</h1></div>
            <div class="admin-topbar-actions">
                <form class="js-logout-form admin-logout-profile" method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit"><span class="admin-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
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
@include('staff.partials.logout-confirmation')
@stack('scripts')
<script src="{{ asset('js/language-runtime.js') }}?v={{ filemtime(public_path('js/language-runtime.js')) }}"></script>
</body>
</html>
