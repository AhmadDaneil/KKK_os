@php
    $staffUser = auth()->user();
    $activeWorkstream = request()->query('workstream');
    $isOverview = request()->attributes->get('staff_overview_mode', false);
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $canMonitorOperations = $staffUser->canMonitorAllDepartments();
    $canViewDesignQueue = $canMonitorOperations || $staffUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER);
    $canViewProductionQueue = $canMonitorOperations || $staffUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION);
    $roleLabel = $staffUser->isAdmin() ? 'Administrator' : match ($staffUser->role) {
        \App\Models\User::ROLE_OM => 'Operation Management',
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
                <a href="{{ route('admin.dashboard') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('admin.dashboard')])><span class="staff-nav-icon" aria-hidden="true">OV</span>Overview</a>
                <a href="{{ route('admin.staff.index') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('admin.staff.*')])><span class="staff-nav-icon" aria-hidden="true">ST</span>Staff &amp; Akses</a>
            </section>

            <section class="staff-nav-section">
                <h2>Operations</h2>
                <a href="{{ route('admin.orders.index') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('admin.orders.*') && ! $activeWorkstream && ! request()->query('attention')])><span class="staff-nav-icon" aria-hidden="true">OR</span>Semua Orders</a>
                <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}" @class(['staff-nav-link', 'is-active' => request()->query('attention') === 'pending_payment'])><span class="staff-nav-icon" aria-hidden="true">RM</span>Semakan Bayaran</a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'design'])><span class="staff-nav-icon" aria-hidden="true">DE</span>Design Queue</a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'printing'])><span class="staff-nav-icon" aria-hidden="true">PR</span>Production Queue</a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'packing'])><span class="staff-nav-icon" aria-hidden="true">PA</span>Packing Queue</a>
            </section>
        @else
            <section class="staff-nav-section">
                <h2>Operation Management</h2>
                <a href="{{ route('staff.dashboard') }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('staff.dashboard')])><span class="staff-nav-icon" aria-hidden="true">OV</span>Overview</a>
                <a href="{{ route($ordersIndexRoute) }}" @class(['staff-nav-link', 'is-active' => request()->routeIs('staff.orders.*') && ! $activeWorkstream])><span class="staff-nav-icon" aria-hidden="true">OR</span>Semua Orders</a>
            </section>

        @if ($canViewDesignQueue)
            <section class="staff-nav-section">
                <h2>Design</h2>
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'design']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'design'])><span class="staff-nav-icon" aria-hidden="true">DE</span>Design Queue</a>
            </section>
        @endif

        @if ($canViewProductionQueue || $canMonitorOperations)
            <section class="staff-nav-section">
                <h2>Production</h2>
                @if ($canViewProductionQueue)
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'printing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'printing'])><span class="staff-nav-icon" aria-hidden="true">PR</span>Production Queue</a>
                @endif

                @if ($canMonitorOperations)
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'packing']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'packing'])><span class="staff-nav-icon" aria-hidden="true">PA</span>Packing Queue</a>
                @endif

                @if ($canMonitorOperations)
                <a href="{{ route($ordersIndexRoute, ['workstream' => 'fulfilment']) }}" @class(['staff-nav-link', 'is-active' => $activeWorkstream === 'fulfilment'])><span class="staff-nav-icon" aria-hidden="true">FU</span>Fulfilment</a>
                @endif
            </section>
        @endif
        @endif
    </nav>

    @if (! $isAdminPortal)
        <section class="staff-sidebar-theme" aria-labelledby="staff-sidebar-theme-title">
            <p id="staff-sidebar-theme-title">Personalisasi</p>
            <form method="POST" action="{{ route('staff.theme.update') }}">
                @csrf
                @method('PUT')
                <label for="staff-sidebar-theme">Tema paparan</label>
                <div>
                    <select id="staff-sidebar-theme" name="staff_theme">
                        <option value="default" @selected($staffUser->staff_theme === 'default')>Default Green</option>
                        <option value="modern_blue" @selected($staffUser->staff_theme === 'modern_blue')>Modern Blue</option>
                        <option value="indigo_violet" @selected($staffUser->staff_theme === 'indigo_violet')>Indigo &amp; Violet</option>
                        <option value="warm_orange" @selected($staffUser->staff_theme === 'warm_orange')>Warm Orange</option>
                        <option value="amber_gold" @selected($staffUser->staff_theme === 'amber_gold')>Amber / Gold</option>
                        <option value="dusty_rose" @selected($staffUser->staff_theme === 'dusty_rose')>Charcoal / Lime</option>
                        <option value="rose_burgundy" @selected($staffUser->staff_theme === 'rose_burgundy')>Rose / Burgundy</option>
                    </select>
                    <button type="submit">Simpan</button>
                </div>
            </form>
        </section>
    @endif

    @unless ($isOverview)
        <div class="staff-sidebar-user">
            <span class="staff-user-avatar">{{ strtoupper(substr($staffUser->name, 0, 1)) }}</span>
            <span><strong>{{ $staffUser->name }}</strong><small>{{ $roleLabel }}</small></span>
        </div>
    @endunless
</aside>
