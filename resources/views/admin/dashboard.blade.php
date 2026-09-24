@extends('admin.layouts.app')

@section('title', 'Admin Overview')
@section('heading', 'Overview')

@section('content')
    <section class="admin-welcome">
        <div><p class="admin-eyebrow">Selamat datang, {{ auth()->user()->name }}</p><h2>Semua operasi dalam satu paparan.</h2><p>Pantau tempahan, pembayaran dan beban kerja setiap jabatan sebelum membuka butiran operasi.</p></div>
        <a class="admin-button admin-button-gold" href="{{ route('admin.staff.index') }}">Urus Staff</a>
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan operasi">
        <article><span>Jumlah Order</span><strong>{{ number_format($statistics['orders_total']) }}</strong><small>Semua rekod tempahan</small></article>
        <article><span>Order Aktif</span><strong>{{ number_format($statistics['orders_active']) }}</strong><small>Belum selesai atau diarkib</small></article>
        <article class="is-warning"><span>Deposit Belum Semak</span><strong>{{ number_format($statistics['pending_deposits']) }}</strong><small>Memerlukan tindakan OM</small></article>
        <article class="is-warning"><span>Baki Belum Semak</span><strong>{{ number_format($statistics['pending_balances']) }}</strong><small>Bukti bayaran penuh customer</small></article>
        <article class="is-success"><span>Order Selesai</span><strong>{{ number_format($statistics['orders_completed']) }}</strong><small>Keseluruhan fulfilment selesai</small></article>
    </section>

    <section class="admin-panel admin-attention-panel">
        <div class="admin-panel-heading"><div><p class="admin-eyebrow">Tindakan Admin</p><h2>Memerlukan Perhatian</h2></div><span class="admin-attention-total">{{ array_sum($attention) }} tindakan</span></div>
        <div class="admin-action-grid">
            <a href="{{ route('admin.orders.index', ['attention' => 'pending_payment']) }}"><span class="action-icon is-payment">RM</span><div><strong>Semakan Pembayaran</strong><small>Deposit atau bayaran penuh yang masih pending</small></div><b>{{ $attention['pending_payments'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'design', 'attention' => 'unassigned_design']) }}"><span class="action-icon is-design">DE</span><div><strong>Design Belum Assign</strong><small>Assign designer supaya artwork boleh dimulakan</small></div><b>{{ $attention['unassigned_design'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'printing', 'attention' => 'unassigned_printing']) }}"><span class="action-icon is-printing">PR</span><div><strong>Printing Belum Assign</strong><small>Assign staf printing untuk order yang telah dibayar</small></div><b>{{ $attention['unassigned_printing'] }}</b></a>
            <a href="{{ route('admin.orders.index', ['workstream' => 'packing', 'attention' => 'unassigned_packing']) }}"><span class="action-icon is-packing">PA</span><div><strong>Packing Belum Assign</strong><small>Assign packing atau kendalikan terus sebagai admin</small></div><b>{{ $attention['unassigned_packing'] }}</b></a>
        </div>
    </section>

    <div class="admin-content-grid">
        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Workload</p><h2>Queue Semasa</h2></div><a href="{{ route('admin.orders.index') }}">Lihat semua →</a></div>
            <div class="admin-queue-list">
                <a href="{{ route('admin.orders.index', ['workstream' => 'design']) }}"><span class="queue-icon">DE</span><div><strong>Design</strong><small>Ready, sedang design atau pembetulan</small></div><b>{{ $queues['design'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'printing']) }}"><span class="queue-icon">PR</span><div><strong>Printing</strong><small>Menunggu atau sedang dicetak</small></div><b>{{ $queues['printing'] }}</b></a>
                <a href="{{ route('admin.orders.index', ['workstream' => 'packing']) }}"><span class="queue-icon">PA</span><div><strong>Packing</strong><small>Menunggu atau sedang dibungkus</small></div><b>{{ $queues['packing'] }}</b></a>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">Team</p><h2>Staff Aktif</h2></div><a href="{{ route('admin.staff.index') }}">Urus →</a></div>
            <dl class="admin-team-counts">
                <div><dt>Admin</dt><dd>{{ $teamCounts['ADMIN'] ?? 0 }}</dd></div>
                <div><dt>Operation Management</dt><dd>{{ $teamCounts['OPERATION_MANAGEMENT'] ?? 0 }}</dd></div>
                <div><dt>Designer</dt><dd>{{ $teamCounts['DESIGNER'] ?? 0 }}</dd></div>
                <div><dt>Printing</dt><dd>{{ $teamCounts['PRINTING'] ?? 0 }}</dd></div>
                <div><dt>Packing</dt><dd>{{ $teamCounts['PACKING'] ?? 0 }}</dd></div>
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
