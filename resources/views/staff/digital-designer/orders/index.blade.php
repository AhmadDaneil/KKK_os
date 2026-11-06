<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Designer Queue - KKK OS</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-compact.css') }}?v={{ filemtime(public_path('css/dashboard-compact.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/operations-theme.css') }}?v={{ filemtime(public_path('css/operations-theme.css')) }}">
    @include('staff.partials.theme-init')
    <style>
        .digital-designer-queue { display:grid; gap:14px; }
        .digital-designer-order { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:18px; padding:18px 20px; border:1px solid var(--staff-border); border-radius:14px; background:var(--staff-surface,#fff); box-shadow:0 4px 14px rgba(23,56,43,.06); }
        .digital-designer-order h2 { margin:0 0 7px; font-size:17px; }
        .digital-designer-order p { margin:4px 0; color:var(--staff-muted); font-size:13px; }
        .digital-designer-order-actions { display:flex; align-items:center; gap:10px; }
        .digital-designer-order-actions .staff-button { white-space:nowrap; }
        .digital-designer-note { margin:0 0 18px; color:var(--staff-muted); }
        @media (max-width:700px) { .digital-designer-order { grid-template-columns:1fr; } .digital-designer-order-actions { justify-content:flex-start; } }
    </style>
</head>
<body>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">Digital Designer</p><h1>Motion Video & Alive Card Premium</h1></div>
                <div class="staff-topbar-actions">
                    @include('staff.partials.theme-toggle')
                    <form class="js-logout-form staff-logout-profile" method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit"><span class="staff-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
                </div>
            </header>

            <main class="staff-main">
                <section class="staff-section">
                    <div class="staff-section-title"><h2>Paid booking queue</h2></div>
                    <p class="digital-designer-note">Orders appear here after the booking deposit is paid. The separate Digital Designer CSV follows the Alive Card V1 format and reflects the information saved by the customer; fields not filled yet remain blank.</p>

                    <div class="digital-designer-queue">
                        @forelse ($orders as $order)
                            <article class="digital-designer-order">
                                <div>
                                    <h2>{{ $order->displayCustomerReference() ?: $order->order_id }}</h2>
                                    <p>{{ $order->customer_name ?: 'Customer name unavailable' }}</p>
                                    <p>{{ $order->package_count }} {{ $order->package_count === 1 ? 'package' : 'packages' }} · Deposit {{ $order->booking_payment_status === 'PAID' ? 'Paid' : 'Pending' }}</p>
                                    <p>Order date: {{ $order->created_at?->timezone(config('app.display_timezone'))->format('d/m/Y, g:i A') ?: '-' }}</p>
                                </div>
                                <div class="digital-designer-order-actions">
                                    <a href="{{ route('staff.digital-designer.orders.csv', $order) }}" class="staff-button staff-button-primary">Download Digital Designer CSV</a>
                                </div>
                            </article>
                        @empty
                            <div class="staff-empty-state">No paid bookings are waiting in the Digital Designer queue.</div>
                        @endforelse
                    </div>

                    @if ($orders->hasPages())
                        <div class="staff-order-pagination">{{ $orders->links() }}</div>
                    @endif
                </section>
            </main>
        </div>
    </div>
    @include('staff.partials.logout-confirmation')
</body>
</html>
