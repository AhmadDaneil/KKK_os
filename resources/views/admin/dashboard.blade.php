@extends('admin.layouts.app')

@section('title', __('ui.admin_dashboard'))
@section('heading', __('ui.overview'))

@section('content')
    @php
        $attentionTotal = array_sum($attention);
        $statusLabels = collect(__('ui.progress'))->mapWithKeys(fn ($copy, $status) => [$status => $copy[0]])->all();
    @endphp

    <section class="admin-welcome">
        <div>
            <p class="admin-eyebrow">{{ __('ui.welcome', ['name' => auth()->user()->name]) }}</p>
            <h2>{{ __('ui.admin_intro_title') }}</h2>
            <p>{{ __('ui.admin_intro') }}</p>
            <form class="admin-quick-order-search" method="GET" action="{{ route('admin.orders.find') }}">
                <label for="dashboard-order-id">{{ __('ui.find_order') }}</label>
                <div>
                    <input id="dashboard-order-id" name="order_id" value="{{ old('order_id') }}" placeholder="Contoh: KKK-260924-0003" maxlength="32" required>
                    <button class="admin-button admin-button-gold admin-action-hover" type="submit">{{ __('ui.open_order') }}</button>
                </div>
                @error('order_id')
                    <span class="admin-quick-search-error">{{ $message }}</span>
                @enderror
            </form>
        </div>
        <a class="admin-button admin-button-gold admin-action-hover" href="{{ route('admin.staff.index') }}">{{ __('ui.manage_staff') }}</a>
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan operasi">
        <article><span>{{ __('ui.total_orders') }}</span><strong data-stat-value="{{ $statistics['orders_total'] }}">{{ number_format($statistics['orders_total']) }}</strong><small>Semua rekod tempahan</small></article>
        <article><span>{{ __('ui.active_orders') }}</span><strong data-stat-value="{{ $statistics['orders_active'] }}">{{ number_format($statistics['orders_active']) }}</strong><small>Belum selesai atau diarkib</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_deposits'] > 0, 'is-clear' => $statistics['pending_deposits'] === 0])><span>{{ __('ui.pending_deposits') }}</span><strong data-stat-value="{{ $statistics['pending_deposits'] }}">{{ number_format($statistics['pending_deposits']) }}</strong><small>{{ $statistics['pending_deposits'] > 0 ? 'Perlu disemak segera' : 'Tiada semakan tertunggak' }}</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_balances'] > 0, 'is-clear' => $statistics['pending_balances'] === 0])><span>{{ __('ui.pending_balances') }}</span><strong data-stat-value="{{ $statistics['pending_balances'] }}">{{ number_format($statistics['pending_balances']) }}</strong><small>{{ $statistics['pending_balances'] > 0 ? 'Perlu disemak segera' : 'Tiada semakan tertunggak' }}</small></article>
        <article class="is-success"><span>{{ __('ui.completed_orders') }}</span><strong data-stat-value="{{ $statistics['orders_completed'] }}">{{ number_format($statistics['orders_completed']) }}</strong><small>Keseluruhan pemenuhan tempahan selesai</small></article>
    </section>

    <section class="admin-panel admin-sales-panel" id="sales-analysis">
        <div class="admin-panel-heading">
            <div><p class="admin-eyebrow">{{ __('ui.business_performance') }}</p><h2>{{ __('ui.sales_analysis') }}</h2><small>Jumlah kutipan berdasarkan transaksi yang telah disahkan sebagai dibayar.</small></div>
            <span class="admin-sales-period">{{ now()->format('Y') }}</span>
        </div>

        <div class="admin-sales-summary">
            <article><span>{{ __('ui.today') }}</span><strong>RM {{ number_format($sales['summary']['today'], 2) }}</strong><small>{{ __('ui.transactions', ['count' => $sales['summary']['today_transactions']]) }}</small></article>
            <article><span>{{ __('ui.this_month') }}</span><strong>RM {{ number_format($sales['summary']['month'], 2) }}</strong><small @class(['is-up' => $sales['summary']['month_change'] >= 0, 'is-down' => $sales['summary']['month_change'] < 0])>{{ $sales['summary']['month_change'] >= 0 ? '+' : '' }}{{ number_format($sales['summary']['month_change'], 1) }}% {{ app()->getLocale() === 'en' ? 'vs last month' : 'berbanding bulan lalu' }}</small></article>
            <article><span>{{ __('ui.this_year') }}</span><strong>RM {{ number_format($sales['summary']['year'], 2) }}</strong><small>{{ $sales['summary']['year_orders'] }} tempahan · {{ __('ui.transactions', ['count' => $sales['summary']['year_transactions']]) }}</small></article>
            <article><span>{{ __('ui.average_order') }}</span><strong>RM {{ number_format($sales['summary']['average_order_value'], 2) }}</strong><small>{{ app()->getLocale() === 'en' ? 'Based on this month’s collections' : 'Berdasarkan kutipan bulan ini' }}</small></article>
            <article class="is-pending"><span>{{ __('ui.pending_payment') }}</span><strong>RM {{ number_format($sales['summary']['pending_amount'], 2) }}</strong><small>{{ __('ui.transactions', ['count' => $sales['summary']['pending_count']]) }}</small></article>
        </div>

        <div class="admin-sales-chart-grid">
            <article class="admin-sales-chart">
                <header><div><strong>{{ __('ui.daily_trend') }}</strong><small>{{ app()->getLocale() === 'en' ? 'Last 14 days' : '14 hari terakhir' }}</small></div></header>
                <div class="admin-bar-chart is-daily" aria-label="Graf jualan harian">
                    @foreach ($sales['daily'] as $point)
                        <div class="admin-bar-column" title="{{ $point['label'] }}: RM {{ number_format($point['amount'], 2) }}"><span class="admin-bar-value">{{ $point['amount'] > 0 ? 'RM'.number_format($point['amount'], 0) : '' }}</span><i style="height: {{ $point['percentage'] }}%"></i><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
            </article>

            <article class="admin-sales-chart">
                <header><div><strong>{{ __('ui.monthly_trend') }}</strong><small>{{ app()->getLocale() === 'en' ? 'January to December' : 'Januari hingga Disember' }} {{ now()->year }}</small></div>@if ($sales['summary']['best_month'])<span>{{ app()->getLocale() === 'en' ? 'Best' : 'Terbaik' }}: {{ $sales['summary']['best_month']['label'] }}</span>@endif</header>
                <div class="admin-bar-chart" aria-label="Graf jualan bulanan">
                    @foreach ($sales['monthly'] as $point)
                        <div class="admin-bar-column" title="{{ $point['label'] }}: RM {{ number_format($point['amount'], 2) }}"><span class="admin-bar-value">{{ $point['amount'] > 0 ? 'RM'.number_format($point['amount'], 0) : '' }}</span><i style="height: {{ $point['percentage'] }}%"></i><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
            </article>
        </div>

        <div class="admin-sales-bottom-grid">
            <article>
                <h3>{{ __('ui.annual_sales') }}</h3>
                <div class="admin-annual-list">
                    @foreach ($sales['annual'] as $point)
                        <div><span>{{ $point['label'] }}</span><i><b style="width: {{ $point['percentage'] }}%"></b></i><strong>RM {{ number_format($point['amount'], 2) }}</strong></div>
                    @endforeach
                </div>
            </article>
            <article>
                <h3>{{ __('ui.collection_breakdown', ['year' => now()->year]) }}</h3>
                <div class="admin-sales-breakdown">
                    @foreach ($sales['breakdown'] as $item)
                        <div><span>{{ $item['label'] }}<small>{{ $item['transactions'] }} transaksi</small></span><strong>RM {{ number_format($item['amount'], 2) }}</strong></div>
                    @endforeach
                </div>
            </article>
            <article class="admin-sales-note">
                <h3>{{ __('ui.important') }}</h3>
                <ul>
                    <li><strong>{{ $sales['summary']['pending_count'] }}</strong> bayaran masih menunggu semakan.</li>
                    <li><strong>{{ $sales['summary']['year_orders'] }}</strong> tempahan telah menyumbang kepada jualan tahun ini.</li>
                    <li>
                        @if ($sales['summary']['best_month'])
                            Bulan terbaik ialah <strong>{{ $sales['summary']['best_month']['label'] }}</strong> dengan RM {{ number_format($sales['summary']['best_month']['amount'], 2) }}.
                        @else
                            Belum ada jualan berbayar untuk tahun ini.
                        @endif
                    </li>
                </ul>
                @if ($sales['summary']['pending_count'] > 0)<a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}">Semak pembayaran sekarang →</a>@endif
            </article>
        </div>
    </section>

    <section class="admin-panel admin-attention-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Tindakan Admin</p><h2>Memerlukan Perhatian</h2></div><span @class(['admin-attention-total', 'has-alert' => $attentionTotal > 0, 'is-clear' => $attentionTotal === 0])>{{ $attentionTotal > 0 ? $attentionTotal.' tindakan' : 'Semua selesai' }}</span></div>
        <div class="admin-action-grid">
            <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}" @class(['has-alert' => $attention['pending_payments'] > 0, 'is-clear' => $attention['pending_payments'] === 0])><span class="action-icon is-payment">RM</span><div><strong>Semakan Pembayaran</strong><small>Deposit atau bayaran penuh yang masih menunggu</small></div><b>{{ $attention['pending_payments'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'design', 'attention' => 'unassigned_design']) }}" @class(['has-alert' => $attention['unassigned_design'] > 0, 'is-clear' => $attention['unassigned_design'] === 0])><span class="action-icon is-design">RB</span><div><strong>Design Belum Ditugaskan</strong><small>Tugaskan pereka supaya hasil design boleh dimulakan</small></div><b>{{ $attention['unassigned_design'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'printing', 'attention' => 'unassigned_printing']) }}" @class(['has-alert' => $attention['unassigned_printing'] > 0, 'is-clear' => $attention['unassigned_printing'] === 0])><span class="action-icon is-printing">CT</span><div><strong>Pengeluaran Belum Ditugaskan</strong><small>Tugaskan staf pengeluaran untuk menghasilkan kad</small></div><b>{{ $attention['unassigned_printing'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'packing', 'attention' => 'unassigned_packing']) }}" @class(['has-alert' => $attention['unassigned_packing'] > 0, 'is-clear' => $attention['unassigned_packing'] === 0])><span class="action-icon is-packing">PK</span><div><strong>Pembungkusan Belum Ditugaskan</strong><small>Tugaskan OM untuk pembungkusan dan pemenuhan tempahan</small></div><b>{{ $attention['unassigned_packing'] }}</b></a>
        </div>
    </section>

    <div class="admin-content-grid">
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Beban Kerja</p><h2>Senarai Semasa</h2></div><a href="{{ route('admin.orders.index') }}">Lihat semua →</a></div>
            <div class="admin-queue-list">
                <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}"><span class="queue-icon">DE</span><div><strong>Design</strong><small>Sedia, sedang direka atau memerlukan pembetulan</small></div><b>{{ $queues['design'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}"><span class="queue-icon">PR</span><div><strong>Pengeluaran</strong><small>Kad sedang dihasilkan</small></div><b>{{ $queues['printing'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}"><span class="queue-icon">PA</span><div><strong>Pembungkusan</strong><small>Menunggu atau sedang dibungkus</small></div><b>{{ $queues['packing'] }}</b></a>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Pasukan</p><h2>Staf Aktif</h2></div><a href="{{ route('admin.staff.index') }}">Urus →</a></div>
            <dl class="admin-team-counts">
                <div><dt>Admin</dt><dd>{{ $teamCounts['ADMIN'] ?? 0 }}</dd></div>
                <div><dt>Pengurusan Operasi</dt><dd>{{ $teamCounts['OPERATION_MANAGEMENT'] ?? 0 }}</dd></div>
                <div><dt>Pereka</dt><dd>{{ $teamCounts['DESIGNER'] ?? 0 }}</dd></div>
                <div><dt>Pengeluaran</dt><dd>{{ $teamCounts['PRODUCTION'] ?? 0 }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="admin-panel admin-orders-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Terkini</p><h2>Tempahan Terkini</h2></div><a href="{{ route('admin.orders.index') }}">Semua tempahan →</a></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>ID Tempahan</th><th>Pelanggan</th><th>Pakej</th><th>Status</th><th>Tarikh</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentOrders as $order)
                    <tr><td><strong>{{ $order->order_id }}</strong></td><td>{{ $order->customer_name ?: 'Belum diisi' }}</td><td>{{ $order->package_count }} pakej</td><td><span class="admin-status">{{ $statusLabels[$order->status] ?? 'Status Tempahan' }}</span></td><td>{{ $order->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td><td><a href="{{ route('admin.orders.show', $order->order_id) }}">Buka</a></td></tr>
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
