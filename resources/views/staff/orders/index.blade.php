@php
    $isAdminPortal = request()->routeIs('admin.orders.*');
    $dashboardRoute = $isAdminPortal ? 'admin.dashboard' : 'staff.dashboard';
    $logoutRoute = $isAdminPortal ? 'admin.logout' : 'staff.logout';
    $ordersIndexRoute = $isAdminPortal ? 'admin.orders.index' : 'staff.orders.index';
    $ordersShowRoute = $isAdminPortal ? 'admin.orders.show' : 'staff.orders.show';
    $workstreamDescriptions = [
        'design' => 'Order yang masih memerlukan design, semakan artwork atau pembetulan.',
        'printing' => 'Order yang menunggu atau sedang dalam proses production.',
        'packing' => 'Order yang telah siap production dan masih memerlukan pembungkusan oleh OM.',
        'fulfilment' => 'Order yang telah dibungkus dan masih menunggu serahan atau kutipan.',
    ];
    $workstreamLabels = [
        'design' => 'Design',
        'printing' => 'Production',
        'packing' => 'Packing',
        'fulfilment' => 'Fulfilment',
    ];
    $workstreamLabel = $workstreamLabels[$workstream] ?? null;
    $statusLabels = [
        'DETAILS_INCOMPLETE' => 'Maklumat belum lengkap',
        'BOOKED' => 'Tempahan diterima',
        'DEPOSIT_PAID' => 'Deposit diterima',
        'DETAILS_CONFIRMED' => 'Maklumat disahkan',
        'READY_FOR_DESIGN' => 'Sedia untuk design',
        'DESIGN_IN_PROGRESS' => 'Design sedang berjalan',
        'DESIGN_READY' => 'Menunggu semakan customer',
        'CORRECTION_REQUESTED' => 'Pembetulan diperlukan',
        'DESIGN_APPROVED' => 'Design diluluskan',
        'BALANCE_PENDING' => 'Menunggu bayaran baki',
        'PAID' => 'Bayaran selesai',
        'READY_FOR_PRINT' => 'Sedia untuk production',
        'PRINTING' => 'Production sedang berjalan',
        'PRINTED' => 'Production siap',
        'READY_FOR_PACKING' => 'Sedia untuk packing',
        'PACKING' => 'Packing sedang berjalan',
        'PACKED' => 'Packing siap',
        'READY_FOR_FULFILMENT' => 'Sedia untuk serahan',
        'READY_FOR_PICKUP' => 'Sedia untuk pickup',
        'SHIPPED' => 'Telah dihantar',
        'COMPLETED' => 'Tempahan selesai',
    ];
    $statusTone = static fn (string $status): string => match ($status) {
        'DETAILS_INCOMPLETE', 'CORRECTION_REQUESTED' => 'attention',
        'DESIGN_READY', 'BALANCE_PENDING', 'READY_FOR_PICKUP', 'SHIPPED' => 'waiting',
        'COMPLETED' => 'complete',
        default => 'active',
    };
    $nextActions = [
        'DETAILS_INCOMPLETE' => 'Dapatkan maklumat customer yang belum lengkap',
        'BOOKED', 'DEPOSIT_PAID' => 'Semak maklumat tempahan customer',
        'DETAILS_CONFIRMED', 'READY_FOR_DESIGN' => 'Mulakan atau assign kerja design',
        'DESIGN_IN_PROGRESS' => 'Teruskan kerja design',
        'DESIGN_READY' => 'Tunggu semakan artwork daripada customer',
        'CORRECTION_REQUESTED' => 'Selesaikan pembetulan artwork',
        'DESIGN_APPROVED', 'BALANCE_PENDING' => 'Semak bayaran baki',
        'PAID', 'READY_FOR_PRINT' => 'Mulakan production',
        'PRINTING' => 'Kemas kini atau siapkan production',
        'PRINTED', 'READY_FOR_PACKING' => 'Mulakan packing',
        'PACKING' => 'Sahkan item dan siapkan packing',
        'PACKED', 'READY_FOR_FULFILMENT' => 'Aturkan serahan atau pickup',
        'READY_FOR_PICKUP' => 'Maklumkan customer untuk pickup',
        'SHIPPED' => 'Pantau penghantaran',
        'COMPLETED' => 'Tiada tindakan lanjut diperlukan',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $workstreamLabel ? $workstreamLabel.' Queue' : 'Staff Orders' }} - KKK OS</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/language-toggle.css') }}?v={{ filemtime(public_path('css/language-toggle.css')) }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()]) data-staff-theme="{{ auth()->user()->staff_theme }}">
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : ($workstreamLabel ?? 'Operation Management') }}</p><h1>{{ $workstreamLabel ? $workstreamLabel.' Queue' : 'Semua Orders' }}</h1></div>
                <div class="staff-topbar-actions">
                    @include('partials.language-toggle')
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

                    <h1>{{ $workstreamLabel ? $workstreamLabel.' Queue' : 'Semua Orders' }}</h1>

                    <p>
                        @if (auth()->user()->canMonitorAllDepartments())
                            {{ $workstreamDescriptions[$workstream] ?? 'Pantau semua order dan kerja operasi KKK OS.' }}
                        @else
                            Lihat order yang mempunyai kerja assigned kepada anda.
                        @endif
                    </p>
                </div>

                <div class="staff-page-count">
                    {{ $orders->total() }} order
                </div>
            </div>

            <form class="staff-order-filter" method="GET" action="{{ route($ordersIndexRoute) }}">
                @if ($workstream)<input type="hidden" name="workstream" value="{{ $workstream }}">@endif
                @if (request('attention'))<input type="hidden" name="attention" value="{{ request('attention') }}">@endif
                <div class="staff-filter-heading"><strong>Cari &amp; tapis order</strong><small>Gunakan Order ID, nama customer atau status kerja.</small></div>
                <label><span>Cari order atau customer</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Order ID, nama, email atau telefon"></label>
                <label><span>Status tempahan</span><select name="status"><option value="">Semua status</option><option value="{{ \App\Models\Order::STATUS_FILTER_NOT_COMPLETED }}" @selected(request('status') === \App\Models\Order::STATUS_FILTER_NOT_COMPLETED)>Belum selesai</option>@foreach ($statusOptions as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $statusLabels[$status] ?? str_replace('_', ' ', $status) }}</option>@endforeach</select></label>
                <div class="staff-filter-actions"><button class="staff-button staff-button-primary" type="submit">Tapis order</button>@if (request()->hasAny(['search', 'status', 'attention']))<a class="staff-button" href="{{ route($ordersIndexRoute, array_filter(['workstream' => $workstream])) }}">Kosongkan</a>@endif</div>
            </form>

            @if (request()->hasAny(['search', 'status', 'attention']))
                <div class="staff-filter-notice">
                    <span>Penapis aktif:</span>
                    @if (request('search'))<strong>“{{ request('search') }}”</strong>@endif
                    @if (request('status'))<strong>{{ $statusLabels[request('status')] ?? str_replace('_', ' ', request('status')) }}</strong>@endif
                    @if (request('attention'))<strong>{{ str_replace('_', ' ', request('attention')) }}</strong>@endif
                    <a href="{{ route($ordersIndexRoute, array_filter(['workstream' => $workstream])) }}">Kosongkan penapis</a>
                </div>
            @endif

            <div class="staff-order-list">
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
                                    {{ $order->package_count == 1 ? 'package' : 'packages' }}
                                </span>

                                @if ($order->card_quantity)
                                    <span>
                                        {{ $order->card_quantity }} cards
                                    </span>
                                @endif
                            </div>

                            <div class="staff-order-next-action">
                                <span>Tindakan seterusnya</span>
                                <strong>{{ $nextActions[$order->status] ?? 'Buka order untuk semakan' }}</strong>
                            </div>
                        </div>

                        <div class="staff-order-work">
                            @if (auth()->user()->canMonitorAllDepartments())
                                @if ((! $workstream || $workstream === 'design') && $order->relationLoaded('designJobs') && $order->designJobs->isNotEmpty())
                                    <div class="staff-work-row">
                                        <span>Design</span>
                                        <strong>
                                            {{ $order->designJobs->pluck('status')->unique()->implode(', ') }}
                                        </strong>
                                    </div>
                                @endif

                                @if ((! $workstream || $workstream === 'printing') && $order->relationLoaded('printJobs') && $order->printJobs->isNotEmpty())
                                    <div class="staff-work-row">
                                        <span>Production</span>
                                        <strong>
                                            {{ $order->printJobs->pluck('status')->unique()->implode(', ') }}
                                        </strong>
                                    </div>
                                @endif

                                @if ((! $workstream || $workstream === 'packing') && $order->relationLoaded('packingJob') && $order->packingJob)
                                    <div class="staff-work-row">
                                        <span>Packing</span>
                                        <strong>
                                            {{ $order->packingJob->status }}
                                        </strong>
                                    </div>
                                @endif

                                @if ((! $workstream || $workstream === 'fulfilment') && $order->relationLoaded('fulfilmentJob') && $order->fulfilmentJob)
                                    <div class="staff-work-row">
                                        <span>Fulfilment</span>
                                        <strong>{{ $order->fulfilmentJob->status }}</strong>
                                    </div>
                                @endif
                            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_DESIGNER))
                                @foreach ($order->designJobs as $job)
                                    @if ($job->assigned_user_id === auth()->id())
                                        <div class="staff-work-row">
                                            <span>Design {{ $job->side }}</span>
                                            <strong>{{ $job->status }}</strong>
                                        </div>
                                    @endif
                                @endforeach
                            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRODUCTION))
                                @if ($order->printJobs->where('assigned_user_id', auth()->id())->isNotEmpty())
                                    @foreach ($order->printJobs->where('assigned_user_id', auth()->id()) as $job)
                                            <div class="staff-work-row">
                                        <span>Production {{ $job->side }}</span>
                                                <strong>{{ $job->status }}</strong>
                                            </div>
                                    @endforeach
                                @elseif ($order->printing_assigned_user_id === auth()->id())
                                    <div class="staff-work-row">
                                        <span>Production</span>
                                        <strong>ASSIGNED · WAITING FOR ARTWORK APPROVAL</strong>
                                    </div>
                                @endif
                            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_OM))
                                @if (
                                    $order->packingJob &&
                                    $order->packingJob->assigned_user_id === auth()->id()
                                )
                                    <div class="staff-work-row">
                                        <span>Packing</span>
                                        <strong>{{ $order->packingJob->status }}</strong>
                                    </div>
                                @elseif ($order->packing_assigned_user_id === auth()->id())
                                    <div class="staff-work-row">
                                        <span>Packing</span>
                                        <strong>ASSIGNED · WAITING FOR PACKING JOB</strong>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div class="staff-order-actions">
                            <a
                                href="{{ route($ordersShowRoute, $order->order_id) }}"
                                class="staff-order-open"
                            >
                                Buka &amp; bertindak &rarr;
                            </a>

                            @if (auth()->user()->isOperationManagement())
                                <form
                                    method="POST"
                                    action="{{ route($isAdminPortal ? 'admin.orders.destroy' : 'staff.orders.destroy', $order) }}"
                                    onsubmit="return confirm('Delete order {{ $order->order_id }}? All order information, payments and workflow records will be permanently removed.');"
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
                            Tiada order aktif dalam {{ $workstreamLabel }} Queue.
                        @elseif (auth()->user()->canMonitorAllDepartments())
                            Tiada order tersedia.
                        @else
                            Tiada order assigned kepada anda.
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($orders->hasPages())
                <nav class="staff-pagination" aria-label="Orders pagination">
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
        </main>
        </div>
    </div>
    @include('staff.partials.logout-confirmation')
    <script src="{{ asset('js/language-runtime.js') }}?v={{ filemtime(public_path('js/language-runtime.js')) }}"></script>
</body>
</html>
