@php
    $staffUser = auth()->user();
    $activeWorkstream = request()->query('workstream');
    $isOverview = request()->attributes->get('staff_overview_mode', false);
@endphp

<aside class="staff-sidebar" id="staff-sidebar">
    <a href="{{ route('staff.dashboard') }}" class="staff-sidebar-brand">
        <span class="staff-brand-mark">KKK</span>
        <span><strong>KKK OS</strong><small>Staff Operations</small></span>
    </a>

    <nav class="staff-nav" aria-label="Staff navigation">
        <section class="staff-nav-section">
            <h2>Operation Management</h2>
            <a href="{{ route('staff.dashboard') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('staff.dashboard')])><span class="staff-nav-icon" aria-hidden="true">OV</span>Overview</a>
            <a href="{{ route('staff.orders.index') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('staff.orders.*') && ! $activeWorkstream])><span class="staff-nav-icon" aria-hidden="true">OR</span>Semua Orders</a>
        </section>

        <section class="staff-nav-section">
            <h2>Design</h2>
            @if ($isOverview || $staffUser->isAdmin() || $staffUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'design'])><span class="staff-nav-icon" aria-hidden="true">DE</span>Design Queue</a>
            @endif
        </section>

        <section class="staff-nav-section">
            <h2>Production</h2>
            @if ($isOverview || $staffUser->isAdmin() || $staffUser->hasStaffRole(\App\Models\User::ROLE_PRINTING))
                <a href="{{ route('staff.orders.index', ['workstream' => 'printing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'printing'])><span class="staff-nav-icon" aria-hidden="true">PR</span>Printing Queue</a>
            @endif

            @if ($isOverview || $staffUser->isAdmin() || $staffUser->hasStaffRole(\App\Models\User::ROLE_PACKING))
                <a href="{{ route('staff.orders.index', ['workstream' => 'packing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'packing'])><span class="staff-nav-icon" aria-hidden="true">PA</span>Packing Queue</a>
            @endif

            @if ($isOverview || $staffUser->isAdmin())
                <a href="{{ route('staff.orders.index', ['workstream' => 'fulfilment']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'fulfilment'])><span class="staff-nav-icon" aria-hidden="true">FU</span>Fulfilment</a>
            @endif
        </section>
    </nav>

    @unless ($isOverview)
        <div class="staff-sidebar-user">
            <span class="staff-user-avatar">{{ strtoupper(substr($staffUser->name, 0, 1)) }}</span>
            <span><strong>{{ $staffUser->name }}</strong><small>{{ str_replace('_', ' ', $staffUser->role) }}</small></span>
        </div>
    @endunless
</aside>
