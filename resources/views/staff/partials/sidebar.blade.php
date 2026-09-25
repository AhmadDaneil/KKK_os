@php
    $staffUser = auth()->user();
    $activeWorkstream = request()->query('workstream');
    $isOverview = request()->attributes->get('staff_overview_mode', false);
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $roleLabel = match ($staffUser->role) {
        \App\Models\User::ROLE_OM => 'OM · Packing & Fulfilment',
        \App\Models\User::ROLE_CUSTOMER_SERVICE => 'Customer Service',
        \App\Models\User::ROLE_PRODUCTION => 'Production',
        default => str_replace('_', ' ', $staffUser->role),
    };
@endphp

<aside class="staff-sidebar" id="staff-sidebar">
    <a href="{{ $staffUser->isAdmin() ? route('admin.dashboard') : route('staff.dashboard') }}" class="staff-sidebar-brand">
        <span class="staff-brand-mark">KKK</span>
        <span><strong>KKK OS</strong><small>{{ $staffUser->isAdmin() ? 'Admin Operations' : 'Staff Operations' }}</small></span>
    </a>

    <nav class="staff-nav" aria-label="Staff navigation">
        @if ($isAdminPortal)
            <section class="staff-nav-section">
                <h2>Management</h2>
                <a href="{{ route('admin.dashboard') }}" class="staff-nav-link"><span class="staff-nav-icon" aria-hidden="true">OV</span>Overview</a>
                <a href="{{ route('admin.staff.index') }}" class="staff-nav-link"><span class="staff-nav-icon" aria-hidden="true">ST</span>Staff &amp; Akses</a>
            </section>
        @endif

        <section class="staff-nav-section">
            <h2>{{ $isAdminPortal ? 'Operations' : 'Operation Management' }}</h2>
            @unless ($isAdminPortal)
                <a href="{{ route('staff.dashboard') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('staff.dashboard')])><span class="staff-nav-icon" aria-hidden="true">OV</span>Overview</a>
            @endunless
            <a href="{{ route($ordersIndexRoute) }}" @class(['staff-nav-link', 'is-active' => request()->routeIs($isAdminPortal ? 'admin.orders.*' : 'staff.orders.*') && ! $activeWorkstream])><span class="staff-nav-icon" aria-hidden="true">OR</span>Semua Orders</a>
        </section>

        <section class="staff-nav-section">
            <h2>Design</h2>
            @if ($staffUser->isAdmin() || $staffUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'design']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'design'])><span class="staff-nav-icon" aria-hidden="true">DE</span>Design Queue</a>
            @endif
        </section>

        <section class="staff-nav-section">
            <h2>Production</h2>
            @if ($staffUser->isAdmin() || $staffUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION))
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'printing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'printing'])><span class="staff-nav-icon" aria-hidden="true">PR</span>Production Queue</a>
            @endif

            @if ($staffUser->isOperationManagement())
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'packing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'packing'])><span class="staff-nav-icon" aria-hidden="true">PA</span>OM Packing Queue</a>
            @endif

            @if ($isOverview || $staffUser->isAdmin())
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'fulfilment']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'fulfilment'])><span class="staff-nav-icon" aria-hidden="true">FU</span>Fulfilment</a>
            @endif
        </section>
    </nav>

    @unless ($isOverview)
        <div class="staff-sidebar-user">
            <span class="staff-user-avatar">{{ strtoupper(substr($staffUser->name, 0, 1)) }}</span>
            <span><strong>{{ $staffUser->name }}</strong><small>{{ $roleLabel }}</small></span>
        </div>
    @endunless
</aside>
