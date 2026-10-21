@php
    $currency = $invoice->currency === 'MYR' ? 'RM' : $invoice->currency;
    $money = static fn ($amount): string => $currency.' '.number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }} - Invoice</title>
    <style>
        @page { size: A4 portrait; margin: 13mm 14mm 12mm; }
        body { margin: 0; color: #1e2930; font: 9pt/1.35 "DejaVu Sans", Arial, sans-serif; }
        h1, h2, p { margin: 0; }
        .header, .columns, .summary, .footer { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header { border-bottom: 2px solid #a9823f; }
        .header td { width: 50%; padding: 0 0 10px; vertical-align: top; }
        .brand { color: #213c35; font-size: 17pt; font-weight: bold; letter-spacing: 1px; }
        .company-details { margin-top: 7px; color: #65716e; font-size: 8pt; white-space: pre-line; }
        .invoice-title { color: #213c35; font-size: 25pt; text-align: right; }
        .invoice-meta { margin-top: 6px; text-align: right; }
        .meta-label, .label { color: #687775; }
        .order-strip { margin: 11px 0 12px; color: #42544f; }
        .columns { margin-bottom: 12px; }
        .columns td { width: 50%; padding: 0 10px 0 0; vertical-align: top; }
        .columns td + td { padding: 0 0 0 10px; }
        .section-label { margin-bottom: 5px; color: #9a7b44; font-size: 8pt; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .details { width: 100%; border-collapse: collapse; }
        .details td { width: auto; padding: 2px 0; vertical-align: top; overflow-wrap: break-word; }
        .details td:first-child { width: 31%; padding-right: 6px; color: #687775; }
        .lines { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .lines th { padding: 5px 5px; background: #29434b; color: white; font-size: 7.5pt; text-align: left; }
        .lines td { padding: 4px 5px; border-bottom: 1px solid #dce2df; vertical-align: top; overflow-wrap: break-word; font-size: 7.5pt; }
        .number { width: 7%; }
        .description { width: 43%; }
        .unit { width: 12%; text-align: center !important; }
        .price { width: 18%; text-align: right !important; }
        .amount { width: 20%; text-align: right !important; }
        .summary { margin-top: 13px; }
        .summary > tbody > tr > td { width: 50%; padding: 0 8px 0 0; vertical-align: top; }
        .summary > tbody > tr > td + td { padding: 0 0 0 8px; }
        .payment-note { padding: 9px; background: #f3f5f3; }
        .payment-note strong { color: #29434b; }
        .payment-details { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .payment-details td { padding: 2px 0; vertical-align: top; }
        .payment-details td:first-child { width: 36%; color: #687775; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 6px; }
        .totals td:last-child { text-align: right; white-space: nowrap; }
        .grand-total td { background: #29434b; color: white; font-weight: bold; }
        .balance td { border-top: 1px solid #a9823f; color: #806536; font-size: 10pt; font-weight: bold; }
        .footer { margin-top: 19px; border-top: 1px solid #dce2df; color: #78817f; font-size: 7.5pt; }
        .footer td { padding-top: 6px; vertical-align: top; }
        .footer td:last-child { text-align: right; }
        .page-two { page-break-before: always; }
        .terms-title { color: #29434b; font-size: 18pt; }
        .terms-subtitle { margin-top: 3px; color: #737d7a; font-size: 8pt; }
        .terms-rule { margin: 10px 0 16px; border: 0; border-top: 2px solid #a9823f; }
        .terms-section { margin-bottom: 17px; page-break-inside: avoid; }
        .terms-section h2 { margin-bottom: 6px; color: #29434b; font-size: 10pt; }
        .terms-section ol { margin: 0; padding-left: 19px; }
        .terms-section li { margin-bottom: 5px; padding-left: 3px; }
        .thanks { margin-top: 10px; font-weight: bold; }
        .muted { color: #65716e; }
    </style>
</head>
<body>
    <section>
        <table class="header"><tr>
            <td><div class="brand">{{ $companyName }}</div><div class="company-details">@if ($registrationNumber)Registration No.: {{ $registrationNumber }}<br>@endif
                @if ($businessAddress){{ $businessAddress }}<br>@endif
                @if ($businessPhone){{ $businessPhone }}<br>@endif
                @if ($businessEmail){{ $businessEmail }}@endif</div></td>
            <td><h1 class="invoice-title">INVOICE</h1><div class="invoice-meta"><div><span class="meta-label">Invoice No.</span> <strong>{{ $invoice->invoice_number }}</strong></div>
                <div><span class="meta-label">Invoice Date</span> <strong>{{ $invoice->issued_at->timezone(config('app.timezone'))->format('d F Y') }}</strong></div></div></td>
        </tr></table>
        <div class="order-strip"><strong>Order No.:</strong> {{ $order->order_id }} &nbsp; | &nbsp; <strong>Status:</strong> {{ str_replace('_', ' ', $invoice->payment_status) }}</div>
        <table class="columns"><tr>
            <td><h2 class="section-label">Bill To</h2><table class="details">
                <tr><td>Name</td><td><strong>{{ $order->fulfilment?->recipient_name ?: $order->customer_name ?: '-' }}</strong></td></tr>
                <tr><td>Phone</td><td>{{ $order->fulfilment?->recipient_phone ?: $order->customer_phone ?: '-' }}</td></tr>
                <tr><td>Address</td><td>{{ $order->fulfilment?->shipping_address ?: '-' }}</td></tr>
            </table></td>
            <td><h2 class="section-label">Event Details</h2><table class="details">
                <tr><td>Event</td><td>Wedding Ceremony</td></tr><tr><td>Couple</td><td>{{ $coupleNames ?: '-' }}</td></tr>
                <tr><td>Booking Date</td><td>{{ $bookingDate }}</td></tr><tr><td>Event Date</td><td>{{ $eventDates ?: '-' }}</td></tr>
                <tr><td>Package</td><td>{{ $packageName }}</td></tr><tr><td>Quantity</td><td>{{ $packageQuantities }}</td></tr>
                <tr><td>Design Code</td><td>{{ $designCodes ?: '-' }}</td></tr>
                <tr><td>Delivery</td><td>{{ $deliveryLabel }}</td></tr>
            </table></td>
        </tr></table>
        <table class="lines"><thead><tr><th class="number">No.</th><th class="description">Description</th><th class="unit">Unit</th><th class="price">Price ({{ $currency }})</th><th class="amount">Amount ({{ $currency }})</th></tr></thead>
            <tbody>
            @foreach ($invoiceLines as $item)
                <tr><td>{{ $loop->iteration }}</td><td>{{ $item['description'] }}@if ($item['side']) <span class="muted">({{ $item['side'] === 'LELAKI' ? 'Groom' : 'Bride' }})</span>@endif</td><td class="unit">{{ number_format($item['quantity']) }}</td><td class="price">{{ number_format($item['unit_price'], 2) }}</td><td class="amount">{{ number_format($item['line_total'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <table class="summary"><tr>
            <td><div class="payment-note"><div class="section-label">Payment Details</div><table class="payment-details">
                <tr><td>Bank</td><td><strong>{{ $paymentBank }}</strong></td></tr>
                <tr><td>Account Name</td><td><strong>{{ $paymentAccountName }}</strong></td></tr>
                <tr><td>Account No.</td><td><strong>{{ $paymentAccountNumber }}</strong></td></tr>
            </table><span class="muted">{{ $paymentInstruction }}</span></div></td>
            <td><table class="totals"><tr><td>Subtotal</td><td>{{ $money($invoice->subtotal) }}</td></tr><tr><td>Postage / Courier</td><td>{{ $money($invoice->postage_amount) }}</td></tr>
                @if ((float) $invoice->discount_amount > 0)<tr><td>Discount</td><td>-{{ $money($invoice->discount_amount) }}</td></tr>@endif
                <tr class="grand-total"><td>Total</td><td>{{ $money($invoice->total_amount) }}</td></tr><tr><td>Deposit</td><td>{{ $money($depositAmount) }}</td></tr>
                <tr class="balance"><td>Balance Due</td><td>{{ $money($invoice->balance_due) }}</td></tr></table></td>
        </tr></table>
        <table class="footer"><tr><td>This invoice was generated by the system. No signature is required.</td><td>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Page 1</td></tr></table>
    </section>
    <section class="page-two">
        <h1 class="terms-title">TERMS &amp; CONDITIONS / ORDER PROCESS</h1><div class="terms-subtitle">Terms &amp; Conditions / Order Process</div><hr class="terms-rule">
        @foreach ($terms as $heading => $items)<section class="terms-section"><h2>{{ mb_strtoupper($heading) }}</h2><ol>@foreach ($items as $term)<li>{{ $term }}</li>@endforeach</ol></section>@endforeach
        <p class="thanks">Thank you for choosing KingKadKahwin.</p>
        <table class="footer"><tr><td>This invoice was generated by the system. No signature is required.</td><td>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Page 2</td></tr></table>
    </section>
</body>
</html>
