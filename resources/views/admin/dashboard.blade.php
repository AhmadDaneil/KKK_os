@extends('admin.layouts.app')

@section('title', 'Admin Overview')
@section('heading', 'Overview')

@section('content')
    @php($attentionTotal = array_sum($attention))

    <section class="admin-welcome">
        <div>
            <p class="admin-eyebrow">Selamat datang, {{ auth()->user()->name }}</p>
            <h2>Semua operasi dalam satu paparan.</h2>
            <p>Pantau tempahan, pembayaran dan beban kerja setiap jabatan sebelum membuka butiran operasi.</p>
            <form class="admin-quick-order-search" method="GET" action="{{ route('admin.orders.find') }}">
                <label for="dashboard-order-id">Cari pantas Order ID</label>
                <div>
                    <input id="dashboard-order-id" name="order_id" value="{{ old('order_id') }}" placeholder="Contoh: KKK-260924-0003" maxlength="32" required>
                    <button class="admin-button admin-button-gold admin-action-hover" type="submit">Buka Order</button>
                </div>
                @error('order_id')
                    <span class="admin-quick-search-error">{{ $message }}</span>
                @enderror
            </form>
        </div>
        <a class="admin-button admin-button-gold admin-action-hover" href="{{ route('admin.staff.index') }}">Urus Staff</a>
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan operasi">
        <article><span>Jumlah Order</span><strong data-stat-value="{{ $statistics['orders_total'] }}">{{ number_format($statistics['orders_total']) }}</strong><small>Semua rekod tempahan</small></article>
        <article><span>Order Aktif</span><strong data-stat-value="{{ $statistics['orders_active'] }}">{{ number_format($statistics['orders_active']) }}</strong><small>Belum selesai atau diarkib</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_deposits'] > 0, 'is-clear' => $statistics['pending_deposits'] === 0])><span>Deposit Belum Semak</span><strong data-stat-value="{{ $statistics['pending_deposits'] }}">{{ number_format($statistics['pending_deposits']) }}</strong><small>{{ $statistics['pending_deposits'] > 0 ? 'Perlu disemak segera' : 'Tiada semakan tertunggak' }}</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_balances'] > 0, 'is-clear' => $statistics['pending_balances'] === 0])><span>Baki Belum Semak</span><strong data-stat-value="{{ $statistics['pending_balances'] }}">{{ number_format($statistics['pending_balances']) }}</strong><small>{{ $statistics['pending_balances'] > 0 ? 'Perlu disemak segera' : 'Tiada semakan tertunggak' }}</small></article>
        <article class="is-success"><span>Order Selesai</span><strong data-stat-value="{{ $statistics['orders_completed'] }}">{{ number_format($statistics['orders_completed']) }}</strong><small>Keseluruhan fulfilment selesai</small></article>
    </section>

    <section class="admin-panel admin-sales-panel" id="sales-analysis">
        <div class="admin-panel-heading">
            <div><p class="admin-eyebrow">Prestasi Perniagaan</p><h2>Sales Analysis</h2><small>Jumlah kutipan berdasarkan transaksi yang telah disahkan sebagai PAID.</small></div>
            <span class="admin-sales-period">{{ now()->format('Y') }}</span>
        </div>

        <div class="admin-sales-summary">
            <article><span>Hari Ini</span><strong>RM {{ number_format($sales['summary']['today'], 2) }}</strong><small>{{ $sales['summary']['today_transactions'] }} transaksi</small></article>
            <article><span>Bulan Ini</span><strong>RM {{ number_format($sales['summary']['month'], 2) }}</strong><small @class(['is-up' => $sales['summary']['month_change'] >= 0, 'is-down' => $sales['summary']['month_change'] < 0])>{{ $sales['summary']['month_change'] >= 0 ? '+' : '' }}{{ number_format($sales['summary']['month_change'], 1) }}% berbanding bulan lalu</small></article>
            <article><span>Tahun Ini</span><strong>RM {{ number_format($sales['summary']['year'], 2) }}</strong><small>{{ $sales['summary']['year_orders'] }} order · {{ $sales['summary']['year_transactions'] }} transaksi</small></article>
            <article><span>Purata Setiap Order</span><strong>RM {{ number_format($sales['summary']['average_order_value'], 2) }}</strong><small>Berdasarkan kutipan bulan ini</small></article>
            <article class="is-pending"><span>Bayaran Pending</span><strong>RM {{ number_format($sales['summary']['pending_amount'], 2) }}</strong><small>{{ $sales['summary']['pending_count'] }} transaksi perlu disemak</small></article>
        </div>

        <div class="admin-sales-chart-grid">
            <article class="admin-sales-chart">
                <header><div><strong>Trend Harian</strong><small>14 hari terakhir</small></div></header>
                <div class="admin-bar-chart is-daily" aria-label="Graf jualan harian">
                    @foreach ($sales['daily'] as $point)
                        <div class="admin-bar-column" title="{{ $point['label'] }}: RM {{ number_format($point['amount'], 2) }}"><span class="admin-bar-value">{{ $point['amount'] > 0 ? 'RM'.number_format($point['amount'], 0) : '' }}</span><i style="height: {{ $point['percentage'] }}%"></i><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
            </article>

            <article class="admin-sales-chart">
                <header><div><strong>Trend Bulanan</strong><small>Januari hingga Disember {{ now()->year }}</small></div>@if ($sales['summary']['best_month'])<span>Terbaik: {{ $sales['summary']['best_month']['label'] }}</span>@endif</header>
                <div class="admin-bar-chart" aria-label="Graf jualan bulanan">
                    @foreach ($sales['monthly'] as $point)
                        <div class="admin-bar-column" title="{{ $point['label'] }}: RM {{ number_format($point['amount'], 2) }}"><span class="admin-bar-value">{{ $point['amount'] > 0 ? 'RM'.number_format($point['amount'], 0) : '' }}</span><i style="height: {{ $point['percentage'] }}%"></i><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
            </article>
        </div>

        <div class="admin-sales-bottom-grid">
            <article>
                <h3>Jualan Tahunan</h3>
                <div class="admin-annual-list">
                    @foreach ($sales['annual'] as $point)
                        <div><span>{{ $point['label'] }}</span><i><b style="width: {{ $point['percentage'] }}%"></b></i><strong>RM {{ number_format($point['amount'], 2) }}</strong></div>
                    @endforeach
                </div>
            </article>
            <article>
                <h3>Pecahan Kutipan {{ now()->year }}</h3>
                <div class="admin-sales-breakdown">
                    @foreach ($sales['breakdown'] as $item)
                        <div><span>{{ $item['label'] }}<small>{{ $item['transactions'] }} transaksi</small></span><strong>RM {{ number_format($item['amount'], 2) }}</strong></div>
                    @endforeach
                </div>
            </article>
            <article class="admin-sales-note">
                <h3>Perkara Penting</h3>
                <ul>
                    <li><strong>{{ $sales['summary']['pending_count'] }}</strong> bayaran masih menunggu semakan.</li>
                    <li><strong>{{ $sales['summary']['year_orders'] }}</strong> order telah menyumbang kepada jualan tahun ini.</li>
                    <li>@if ($sales['summary']['best_month'])Bulan terbaik ialah <strong>{{ $sales['summary']['best_month']['label'] }}</strong> dengan RM {{ number_format($sales['summary']['best_month']['amount'], 2) }}.@elseBelum ada jualan berbayar untuk tahun ini.@endif</li>
                </ul>
                @if ($sales['summary']['pending_count'] > 0)<a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}">Semak pembayaran sekarang →</a>@endif
            </article>
        </div>
    </section>

    <section class="admin-panel admin-attention-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Tindakan Admin</p><h2>Memerlukan Perhatian</h2></div><span @class(['admin-attention-total', 'has-alert' => $attentionTotal > 0, 'is-clear' => $attentionTotal === 0])>{{ $attentionTotal > 0 ? $attentionTotal.' tindakan' : 'Semua selesai' }}</span></div>
        <div class="admin-action-grid">
            <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}" @class(['has-alert' => $attention['pending_payments'] > 0, 'is-clear' => $attention['pending_payments'] === 0])><span class="action-icon is-payment">RM</span><div><strong>Semakan Pembayaran</strong><small>Deposit atau bayaran penuh yang masih pending</small></div><b>{{ $attention['pending_payments'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'design', 'attention' => 'unassigned_design']) }}" @class(['has-alert' => $attention['unassigned_design'] > 0, 'is-clear' => $attention['unassigned_design'] === 0])><span class="action-icon is-design">DE</span><div><strong>Design Belum Assign</strong><small>Assign designer supaya artwork boleh dimulakan</small></div><b>{{ $attention['unassigned_design'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'printing', 'attention' => 'unassigned_printing']) }}" @class(['has-alert' => $attention['unassigned_printing'] > 0, 'is-clear' => $attention['unassigned_printing'] === 0])><span class="action-icon is-printing">PR</span><div><strong>Production Belum Assign</strong><small>Assign staf production untuk menghasilkan hanger</small></div><b>{{ $attention['unassigned_printing'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'packing', 'attention' => 'unassigned_packing']) }}" @class(['has-alert' => $attention['unassigned_packing'] > 0, 'is-clear' => $attention['unassigned_packing'] === 0])><span class="action-icon is-packing">PA</span><div><strong>Packing Belum Assign</strong><small>Assign OM untuk packing dan fulfilment</small></div><b>{{ $attention['unassigned_packing'] }}</b></a>
        </div>
    </section>

    <div class="admin-content-grid">
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Workload</p><h2>Queue Semasa</h2></div><a href="{{ route('admin.orders.index') }}">Lihat semua →</a></div>
            <div class="admin-queue-list">
                <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}"><span class="queue-icon">DE</span><div><strong>Design</strong><small>Ready, sedang design atau pembetulan</small></div><b>{{ $queues['design'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}"><span class="queue-icon">PR</span><div><strong>Production</strong><small>Hanger sedang dihasilkan</small></div><b>{{ $queues['printing'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}"><span class="queue-icon">PA</span><div><strong>Packing</strong><small>Menunggu atau sedang dibungkus</small></div><b>{{ $queues['packing'] }}</b></a>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Team</p><h2>Staff Aktif</h2></div><a href="{{ route('admin.staff.index') }}">Urus →</a></div>
            <dl class="admin-team-counts">
                <div><dt>Admin</dt><dd>{{ $teamCounts['ADMIN'] ?? 0 }}</dd></div>
                <div><dt>Operation Management</dt><dd>{{ $teamCounts['OPERATION_MANAGEMENT'] ?? 0 }}</dd></div>
                <div><dt>Designer</dt><dd>{{ $teamCounts['DESIGNER'] ?? 0 }}</dd></div>
                <div><dt>Production</dt><dd>{{ $teamCounts['PRODUCTION'] ?? 0 }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="admin-panel admin-orders-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Latest</p><h2>Order Terkini</h2></div><a href="{{ route('admin.orders.index') }}">Semua order →</a></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order ID</th><th>Customer</th><th>Pakej</th><th>Status</th><th>Tarikh</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentOrders as $order)
                    <tr><td><strong>{{ $order->order_id }}</strong></td><td>{{ $order->customer_name ?: 'Belum diisi' }}</td><td>{{ $order->package_count }} pakej</td><td><span class="admin-status">{{ str_replace('_', ' ', $order->status) }}</span></td><td>{{ $order->created_at->format('d M Y') }}</td><td><a href="{{ route('admin.orders.show', $order->order_id) }}">Buka</a></td></tr>
                @empty
                    <tr><td colspan="6" class="admin-empty">Belum ada tempahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.querySelector('.admin-stat-grid');

        if (! grid) {
            return;
        }

        const cards = grid.querySelectorAll('article');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const formatter = new Intl.NumberFormat('ms-MY');

        cards.forEach(function (card, index) {
            card.style.setProperty('--stat-index', index);
        });

        grid.classList.add('is-animated');

        if (! reduceMotion) {
            window.setTimeout(function () {
                grid.classList.remove('is-animated');
                grid.classList.add('is-floating');
            }, 950);
        }

        grid.querySelectorAll('[data-stat-value]').forEach(function (counter) {
            const target = Number(counter.dataset.statValue);

            if (reduceMotion || ! Number.isFinite(target) || target <= 0) {
                counter.textContent = formatter.format(Math.max(0, target || 0));

                return;
            }

            const duration = 850;
            const startTime = performance.now();
            counter.textContent = '0';

            function updateCounter(currentTime) {
                const progress = Math.min((currentTime - startTime) / duration, 1);
                const easedProgress = 1 - Math.pow(1 - progress, 3);
                counter.textContent = formatter.format(Math.round(target * easedProgress));

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                }
            }

            requestAnimationFrame(updateCounter);
        });
    });
</script>
@endpush
