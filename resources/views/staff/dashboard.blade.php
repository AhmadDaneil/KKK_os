<!DOCTYPE html>
@php
    $dashboardUser = auth()->user();
    $canMonitorOperations = $dashboardUser->canMonitorAllDepartments();
    $canViewDesignQueue = $canMonitorOperations || $dashboardUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER);
    $canViewProductionQueue = $canMonitorOperations || $dashboardUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION);
@endphp
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/language-toggle.css') }}?v={{ filemtime(public_path('css/language-toggle.css')) }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()]) data-staff-theme="{{ $dashboardUser->staff_theme }}">
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')

        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ $dashboardUser->isAdmin() ? __('ui.operations') : str_replace('_', ' ', $dashboardUser->role) }}</p><h1>{{ __('ui.staff_dashboard') }}</h1></div>
                <form class="js-logout-form staff-logout-profile" method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit"><span class="staff-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
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
                            <div><p class="staff-kicker">Tindakan Operation Management</p><h2 id="staff-attention-title">Memerlukan Perhatian</h2></div>
                            <span class="staff-attention-total @if ($attentionTotal > 0) has-alert @endif">{{ $attentionTotal > 0 ? $attentionTotal.' tindakan' : 'Semua selesai' }}</span>
                        </div>
                        <div class="staff-attention-grid">
                            @foreach ([
                                ['key' => 'pending_payments', 'label' => 'Semakan Bayaran', 'description' => 'Bayaran yang perlu diluluskan atau ditolak', 'icon' => 'RM', 'params' => ['attention' => 'pending_payment']],
                                ['key' => 'unassigned_design', 'label' => 'Design Belum Assign', 'description' => 'Assign designer untuk mula kerja artwork', 'icon' => 'DE', 'params' => ['workstream' => 'design', 'attention' => 'unassigned_design']],
                                ['key' => 'unassigned_printing', 'label' => 'Production Belum Assign', 'description' => 'Assign staff printing untuk mula production', 'icon' => 'PR', 'params' => ['workstream' => 'printing', 'attention' => 'unassigned_printing']],
                                ['key' => 'unassigned_packing', 'label' => 'Packing Belum Assign', 'description' => 'Assign OM untuk packing dan fulfilment', 'icon' => 'PA', 'params' => ['workstream' => 'packing', 'attention' => 'unassigned_packing']],
                            ] as $item)
                                <a href="{{ route('staff.orders.index', $item['params']) }}" class="staff-attention-card @if ($attention[$item['key']] > 0) has-alert @else is-clear @endif">
                                    <span class="staff-attention-icon">{{ $item['icon'] }}</span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['description'] }}</small></span><b>{{ $attention[$item['key']] }}</b>
                                </a>
                            @endforeach
                            @php($packingTotal = array_sum($packingAttention))
                            <div class="staff-attention-card @if ($packingTotal > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">PA</span><span><strong>Packing & Fulfilment Perlu Tindakan</strong><small>{{ $packingTotal > 0 ? $packingAttention['ready'].' belum mula · '.$packingAttention['packing'].' sedang packing · '.$packingAttention['fulfilment'].' perlu diserah' : 'Semua packing dan fulfilment telah selesai' }}</small></span><b>{{ $packingTotal }}</b></div>
                        </div>
                    </section>
                @endif

                @if ($dashboardUser->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                    <section class="staff-attention-panel staff-designer-attention" aria-labelledby="designer-attention-title">
                        <div class="staff-attention-heading">
                            <div><p class="staff-kicker">Tindakan Designer</p><h2 id="designer-attention-title">Memerlukan Perhatian</h2></div>
                            <span class="staff-attention-total @if ($designerAttention > 0) has-alert @endif">{{ $designerAttention > 0 ? $designerAttention.' tugasan' : 'Tiada tindakan diperlukan' }}</span>
                        </div>
                        <div class="staff-attention-card @if ($designerAttention > 0) has-alert @else is-clear @endif">
                            <span class="staff-attention-icon">RB</span><span><strong>{{ __('ui.design_queue') }}</strong><small>{{ $designerAttention > 0 ? 'Tempahan sedia untuk dimulakan atau memerlukan pembetulan' : 'Semua tugasan reka bentuk telah dikemas kini' }}</small></span><b>{{ $designerAttention }}</b>
                        </div>
                    </section>
                @endif

                @if ($dashboardUser->hasStaffRole(\App\Models\User::ROLE_PRODUCTION))
                    @php($productionTotal = array_sum($productionAttention))
                    <section class="staff-attention-panel staff-production-attention" aria-labelledby="production-attention-title">
                        <div class="staff-attention-heading">
                            <div><p class="staff-kicker">Tindakan Production</p><h2 id="production-attention-title">Memerlukan Perhatian</h2></div>
                            <span class="staff-attention-total @if ($productionTotal > 0) has-alert @endif">{{ $productionTotal > 0 ? $productionTotal.' tugasan' : 'Tiada tindakan diperlukan' }}</span>
                        </div>
                        <div class="staff-attention-grid">
                            <div class="staff-attention-card @if ($productionAttention['ready'] > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">PR</span><span><strong>Printing Belum Mula</strong><small>Kerja printing yang boleh dimulakan</small></span><b>{{ $productionAttention['ready'] }}</b></div>
                            <div class="staff-attention-card @if ($productionAttention['printing'] > 0) has-alert @else is-clear @endif"><span class="staff-attention-icon">PR</span><span><strong>Printing Sedang Berjalan</strong><small>Muat naik progress dan tandakan printed</small></span><b>{{ $productionAttention['printing'] }}</b></div>
                        </div>
                    </section>
                @endif

                <div class="staff-dashboard-sections">
                    @if ($canMonitorOperations)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">01</span><div><h2>Operation Management</h2><p>Pantau semua jabatan, tugasan dan status order dari satu paparan.</p></div></div>
                        <a href="{{ route('staff.orders.index') }}" class="staff-feature-card"><span class="staff-feature-icon">OR</span><div><h3>Semua Orders</h3><p>Lihat identiti pelanggan, pakej, bayaran dan status operasi.</p></div><span class="staff-card-arrow">→</span></a>
                    </section>
                    @endif

                    @if ($canViewDesignQueue)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">02</span><div><h2>Design</h2><p>Data disahkan, merge job dan semakan artwork.</p></div></div>
                        <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}" class="staff-feature-card"><span class="staff-feature-icon">RB</span><div><h3>{{ __('ui.design_queue') }}</h3><p>Urus tempahan yang sedia untuk reka bentuk, sedang disediakan atau memerlukan pembetulan.</p></div><span class="staff-card-arrow">→</span></a>
                    </section>
                    @endif

                    @if ($canViewProductionQueue || $canMonitorOperations)
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">03</span><div><h2>Production</h2><p>Cetakan, pembungkusan dan serahan kepada pelanggan.</p></div></div>
                        <div class="staff-feature-grid">
                            @if ($canViewProductionQueue)
                                <a href="{{ route('staff.orders.index', ['workstream' => 'printing']) }}" class="staff-feature-card"><span class="staff-feature-icon">PR</span><div><h3>Production</h3><p>Sediakan hanger Pengantin Lelaki/Perempuan dan muat naik kemajuan kerja.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                            @if ($canMonitorOperations)
                                <a href="{{ route('staff.orders.index', ['workstream' => 'packing']) }}" class="staff-feature-card"><span class="staff-feature-icon">PA</span><div><h3>Packing</h3><p>Semak item dan kemajuan pembungkusan setiap order.</p></div><span class="staff-card-arrow">→</span></a>
                                <a href="{{ route('staff.orders.index', ['workstream' => 'fulfilment']) }}" class="staff-feature-card"><span class="staff-feature-icon">FU</span><div><h3>Fulfilment</h3><p>Pantau serahan courier dan kutipan pelanggan.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                        </div>
                    </section>
                    @endif
                </div>
            </main>
        </div>
    </div>
    @include('staff.partials.logout-confirmation')
    <script src="{{ asset('js/language-runtime.js') }}?v={{ filemtime(public_path('js/language-runtime.js')) }}"></script>
</body>
</html>
