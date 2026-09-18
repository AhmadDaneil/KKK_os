<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $workstream ? ucfirst($workstream).' Queue' : 'Staff Orders' }} - KKK OS</title>

    <link rel="stylesheet" href="{{ asset('css/staff.css') }}">
</head>
<body @class(['admin-operations-mode' => auth()->user()->isAdmin()])>
    <div class="staff-app-shell">
        @include('staff.partials.sidebar')
        <div class="staff-workspace">
            <header class="staff-topbar">
                <div><p class="staff-kicker">{{ auth()->user()->isAdmin() ? 'Admin Operations' : ($workstream ? ucfirst($workstream) : 'Operation Management') }}</p><h1>{{ $workstream ? ucfirst($workstream).' Queue' : 'Semua Orders' }}</h1></div>
                <form method="POST" action="{{ route('staff.logout') }}">@csrf<button type="submit" class="staff-button staff-button-small">Log Keluar</button></form>
            </header>

        <main class="staff-main">
            <div class="staff-page-header">
                <div>
                    <a
                        href="{{ route('staff.dashboard') }}"
                        class="staff-back-link"
                    >
                        &larr; Dashboard
                    </a>

                    <h1>{{ $workstream ? ucfirst($workstream).' Queue' : 'Semua Orders' }}</h1>

                    <p>
                        @if (auth()->user()->isAdmin())
                            Pantau semua order dan kerja operasi KKK OS.
                        @else
                            Lihat order yang mempunyai kerja assigned kepada anda.
                        @endif
                    </p>
                </div>

                <div class="staff-page-count">
                    {{ $orders->total() }} order
                </div>
            </div>

            <div class="staff-order-list">
                @forelse ($orders as $order)
                    <article class="staff-order-card">
                        <div class="staff-order-primary">
                            <div class="staff-order-heading">
                                <strong class="staff-order-id">
                                    {{ $order->order_id }}
                                </strong>

                                <span class="staff-status">
                                    {{ str_replace('_', ' ', $order->status) }}
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
                        </div>

                        <div class="staff-order-work">
                            @if (auth()->user()->isAdmin())
                                @if ($order->relationLoaded('designJobs') && $order->designJobs->isNotEmpty())
                                    <div class="staff-work-row">
                                        <span>Design</span>
                                        <strong>
                                            {{ $order->designJobs->pluck('status')->unique()->implode(', ') }}
                                        </strong>
                                    </div>
                                @endif

                                @if ($order->relationLoaded('printJobs') && $order->printJobs->isNotEmpty())
                                    <div class="staff-work-row">
                                        <span>Printing</span>
                                        <strong>
                                            {{ $order->printJobs->pluck('status')->unique()->implode(', ') }}
                                        </strong>
                                    </div>
                                @endif

                                @if ($order->relationLoaded('packingJob') && $order->packingJob)
                                    <div class="staff-work-row">
                                        <span>Packing</span>
                                        <strong>
                                            {{ $order->packingJob->status }}
                                        </strong>
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
                            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PRINTING))
                                @foreach ($order->printJobs as $job)
                                    @if ($job->assigned_user_id === auth()->id())
                                        <div class="staff-work-row">
                                            <span>Printing {{ $job->side }}</span>
                                            <strong>{{ $job->status }}</strong>
                                        </div>
                                    @endif
                                @endforeach
                            @elseif (auth()->user()->hasStaffRole(\App\Models\User::ROLE_PACKING))
                                @if (
                                    $order->packingJob &&
                                    $order->packingJob->assigned_user_id === auth()->id()
                                )
                                    <div class="staff-work-row">
                                        <span>Packing</span>
                                        <strong>{{ $order->packingJob->status }}</strong>
                                    </div>
                                @endif
                            @endif
                        </div>

                        <a
                            href="{{ route('staff.orders.show', $order->order_id) }}"
                            class="staff-order-open"
                        >
                            View order &rarr;
                        </a>
                    </article>
                @empty
                    <div class="staff-empty">
                        @if (auth()->user()->isAdmin())
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
</body>
</html>
