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
                    <input id="dashboard-order-id" name="order_id" value="{{ old('order_id') }}" placeholder="Example: KKK-260924-0003" maxlength="32" required>
                    <button class="admin-button admin-button-gold admin-action-hover" type="submit">{{ __('ui.open_order') }}</button>
                </div>
                @error('order_id')
                    <span class="admin-quick-search-error">{{ $message }}</span>
                @enderror
            </form>
        </div>
        <a class="admin-button admin-button-gold admin-action-hover" href="{{ route('admin.staff.index') }}">{{ __('ui.manage_staff') }}</a>
    </section>

    <section class="admin-stat-grid" aria-label="Operations summary">
        <article><span>{{ __('ui.total_orders') }}</span><strong data-stat-value="{{ $statistics['orders_total'] }}">{{ number_format($statistics['orders_total']) }}</strong><small>All order records</small></article>
        <article><span>{{ __('ui.active_orders') }}</span><strong data-stat-value="{{ $statistics['orders_active'] }}">{{ number_format($statistics['orders_active']) }}</strong><small>Not completed or archived</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_deposits'] > 0, 'is-clear' => $statistics['pending_deposits'] === 0])><span>{{ __('ui.pending_deposits') }}</span><strong data-stat-value="{{ $statistics['pending_deposits'] }}">{{ number_format($statistics['pending_deposits']) }}</strong><small>{{ $statistics['pending_deposits'] > 0 ? 'Review promptly' : 'No pending reviews' }}</small></article>
        <article @class(['is-warning', 'has-alert' => $statistics['pending_balances'] > 0, 'is-clear' => $statistics['pending_balances'] === 0])><span>{{ __('ui.pending_balances') }}</span><strong data-stat-value="{{ $statistics['pending_balances'] }}">{{ number_format($statistics['pending_balances']) }}</strong><small>{{ $statistics['pending_balances'] > 0 ? 'Review promptly' : 'No pending reviews' }}</small></article>
        <article class="is-success"><span>{{ __('ui.completed_orders') }}</span><strong data-stat-value="{{ $statistics['orders_completed'] }}">{{ number_format($statistics['orders_completed']) }}</strong><small>All fulfilment steps completed</small></article>
    </section>

    <section class="admin-panel admin-sales-panel" id="sales-analysis">
        <div class="admin-panel-heading">
            <div><p class="admin-eyebrow">{{ __('ui.business_performance') }}</p><h2>{{ __('ui.sales_analysis') }}</h2><small>Total collections from confirmed paid transactions.</small></div>
            <span class="admin-sales-period">{{ now()->format('Y') }}</span>
        </div>

        <div class="admin-sales-summary">
            <article><span>Gross Sales</span><strong>RM {{ number_format($sales['business_summary']['gross_sales'], 2) }}</strong><small>Total invoice value</small></article>
            <article><span>Collected</span><strong>RM {{ number_format($sales['business_summary']['collected'], 2) }}</strong><small>Paid transactions</small></article>
            <article><span>Outstanding</span><strong>RM {{ number_format($sales['business_summary']['outstanding'], 2) }}</strong><small>Invoice balance</small></article>
            <article><span>Total Orders</span><strong>{{ number_format($sales['business_summary']['total_orders']) }}</strong><small>1 package: {{ $sales['package_breakdown'][1] }} · 2 packages: {{ $sales['package_breakdown'][2] }}</small></article>
            <article><span>Add-on / Postage</span><strong>RM {{ number_format($sales['business_summary']['addon_sales'] + $sales['business_summary']['postage_sales'], 2) }}</strong><small>Add-on RM {{ number_format($sales['business_summary']['addon_sales'], 2) }} · Postage RM {{ number_format($sales['business_summary']['postage_sales'], 2) }}</small></article>
            <article><span>Booking Collected</span><strong>RM {{ number_format($sales['business_summary']['booking_collected'], 2) }}</strong><small>Successful bookings</small></article>
            <article><span>Balance Collected</span><strong>RM {{ number_format($sales['business_summary']['balance_collected'], 2) }}</strong><small>Balance payments received</small></article>
            <article><span>{{ __('ui.today') }}</span><strong>RM {{ number_format($sales['summary']['today'], 2) }}</strong><small>{{ __('ui.transactions', ['count' => $sales['summary']['today_transactions']]) }}</small></article>
            <article><span>{{ __('ui.this_month') }}</span><strong>RM {{ number_format($sales['summary']['month'], 2) }}</strong><small @class(['is-up' => $sales['summary']['month_change'] >= 0, 'is-down' => $sales['summary']['month_change'] < 0])>{{ $sales['summary']['month_change'] >= 0 ? '+' : '' }}{{ number_format($sales['summary']['month_change'], 1) }}% vs last month</small></article>
            <article><span>{{ __('ui.this_year') }}</span><strong>RM {{ number_format($sales['summary']['year'], 2) }}</strong><small>{{ $sales['summary']['year_orders'] }} orders · {{ __('ui.transactions', ['count' => $sales['summary']['year_transactions']]) }}</small></article>
            <article><span>{{ __('ui.average_order') }}</span><strong>RM {{ number_format($sales['summary']['average_order_value'], 2) }}</strong><small>Based on this month's collections</small></article>
            <article class="is-pending"><span>{{ __('ui.pending_payment') }}</span><strong>RM {{ number_format($sales['summary']['pending_amount'], 2) }}</strong><small>{{ __('ui.transactions', ['count' => $sales['summary']['pending_count']]) }}</small></article>
        </div>

        <div class="admin-sales-chart-grid">
            <article class="admin-sales-chart">
                <header><div><strong>{{ __('ui.daily_trend') }}</strong><small>Last 14 days</small></div></header>
                <div class="admin-bar-chart is-daily" aria-label="Daily sales chart">
                    @foreach ($sales['daily'] as $point)
                        <div class="admin-bar-column" title="{{ $point['label'] }}: RM {{ number_format($point['amount'], 2) }}"><span class="admin-bar-value">{{ $point['amount'] > 0 ? 'RM'.number_format($point['amount'], 0) : '' }}</span><i style="height: {{ $point['percentage'] }}%"></i><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
            </article>

            <article class="admin-sales-chart">
                <header><div><strong>{{ __('ui.monthly_trend') }}</strong><small>January to December {{ now()->year }}</small></div>@if ($sales['summary']['best_month'])<span>Best: {{ $sales['summary']['best_month']['label'] }}</span>@endif</header>
                <div class="admin-bar-chart" aria-label="Monthly sales chart">
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
                        <div><span>{{ $item['label'] }}<small>{{ $item['transactions'] }} transactions</small></span><strong>RM {{ number_format($item['amount'], 2) }}</strong></div>
                    @endforeach
                </div>
            </article>
            <article class="admin-sales-note">
                <h3>{{ __('ui.important') }}</h3>
                <ul>
                    <li><strong>{{ $sales['summary']['pending_count'] }}</strong> payments are awaiting review.</li>
                    <li><strong>{{ $sales['summary']['year_orders'] }}</strong> orders have contributed to this year's sales.</li>
                    <li>
                        @if ($sales['summary']['best_month'])
                            The best month was <strong>{{ $sales['summary']['best_month']['label'] }}</strong> with RM {{ number_format($sales['summary']['best_month']['amount'], 2) }}.
                        @else
                            There are no paid sales this year yet.
                        @endif
                    </li>
                    <li>These collections include only confirmed paid transactions; operating costs, refunds and net profit are not recorded in this module.</li>
                </ul>
                @if ($sales['summary']['pending_count'] > 0)<a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}">Review payments now →</a>@endif
            </article>
        </div>
    </section>

    <section class="admin-panel admin-attention-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Admin Actions</p><h2>Needs Attention</h2></div><span @class(['admin-attention-total', 'has-alert' => $attentionTotal > 0, 'is-clear' => $attentionTotal === 0])>{{ $attentionTotal > 0 ? $attentionTotal.' actions' : 'All clear' }}</span></div>
        <div class="admin-action-grid">
            <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}" @class(['has-alert' => $attention['pending_payments'] > 0, 'is-clear' => $attention['pending_payments'] === 0])><span class="action-icon is-payment">RM</span><div><strong>Payment Review</strong><small>Pending deposit or full payments</small></div><b>{{ $attention['pending_payments'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}" @class(['has-alert' => $attention['design_queue'] > 0, 'is-clear' => $attention['design_queue'] === 0])><span class="action-icon is-design">RB</span><div><strong>Design Tasks</strong><small>Automatically assigned to the lead designer</small></div><b>{{ $attention['design_queue'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}" @class(['has-alert' => $attention['printing_queue'] > 0, 'is-clear' => $attention['printing_queue'] === 0])><span class="action-icon is-printing">CT</span><div><strong>Production Tasks</strong><small>Automatically assigned to lead production staff</small></div><b>{{ $attention['printing_queue'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'packing', 'attention' => 'unassigned_packing']) }}" @class(['has-alert' => $attention['unassigned_packing'] > 0, 'is-clear' => $attention['unassigned_packing'] === 0])><span class="action-icon is-packing">PK</span><div><strong>Unassigned Packing</strong><small>Assign Operations Management to packing and fulfilment</small></div><b>{{ $attention['unassigned_packing'] }}</b></a>
        </div>
    </section>

    <div class="admin-content-grid">
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Workload</p><h2>Current Queues</h2></div><a href="{{ route('admin.orders.index') }}">View all →</a></div>
            <div class="admin-queue-list">
                <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}"><span class="queue-icon">DE</span><div><strong>Design</strong><small>Ready, in progress or requiring corrections</small></div><b>{{ $queues['design'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}"><span class="queue-icon">PR</span><div><strong>Production</strong><small>Cards being produced</small></div><b>{{ $queues['printing'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}"><span class="queue-icon">PA</span><div><strong>Packing</strong><small>Awaiting or undergoing packing</small></div><b>{{ $queues['packing'] }}</b></a>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Team</p><h2>Active Staff</h2></div><a href="{{ route('admin.staff.index') }}">Manage →</a></div>
            <dl class="admin-team-counts">
                <div><dt>Admin</dt><dd>{{ $teamCounts['ADMIN'] ?? 0 }}</dd></div>
                <div><dt>Operations Management</dt><dd>{{ $teamCounts['OPERATION_MANAGEMENT'] ?? 0 }}</dd></div>
                <div><dt>Designers</dt><dd>{{ $teamCounts['DESIGNER'] ?? 0 }}</dd></div>
                <div><dt>Production</dt><dd>{{ $teamCounts['PRODUCTION'] ?? 0 }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="admin-panel admin-orders-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Recent</p><h2>Recent Orders</h2></div><a href="{{ route('admin.orders.index') }}">All orders →</a></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order ID</th><th>Customer</th><th>Packages</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentOrders as $order)
                    <tr><td><strong>{{ $order->order_id }}</strong></td><td>{{ $order->customer_name ?: 'Not provided' }}</td><td>{{ $order->package_count }} {{ $order->package_count === 1 ? 'package' : 'packages' }}</td><td><span class="admin-status">{{ $statusLabels[$order->status] ?? 'Order Status' }}</span></td><td>{{ $order->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td><td><a href="{{ route('admin.orders.show', $order->order_id) }}">Open</a></td></tr>
                @empty
                    <tr><td colspan="6" class="admin-empty">No orders yet.</td></tr>
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
        const formatter = new Intl.NumberFormat('en-MY');

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
