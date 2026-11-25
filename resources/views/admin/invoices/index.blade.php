@extends('admin.layouts.app')

@section('title', 'Sales Invoice')
@section('heading', 'Sales Invoice')

@section('content')
    <section class="admin-panel admin-invoice-panel">
        <div class="admin-panel-heading">
            <div>
                <p class="admin-eyebrow">Invoice Records</p>
                <h2>Sales Invoice</h2>
                <small>Filter invoices by issue date, payment status, assigned staff, or customer details.</small>
            </div>
            <span class="admin-sales-period">{{ number_format($invoices->total()) }} invoices</span>
        </div>

        <form class="admin-invoice-filters" method="GET" action="{{ route('admin.invoices.index') }}">
            <div class="admin-invoice-filter-grid">
                <div>
                    <label for="invoice-year">Year</label>
                    <select id="invoice-year" name="year">
                        <option value="">All years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected($filters['year'] === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice-month">Month</label>
                    <select id="invoice-month" name="month">
                        <option value="">All months</option>
                        @foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $monthNumber => $monthName)
                            <option value="{{ $monthNumber }}" @selected($filters['month'] === (string) $monthNumber)>{{ $monthName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice-day">Day</label>
                    <select id="invoice-day" name="day">
                        <option value="">Any day</option>
                        @foreach (range(1, 31) as $day)
                            <option value="{{ $day }}" @selected($filters['day'] === (string) $day)>{{ str_pad((string) $day, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice-date">Exact date</label>
                    <input id="invoice-date" type="date" name="date" value="{{ $filters['date'] }}">
                </div>
                <div>
                    <label for="invoice-status">Status</label>
                    <select id="invoice-status" name="status">
                        <option value="">All statuses</option>
                        <option value="UNPAID" @selected($filters['status'] === 'UNPAID')>Unpaid</option>
                        <option value="PARTIALLY_PAID" @selected($filters['status'] === 'PARTIALLY_PAID')>Partially paid</option>
                        <option value="PAID" @selected($filters['status'] === 'PAID')>Paid</option>
                    </select>
                </div>
                <div>
                    <label for="invoice-staff">Assigned staff</label>
                    <select id="invoice-staff" name="staff">
                        <option value="">All staff</option>
                        @foreach ($staffOptions as $staff)
                            <option value="{{ $staff->id }}" @selected($filters['staff'] === (string) $staff->id)>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-invoice-search">
                    <label for="invoice-search">Search</label>
                    <input id="invoice-search" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Invoice no., order ID, customer name or phone">
                </div>
                <div class="admin-invoice-filter-actions">
                    <button class="admin-button admin-button-primary" type="submit">Search invoices</button>
                    <a class="admin-button admin-button-secondary" href="{{ route('admin.invoices.index') }}">Clear filters</a>
                </div>
            </div>
        </form>

        <div class="admin-table-wrap admin-invoice-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Invoice No.</th><th>Issued</th><th>Customer / Order</th><th>Contact</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php($currencyLabel = $invoice->currency === 'MYR' ? 'RM' : $invoice->currency)
                        <tr>
                            <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            <td>{{ $invoice->issued_at->timezone(config('app.display_timezone'))->format('d M Y') }}</td>
                            <td>
                                <strong>{{ $invoice->order->customer_name ?: 'Not provided' }}</strong>
                                <small class="admin-invoice-order-reference">{{ $invoice->order->displayCustomerReference() ?: $invoice->order->order_id }}</small>
                            </td>
                            <td>{{ $invoice->order->customer_phone ?: '—' }}</td>
                            <td>{{ $currencyLabel }} {{ number_format((float) $invoice->total_amount, 2) }}</td>
                            <td>{{ $currencyLabel }} {{ number_format((float) $invoice->amount_paid, 2) }}</td>
                            <td>{{ $currencyLabel }} {{ number_format((float) $invoice->balance_due, 2) }}</td>
                            <td><span class="admin-invoice-status is-{{ strtolower($invoice->payment_status) }}">{{ str_replace('_', ' ', $invoice->payment_status) }}</span></td>
                            <td class="admin-invoice-actions">
                                <a href="{{ route('admin.invoices.print', $invoice) }}" target="_blank" rel="noopener">View</a>
                                <a href="{{ route('admin.invoices.download', $invoice) }}">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="admin-empty">No sales invoices match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-invoice-pagination">{{ $invoices->links('admin.partials.pagination') }}</div>
    </section>
@endsection
