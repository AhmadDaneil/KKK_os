<!DOCTYPE html>
@php
    $dashboardUser = auth()->user();
    $canMonitorOperations = $dashboardUser->canMonitorAllDepartments();
    $canViewDesignQueue = $canMonitorOperations || $dashboardUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER);
    $canViewProductionQueue = $canMonitorOperations || $dashboardUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION);
    $roleLabels = [
        'OPERATION_MANAGEMENT' => 'Operations Management',
        'DESIGNER' => 'Designer',
        'PRODUCTION' => 'Production',
        'CUSTOMER_SERVICE' => 'Customer Service',
        'ADMIN' => 'Admin',
    ];
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-compact.css') }}?v={{ filemtime(public_path('css/dashboard-compact.css')) }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()]) data-staff-theme="{{ $dashboardUser->staff_theme }}">
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')

        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ $dashboardUser->isAdmin() ? __('ui.operations') : ($roleLabels[$dashboardUser->role] ?? 'Staff') }}</p><h1>{{ __('ui.staff_dashboard') }}</h1></div>
                <div class="staff-topbar-actions">
                    <form class="js-logout-form staff-logout-profile" method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit"><span class="staff-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
                </div>
            </header>

            <main class="staff-main staff-dashboard-main">
                <section class="staff-hero">
                    <div>
                        <p class="staff-kicker">{{ __('ui.welcome', ['name' => auth()->user()->name]) }}</p>
                        <h2>{{ __('ui.staff_intro_title') }}</h2>
                        <p>{{ __('ui.staff_intro') }}</p>
                    </div>
                </section>

                @if ($canMonitorOperations)
                    @php($attentionTotal = array_sum($attention))
                    <section class="staff-attention-panel" aria-labelledby="staff-attention-title">
                        <div class="staff-attention-heading">
                            <div><p class="staff-kicker">Operations Management Actions</p><h2 id="staff-attention-title">Needs Attention</h2></div>
                            <span class="staff-attention-total @if ($attentionTotal > 0) has-alert @endif">{{ $attentionTotal > 0 ? $attentionTotal.' actions' : 'All clear' }}</span>
                        </div>
                        <div class="staff-attention-grid">
                            @foreach ([
                                ['key' => 'pending_payments', 'label' => 'Payment Review', 'description' => 'Payments requiring approval or rejection', 'icon' => 'RM', 'params' => ['attention' => 'pending_payment']],
                                ['key' => 'design_queue', 'label' => 'Design Tasks', 'description' => 'Automatically assigned to the lead designer', 'icon' => 'RB', 'params' => ['workstream' => 'design']],
                                ['key' => 'printing_queue', 'label' => 'Production Tasks', 'description' => 'Automatically assigned to the lead production staff', 'icon' => 'CT', 'params' => ['workstream' => 'printing']],
                                ['key' => 'unassigned_packing', 'label' => 'Unassigned Packing', 'description' => 'Assign Operations Management to packing and fulfilment', 'icon' => 'PK', 'params' => ['workstream' => 'packing', 'attention' => 'unassigned_packing']],
                            ] as $item)
                                <a href="{{ route('staff.orders.index', $item['params']) }}" class="staff-attention-card @if ($attention[$item['key']] > 0) has-alert @else is-clear @endif">
                                    <span class="staff-attention-icon">{{ $item['icon'] }}</span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small></span><b>{{ $attention[$item['key']] }}</b>
                                </a>
                            @endforeach
                            @php($packingTotal = array_sum($packingAttention))
                            <a href="{{ route('staff.orders.index', ['attention' => 'packing_fulfilment']) }}" class="staff-attention-card @if ($packingTotal > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">PK</span><span><strong>Packing & Fulfilment Needs Attention</strong><small>{{ $packingTotal > 0 ? $packingAttention['ready'].' not started · '.$packingAttention['packing'].' being packed · '.$packingAttention['fulfilment'].' awaiting handover' : 'All packing and fulfilment work is complete' }}</small></span><b>{{ $packingTotal }}</b></a>
                        </div>
                    </section>
                @endif

                @if ($dashboardUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                    <section class="staff-attention-panel staff-designer-attention" aria-labelledby="designer-attention-title">
                        <div class="staff-attention-heading">
                            <div><p class="staff-kicker">Designer Actions</p><h2 id="designer-attention-title">Needs Attention</h2></div>
                            <span class="staff-attention-total @if ($designerAttention > 0) has-alert @endif">{{ $designerAttention > 0 ? $designerAttention.' tasks' : 'No action required' }}</span>
                        </div>
                        <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}" class="staff-attention-card @if ($designerAttention > 0) has-alert @else is-clear @endif">
                            <span class="staff-attention-icon">RB</span><span><strong>{{ __('ui.design_queue') }}</strong><small>{{ $designerAttention > 0 ? 'Orders ready to start or requiring corrections' : 'All design tasks are up to date' }}</small></span><b>{{ $designerAttention }}</b>
                        </a>
                    </section>
                @endif

                @if ($dashboardUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION))
                    @php($productionTotal = array_sum($productionAttention))
                    <section class="staff-attention-panel staff-production-attention" aria-labelledby="production-attention-title">
                        <div class="staff-attention-heading">
                            <div><p class="staff-kicker">Production Actions</p><h2 id="production-attention-title">Needs Attention</h2></div>
                            <span class="staff-attention-total @if ($productionTotal > 0) has-alert @endif">{{ $productionTotal > 0 ? $productionTotal.' tasks' : 'No action required' }}</span>
                        </div>
                        <div class="staff-attention-grid">
                            <a href="{{ route('staff.orders.index', ['workstream' => 'printing', 'status' => 'READY_FOR_PRINT']) }}" class="staff-attention-card @if ($productionAttention['ready'] > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">CT</span><span><strong>Printing Not Started</strong><small>Printing work ready to begin</small></span><b>{{ $productionAttention['ready'] }}</b></a>
                            <a href="{{ route('staff.orders.index', ['workstream' => 'printing', 'status' => 'PRINTING']) }}" class="staff-attention-card @if ($productionAttention['printing'] > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">CT</span><span><strong>Printing in Progress</strong><small>Upload progress and mark printing complete</small></span><b>{{ $productionAttention['printing'] }}</b></a>
                        </div>
                    </section>
                @endif

                <div class="staff-dashboard-sections">
                    @if ($canMonitorOperations)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">01</span><div><h2>Operations Management</h2><p>Monitor all departments, tasks and order statuses in one view.</p></div></div>
                        <a href="{{ route('staff.orders.index') }}" class="staff-feature-card"><span class="staff-feature-icon">TP</span><div><h3>All Orders</h3><p>View customer details, packages, payments and operational status.</p></div><span class="staff-card-arrow">→</span></a>
                    </section>
                    @endif

                    @if ($canViewDesignQueue)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">02</span><div><h2>Design</h2><p>Confirmed details, merging and design review.</p></div></div>
                        <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}" class="staff-feature-card"><span class="staff-feature-icon">RB</span><div><h3>{{ __('ui.design_queue') }}</h3><p>Manage orders ready for design, in progress or requiring corrections.</p></div><span class="staff-card-arrow">→</span></a>
                    </section>
                    @endif

                    @if ($canViewProductionQueue || $canMonitorOperations)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">03</span><div><h2>Production</h2><p>Printing, packing and customer handover.</p></div></div>
                        <div class="staff-feature-grid">
                            @if ($canViewProductionQueue)
                                <a href="{{ route('staff.orders.index', ['workstream' => 'printing']) }}" class="staff-feature-card"><span class="staff-feature-icon">CT</span><div><h3>Production</h3><p>Prepare both sides' cards and upload work progress.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                            @if ($canMonitorOperations)
                                <a href="{{ route('staff.orders.index', ['workstream' => 'packing']) }}" class="staff-feature-card"><span class="staff-feature-icon">PK</span><div><h3>Packing</h3><p>Review items and packing progress for each order.</p></div><span class="staff-card-arrow">→</span></a>
                                <a href="{{ route('staff.orders.index', ['workstream' => 'fulfilment']) }}" class="staff-feature-card"><span class="staff-feature-icon">PT</span><div><h3>Order Fulfilment</h3><p>Monitor courier handover and customer pickups.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                        </div>
                    </section>
                    @endif
                </div>
            </main>
        </div>
    </div>
    @include('staff.partials.logout-confirmation')
</body>
</html>
