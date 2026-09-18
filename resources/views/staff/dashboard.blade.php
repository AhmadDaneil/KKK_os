<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')

        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : 'Operation Management' }}</p><h1>{{ auth()->user()->isAdmin() ? 'Admin Operations Dashboard' : 'Staff Dashboard' }}</h1></div>
                <form method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit" class="staff-button staff-button-small">Log Keluar</button></form>
            </header>

            <main class="staff-main staff-dashboard-main">
                <section class="staff-hero">
                    <div>
                        <p class="staff-kicker">Selamat datang, {{ auth()->user()->name }}</p>
                        <h2>Operasi yang jelas, daripada order hingga siap.</h2>
                        <p>Pantau tugasan mengikut role anda tanpa mengubah aliran kerja yang ditetapkan dalam Master Blueprint.</p>
                    </div>
                    <a href="{{ route('staff.orders.index') }}" class="staff-button staff-button-primary">Buka senarai order</a>
                </section>

                <div class="staff-dashboard-sections">
                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">01</span><div><h2>Operation Management</h2><p>Rujukan utama untuk semua order dan status semasa.</p></div></div>
                        <a href="{{ route('staff.orders.index') }}" class="staff-feature-card"><span class="staff-feature-icon">OR</span><div><h3>Semua Orders</h3><p>Lihat identiti pelanggan, pakej, bayaran dan status operasi.</p></div><span class="staff-card-arrow">→</span></a>
                    </section>

                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">02</span><div><h2>Design</h2><p>Data disahkan, merge job dan semakan artwork.</p></div></div>
                        @if (auth()->user()->isAdmin() || auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                            <a href="{{ route('staff.orders.index', ['workstream' => 'design']) }}" class="staff-feature-card"><span class="staff-feature-icon">DE</span><div><h3>Design Queue</h3><p>Urus order yang sedia untuk design, sedang disediakan atau memerlukan pembetulan.</p></div><span class="staff-card-arrow">→</span></a>
                        @else
                            <p class="staff-access-note">Tiada tugasan untuk role anda dalam bahagian ini.</p>
                        @endif
                    </section>

                    <section class="staff-dashboard-group">
                        <div class="staff-dashboard-group-heading"><span class="staff-group-number">03</span><div><h2>Production</h2><p>Cetakan, pembungkusan dan serahan kepada pelanggan.</p></div></div>
                        <div class="staff-feature-grid">
                            @if (auth()->user()->isAdmin() || auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRINTING))
                                <a href="{{ route('staff.orders.index', ['workstream' => 'printing']) }}" class="staff-feature-card"><span class="staff-feature-icon">PR</span><div><h3>Printing</h3><p>Order berbayar yang menunggu atau sedang dicetak.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                            @if (auth()->user()->isOperationManagement() || auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING))
                                <a href="{{ route('staff.orders.index', ['workstream' => 'packing']) }}" class="staff-feature-card"><span class="staff-feature-icon">PA</span><div><h3>Packing</h3><p>Semak item dan kemajuan pembungkusan setiap order.</p></div><span class="staff-card-arrow">→</span></a>
                            @endif
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
