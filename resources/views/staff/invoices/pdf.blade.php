@php
    $currency = $invoice->currency === 'MYR' ? 'RM' : $invoice->currency;
    $money = static fn ($amount): string => $currency.' '.number_format((float) $amount, 2);
    $lineItems = $order->items->whereNotIn('item_type', ['DELIVERY', 'DISCOUNT']);
    $deliveryItem = $order->items->firstWhere('item_type', 'DELIVERY');
    $discountItem = $order->items->firstWhere('item_type', 'DISCOUNT');
    $deliveryLabel = $deliveryItem?->description ?? match ($order->fulfilment?->method) {
        'COURIER' => 'Kurier', 'PICKUP' => 'Pengambilan sendiri', default => 'Belum ditetapkan',
    };
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }} - Invois</title>
    <style>
        @page { size: A4 portrait; margin: 13mm 14mm 12mm; }
        body { margin: 0; color: #1e2930; font: 9pt/1.35 "DejaVu Sans", Arial, sans-serif; }
        h1, h2, p { margin: 0; }
        .header, .columns, .summary, .footer { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header { border-bottom: 2px solid #a9823f; }
        .header td { width: 50%; padding: 0 0 10px; vertical-align: top; }
        .brand { color: #213c35; font-size: 17pt; font-weight: bold; letter-spacing: 1px; }
        .company-details { margin-top: 7px; color: #65716e; font-size: 8pt; }
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
        .lines th { padding: 7px 6px; background: #29434b; color: white; font-size: 8pt; text-align: left; }
        .lines td { padding: 7px 6px; border-bottom: 1px solid #dce2df; vertical-align: top; overflow-wrap: break-word; }
        .number { width: 7%; }
        .description { width: 43%; }
        .unit { width: 12%; text-align: center !important; }
        .price { width: 18%; text-align: right !important; }
        .amount { width: 20%; text-align: right !important; }
        .summary { margin-top: 13px; }
        .summary > tbody > tr > td { width: 50%; padding: 0 8px 0 0; vertical-align: top; }
        .summary > tbody > tr > td + td { padding: 0 0 0 8px; }
        .payment-note { padding: 11px; background: #f3f5f3; }
        .payment-note strong { color: #29434b; }
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
            <td><div class="brand">{{ $companyName }}</div><div class="company-details">@if ($registrationNumber)No. Pendaftaran: {{ $registrationNumber }}<br>@endif
                @if ($businessAddress){{ $businessAddress }}<br>@endif
                @if ($businessPhone){{ $businessPhone }}<br>@endif
                @if ($businessEmail){{ $businessEmail }}@endif</div></td>
            <td><h1 class="invoice-title">INVOIS</h1><div class="invoice-meta"><div><span class="meta-label">No. Invois</span> <strong>{{ $invoice->invoice_number }}</strong></div>
                <div><span class="meta-label">Tarikh Invois</span> <strong>{{ $invoice->issued_at->timezone(config('app.timezone'))->format('d F Y') }}</strong></div></div></td>
        </tr></table>
        <div class="order-strip"><strong>No. Tempahan:</strong> {{ $order->order_id }} &nbsp; | &nbsp; <strong>Status:</strong> {{ str_replace('_', ' ', $invoice->payment_status) }}</div>
        <table class="columns"><tr>
            <td><h2 class="section-label">Bill To</h2><table class="details">
                <tr><td>Nama</td><td><strong>{{ $order->fulfilment?->recipient_name ?: $order->customer_name ?: '-' }}</strong></td></tr>
                <tr><td>Telefon</td><td>{{ $order->fulfilment?->recipient_phone ?: $order->customer_phone ?: '-' }}</td></tr>
                <tr><td>Alamat</td><td>{{ $order->fulfilment?->shipping_address ?: '-' }}</td></tr>
            </table></td>
            <td><h2 class="section-label">Event Details</h2><table class="details">
                <tr><td>Event</td><td>Majlis Perkahwinan</td></tr><tr><td>Pasangan</td><td>{{ $coupleNames ?: '-' }}</td></tr>
                <tr><td>Tarikh</td><td>{{ $eventDates ?: '-' }}</td></tr><tr><td>Pakej</td><td>{{ $packageNames ?: '-' }}</td></tr>
                <tr><td>Kuantiti</td><td>{{ $order->card_quantity ? number_format($order->card_quantity).' kad' : '-' }}</td></tr>
                <tr><td>Penghantaran</td><td>{{ $deliveryLabel }}</td></tr>
            </table></td>
        </tr></table>
        <table class="lines"><thead><tr><th class="number">No.</th><th class="description">Keterangan</th><th class="unit">Unit</th><th class="price">Harga ({{ $currency }})</th><th class="amount">Amaun ({{ $currency }})</th></tr></thead>
            <tbody>
                @forelse ($lineItems as $item)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $item->description }}@if ($item->side) <span class="muted">({{ ucfirst(strtolower($item->side)) }})</span>@endif</td><td class="unit">{{ number_format($item->quantity) }}</td><td class="price">{{ number_format((float) $item->unit_price, 2) }}</td><td class="amount">{{ number_format((float) $item->line_total, 2) }}</td></tr>
                @empty
                    <tr><td>1</td><td>Tempahan kad kahwin ({{ $order->package_count }} pakej)</td><td class="unit">1</td><td class="price">{{ number_format((float) $invoice->subtotal, 2) }}</td><td class="amount">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                @endforelse
                @if ((float) $invoice->discount_amount > 0)<tr><td>{{ $lineItems->count() + 1 }}</td><td>{{ $discountItem?->description ?: 'Diskaun' }}</td><td class="unit">1</td><td class="price">-{{ number_format((float) $invoice->discount_amount, 2) }}</td><td class="amount">-{{ number_format((float) $invoice->discount_amount, 2) }}</td></tr>@endif
                @if ((float) $invoice->postage_amount > 0)<tr><td>{{ $lineItems->count() + ((float) $invoice->discount_amount > 0 ? 2 : 1) }}</td><td>{{ $deliveryLabel }}</td><td class="unit">1</td><td class="price">{{ number_format((float) $invoice->postage_amount, 2) }}</td><td class="amount">{{ number_format((float) $invoice->postage_amount, 2) }}</td></tr>@endif
            </tbody>
        </table>
        <table class="summary"><tr>
            <td><div class="payment-note"><div class="section-label">Payment Details</div><strong>Bayaran melalui saluran rasmi KKK OS</strong><br><span class="muted">Simpan invois ini sebagai rujukan tempahan dan pembayaran.</span></div></td>
            <td><table class="totals"><tr><td>Subtotal</td><td>{{ $money($invoice->subtotal) }}</td></tr><tr><td>Postage / Courier</td><td>{{ $money($invoice->postage_amount) }}</td></tr>
                @if ((float) $invoice->discount_amount > 0)<tr><td>Diskaun</td><td>-{{ $money($invoice->discount_amount) }}</td></tr>@endif
                <tr class="grand-total"><td>Jumlah</td><td>{{ $money($invoice->total_amount) }}</td></tr><tr><td>Bayaran Diterima</td><td>{{ $money($invoice->amount_paid) }}</td></tr>
                <tr class="balance"><td>Baki</td><td>{{ $money($invoice->balance_due) }}</td></tr></table></td>
        </tr></table>
        <table class="footer"><tr><td>Invois ini dijana oleh sistem. Tandatangan tidak diperlukan.</td><td>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Muka 1</td></tr></table>
    </section>
    <section class="page-two">
        <h1 class="terms-title">TERMA &amp; SYARAT / PROSES TEMPAHAN</h1><div class="terms-subtitle">Terms &amp; Conditions / Order Process</div><hr class="terms-rule">
        @foreach ($terms as $heading => $items)<section class="terms-section"><h2>{{ mb_strtoupper($heading) }}</h2><ol>@foreach ($items as $term)<li>{{ $term }}</li>@endforeach</ol></section>@endforeach
        <p class="thanks">Terima kasih kerana memilih King Kad Kahwin.</p>
        <table class="footer"><tr><td>Invois ini dijana oleh sistem. Tandatangan tidak diperlukan.</td><td>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Muka 2</td></tr></table>
    </section>
</body>
</html>
