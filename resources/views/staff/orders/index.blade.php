@php
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $dashboardRoute = $isAdminPortal ? 'admin.dashboard' : 'staff.dashboard';
    $logoutRoute = $isAdminPortal ? 'admin.logout' : 'staff.logout';
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $ordersShowRoute = $isAdminPortal ? 'admin.orders.show' : 'staff.orders.show';
    $workstreamDescriptions = [
        'design' => 'Orders requiring design, design review or corrections.',
        'printing' => 'Orders awaiting or undergoing production.',
        'packing' => 'Printed orders that still need packing by Operations Management.',
        'fulfilment' => 'Packed orders awaiting delivery or pickup.',
    ];
    $workstreamLabels = [
        'design' => 'Design',
        'printing' => 'Production',
        'packing' => 'Packing',
        'fulfilment' => 'Order Fulfilment',
    ];
    $workstreamLabel = $workstreamLabels[$workstream] ?? null;
    $attentionLabels = [
        'pending_payment' => 'Payment Review',
        'unassigned_design' => 'Unassigned Design Work',
        'unassigned_printing' => 'Unassigned Production Work',
        'unassigned_packing' => 'Unassigned Packing Work',
        'packing_fulfilment' => 'Packing & Fulfilment Needs Attention',
    ];
    $attentionDescriptions = [
        'pending_payment' => 'Review deposits and full payments awaiting confirmation.',
        'packing_fulfilment' => 'Review orders requiring packing, courier handover or pickup preparation.',
    ];
    $attention = request('attention');
    $attentionLabel = $attentionLabels[$attention] ?? null;
    $pageLabel = $attentionLabel ?? ($workstreamLabel ? $workstreamLabel.' Queue' : 'All Orders');
    $statusLabels = [
        'DETAILS_INCOMPLETE' => 'Details Incomplete',
        'BOOKED' => 'Order Received',
        'DEPOSIT_PAID' => 'Deposit Received',
        'DETAILS_CONFIRMED' => 'Details Confirmed',
        'READY_FOR_DESIGN' => 'Ready for Design',
        'DESIGN_IN_PROGRESS' => 'Design in Progress',
        'DESIGN_READY' => 'Awaiting Design Review',
        'CORRECTION_REQUESTED' => 'Correction Required',
        'DESIGN_APPROVED' => 'Design Approved',
        'BALANCE_PENDING' => 'Awaiting Balance Payment',
        'PAID' => 'Payment Complete',
        'READY_FOR_PRINT' => 'Ready for Production',
        'PRINTING' => 'Production in Progress',
        'PRINTED' => 'Production Complete',
        'READY_FOR_PACKING' => 'Ready for Packing',
        'PACKING' => 'Packing in Progress',
        'PACKED' => 'Packing Complete',
        'READY_FOR_FULFILMENT' => 'Ready for Handover',
        'READY_FOR_PICKUP' => 'Ready for Pickup',
        'SHIPPED' => 'Shipped',
        'COMPLETED' => 'Order Complete',
        'CANCELLED' => 'CANCELLED',
        'ARCHIVED' => 'Archived',
    ];
    $statusTone = static fn (string $status): string => match ($status) {
        'DETAILS_INCOMPLETE', 'CORRECTION_REQUESTED' => 'attention',
        'DESIGN_READY', 'BALANCE_PENDING', 'READY_FOR_PICKUP', 'SHIPPED' => 'waiting',
        'COMPLETED' => 'complete',
        'CANCELLED' => 'cancelled',
        default => 'active',
    };
    $nextActions = [
        'DETAILS_INCOMPLETE' => 'Collect missing customer details',
        'BOOKED', 'DEPOSIT_PAID' => 'Review customer order details',
        'DETAILS_CONFIRMED', 'READY_FOR_DESIGN' => 'Start or assign design work',
        'DESIGN_IN_PROGRESS' => 'Continue design work',
        'DESIGN_READY' => 'Review the completed design',
        'CORRECTION_REQUESTED' => 'Complete design corrections',
        'DESIGN_APPROVED', 'BALANCE_PENDING' => 'Review balance payment',
        'PAID', 'READY_FOR_PRINT' => 'Start production',
        'PRINTING' => 'Update or complete production',
        'PRINTED', 'READY_FOR_PACKING' => 'Start packing',
        'PACKING' => 'Verify items and complete packing',
        'PACKED', 'READY_FOR_FULFILMENT' => 'Arrange handover or pickup',
        'READY_FOR_PICKUP' => 'Notify customer about pickup',
        'SHIPPED' => 'Track delivery',
        'COMPLETED' => 'No further action required',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $pageLabel }} - KKK OS</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-compact.css') }}?v={{ filemtime(public_path('css/dashboard-compact.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/operations-theme.css') }}?v={{ filemtime(public_path('css/operations-theme.css')) }}">
    @include('staff.partials.theme-init')
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : ($workstreamLabel ?? 'Operations Management') }}</p><h1>Orders</h1></div>
                <div class="staff-topbar-actions">
                    @include('staff.partials.theme-toggle')
                    <form class="js-logout-form staff-logout-profile" method="POST" action="{{ route($logoutRoute) }}">@csrf<button type="submit"><span class="staff-topbar-avatar" aria-hidden="true"></span><span>{{ __('ui.logout') }}</span></button></form>
                </div>
            </header>

        <main class="staff-main">
            @if (session('status'))
                <div class="staff-alert staff-alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->has('order_delete'))
                <div class="staff-alert staff-alert-error">{{ $errors->first('order_delete') }}</div>
            @endif

            <div class="staff-page-header">
                <div>
                    <a
                        href="{{ route($dashboardRoute) }}"
                        class="staff-back-link"
                    >
                        &larr; Dashboard
                    </a>

                    <h1>{{ $pageLabel }}</h1>

                    <p>
                        @if (auth()->user()->canMonitorAllDepartments())
                            {{ $attentionDescriptions[$attention] ?? $workstreamDescriptions[$workstream] ?? 'Monitor all orders and operational work in KKK OS.' }}
                        @else
                            View orders with work assigned to you.
                        @endif
                    </p>
                </div>

                <div id="staff-order-count" class="staff-page-count" data-live-search-count>
                    {{ $orders->total() }} orders
                </div>
            </div>

            <form class="staff-order-filter" method="GET" action="{{ route($ordersIndexRoute) }}" data-live-search data-live-search-list="staff-order-list" data-live-search-pagination="staff-order-pagination" data-live-search-count="staff-order-count" data-live-search-notice="staff-order-filter-notice">
                @if ($workstream)<input type="hidden" name="workstream" value="{{ $workstream }}">@endif
                @if (request('attention'))<input type="hidden" name="attention" value="{{ request('attention') }}">@endif
                <label><span>Search orders or customers</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Order ID, name, email or phone"></label>
                <label><span>Order status</span><select name="status"><option value="">All statuses</option><option value="{{ \App\Models\Order::STATUS_FILTER_NOT_COMPLETED }}" @selected(request('status') === \App\Models\Order::STATUS_FILTER_NOT_COMPLETED)>Not completed</option>@foreach ($statusOptions as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? str_replace('_', ' ', $status) }}</option>@endforeach</select></label>
                <div class="staff-filter-actions"><button class="staff-button staff-button-primary" type="submit">Filter Orders</button>@if (request()->hasAny(['search', 'status', 'attention']))<a class="staff-button" href="{{ route($ordersIndexRoute, array_filter(['workstream' => $workstream])) }}">Clear</a>@endif</div>
            </form>

            <div id="staff-order-filter-notice">
                @if (request()->hasAny(['search', 'status', 'attention']))
                    <div class="staff-filter-notice">
                        <span>Active filters:</span>
                        @if (request('search'))<strong>“{{ request('search') }}”</strong>@endif
                        @if (request('status'))<strong>{{ $statusLabels[request('status')] ?? str_replace('_', ' ', request('status')) }}</strong>@endif
                        @if ($attention)<strong>{{ $attentionLabel ?? str_replace('_', ' ', $attention) }}</strong>@endif
                        <a href="{{ route($ordersIndexRoute, array_filter(['workstream' => $workstream])) }}">Clear filters</a>
                    </div>
                @endif
            </div>

            <div id="staff-order-list" class="staff-order-list">
                @forelse ($orders as $order)
                    <article class="staff-order-card">
                        <div class="staff-order-primary">
                            <div class="staff-order-heading">
                                <strong class="staff-order-id">
                                    {{ $order->order_id }}
                                </strong>

                                <span class="staff-status staff-status--{{ $statusTone($order->status) }}">
                                    {{ $statusLabels[$order->status] ?? str_replace('_', ' ', $order->status) }}
                                </span>
                            </div>

                            <div class="staff-order-customer">
                                {{ $order->customer_name ?: 'Customer name unavailable' }}
                            </div>

                            <div class="staff-order-meta">
                                <span>
                                    {{ $order->package_count }}
                                    {{ $order->package_count === 1 ? 'package' : 'packages' }}
                                </span>

                                @if ($order->card_quantity)
                                    <span>
                                        {{ $order->card_quantity }} cards
                                    </span>
                                @endif
                            </div>

                        </div>

                        <div class="staff-order-next-action">
                            <span>Next action</span>
                            <strong>{{ $nextActions[$order->status] ?? 'Open order for review' }}</strong>
                        </div>

                        <div class="staff-order-actions">
                            <a
                                href="{{ route($ordersShowRoute, $order->order_id) }}"
                                class="staff-order-open"
                            >
                                Open &rarr;
                            </a>

                            @if (
                                $order->status === 'DETAILS_INCOMPLETE'
                                    && ($isAdminPortal
                                    ? auth('admin')->user()?->isAdmin()
                                    : auth('staff')->user()?->isOperationManagement())
                            )
                                <form
                                    method="POST"
                                    action="{{ route($isAdminPortal ? 'admin.orders.destroy' : 'staff.orders.destroy', $order) }}"
                                    onsubmit="return confirm('Delete order {{ $order->order_id }}? All order details, payments and workflow records will be permanently deleted.');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="staff-order-delete">Delete</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="staff-empty">
                        @if ($workstream)
                            No active orders in the {{ $workstreamLabel }} queue.
                        @elseif (auth()->user()->canMonitorAllDepartments())
                            No orders available.
                        @else
                            No orders assigned to you.
                        @endif
                    </div>
                @endforelse
            </div>

            <div id="staff-order-pagination">
                @if ($orders->hasPages())
                    <nav class="staff-pagination" aria-label="Order pagination">
                    <div class="staff-pagination-summary">
                        Showing {{ $orders->firstItem() }}
                        to {{ $orders->lastItem() }}
                        of {{ $orders->total() }} results
                    </div>

                    <div class="staff-pagination-links">
                        @if ($orders->onFirstPage())
                            <span class="staff-pagination-link is-disabled">
                                Previous
                            </span>
                        @else
                            <a
                                href="{{ $orders->previousPageUrl() }}"
                                class="staff-pagination-link"
                            >
                                Previous
                            </a>
                        @endif

                        @foreach ($orders->getUrlRange(1, $orders->lastPage()) as $page => $url)
                            @if ($page === $orders->currentPage())
                                <span
                                    class="staff-pagination-link is-current"
                                    aria-current="page"
                                >
                                    {{ $page }}
                                </span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    class="staff-pagination-link"
                                >
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        @if ($orders->hasMorePages())
                            <a
                                href="{{ $orders->nextPageUrl() }}"
                                class="staff-pagination-link"
                            >
                                Next
                            </a>
                        @else
                            <span class="staff-pagination-link is-disabled">
                                Next
                            </span>
                        @endif
                    </div>
                    </nav>
                @endif
            </div>
        </main>
        </div>
    </div>
    @include('staff.partials.logout-confirmation')
    <script src="{{ asset('js/live-search.js') }}?v={{ filemtime(public_path('js/live-search.js')) }}" defer></script>
</body>
</html>
