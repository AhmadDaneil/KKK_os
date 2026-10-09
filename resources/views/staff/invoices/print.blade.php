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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - Invois</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #1e2930; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #edf0ef; font-size: 10pt; line-height: 1.4; }
        .toolbar { display: flex; justify-content: center; gap: 12px; padding: 18px; }
        .toolbar button, .toolbar a { border: 0; border-radius: 6px; padding: 11px 18px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #1d5f48; color: white; } .toolbar a { background: white; color: #1e2930; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto 18px; padding: 14mm 15mm 13mm; background: white; display: flex; flex-direction: column; box-shadow: 0 3px 16px #17212b20; }
        .invoice-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; border-bottom: 2px solid #a9823f; padding-bottom: 14px; }
        .brand { font-size: 17pt; font-weight: 800; letter-spacing: .04em; color: #213c35; }
        .brand-sub, .company-details, .muted { color: #65716e; } .brand-sub { font-size: 9pt; margin-top: 3px; }
        .company-details { font-size: 8.5pt; margin-top: 8px; white-space: pre-line; }
        .invoice-title { margin: 0; color: #213c35; font-size: 25pt; letter-spacing: .03em; text-align: right; }
        .invoice-meta { margin-top: 8px; text-align: right; font-size: 9pt; }
        .meta-row { display: flex; justify-content: flex-end; gap: 12px; margin-top: 3px; } .meta-row span:first-child { color: #687775; }
        .order-strip { margin: 14px 0 18px; color: #42544f; }
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 18px; }
        .section-label { margin: 0 0 8px; font-size: 9pt; font-weight: 800; letter-spacing: .07em; color: #9a7b44; text-transform: uppercase; }
        .details-table { width: 100%; border-collapse: collapse; font-size: 9pt; } .details-table td { padding: 3px 0; vertical-align: top; overflow-wrap: anywhere; }
        .details-table td:first-child { width: 32%; color: #6b7473; padding-right: 8px; }
        .invoice-lines { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 5px; }
        .invoice-lines th { background: #29434b; color: white; text-align: left; font-size: 8.5pt; padding: 8px 7px; }
        .invoice-lines td { border-bottom: 1px solid #dce2df; padding: 8px 7px; vertical-align: top; font-size: 9pt; overflow-wrap: anywhere; }
        .invoice-lines .number { width: 7%; } .invoice-lines .description { width: 43%; } .invoice-lines .unit { width: 12%; text-align: center; }
        .invoice-lines .price { width: 18%; text-align: right; } .invoice-lines .amount { width: 20%; text-align: right; }
        .summary-area { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px; align-items: start; }
        .payment-note { padding: 13px; background: #f3f5f3; border-radius: 5px; } .payment-note strong { color: #29434b; }
        .totals { width: 100%; border-collapse: collapse; } .totals td { padding: 5px 7px; } .totals td:last-child { text-align: right; white-space: nowrap; }
        .totals .grand-total { background: #29434b; color: white; font-weight: 800; font-size: 11pt; }
        .totals .balance td { border-top: 1px solid #a9823f; font-weight: 800; font-size: 11pt; color: #806536; }
        .page-footer { display: flex; justify-content: space-between; border-top: 1px solid #dce2df; margin-top: auto; padding-top: 8px; color: #78817f; font-size: 8pt; }
        .terms-title { margin: 0; color: #29434b; font-size: 20pt; } .terms-subtitle { color: #737d7a; font-size: 9pt; }
        .terms-rule { border: 0; border-top: 2px solid #a9823f; margin: 12px 0 20px; } .terms-section { margin: 0 0 20px; }
        .terms-section h2 { margin: 0 0 7px; font-size: 12pt; color: #29434b; } .terms-section ol { margin: 0; padding-left: 22px; }
        .terms-section li { padding-left: 4px; margin: 0 0 7px; } .thanks { margin-top: 12px; font-weight: 700; }
        @page { size: A4; margin: 0; }
        @media print { body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; } .toolbar { display: none !important; }
            .page { width: 210mm; height: 297mm; min-height: 297mm; margin: 0; padding: 14mm 15mm 13mm; box-shadow: none; break-after: page; page-break-after: always; }
            .page:last-child { break-after: auto; page-break-after: auto; } }
        @media screen and (max-width: 760px) { .page { width: 100%; min-height: 0; padding: 24px; } .two-columns, .summary-area { grid-template-columns: 1fr; }
            .invoice-header { flex-direction: column; } .invoice-meta, .invoice-title { text-align: left; } .meta-row { justify-content: flex-start; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Tindakan invois">
        <button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
        <a href="{{ route($orderRoute, $order->order_id) }}">Kembali ke tempahan</a>
    </nav>
    <main>
        <section class="page" aria-label="Muka hadapan invois">
            <header class="invoice-header">
                <div><div class="brand">{{ $companyName }}</div><div class="brand-sub">Invois tempahan pelanggan</div>
                    <div class="company-details">@if ($registrationNumber)No. Pendaftaran: {{ $registrationNumber }}@endif
@if ($businessAddress){{ $businessAddress }}@endif
@if ($businessPhone){{ $businessPhone }}@endif
@if ($businessEmail){{ $businessEmail }}@endif</div></div>
                <div><h1 class="invoice-title">INVOIS</h1><div class="invoice-meta">
                    <div class="meta-row"><span>No. Invois</span><strong>{{ $invoice->invoice_number }}</strong></div>
                    <div class="meta-row"><span>Tarikh Invois</span><strong>{{ $invoice->issued_at->timezone(config('app.timezone'))->format('d F Y') }}</strong></div>
                </div></div>
            </header>
            <div class="order-strip"><strong>No. Tempahan:</strong> {{ $order->order_id }} &nbsp; | &nbsp; <strong>Status:</strong> {{ str_replace('_', ' ', $invoice->payment_status) }}</div>
            <div class="two-columns">
                <section><h2 class="section-label">Bill To</h2><table class="details-table">
                    <tr><td>Nama</td><td><strong>{{ $order->fulfilment?->recipient_name ?: $order->customer_name ?: '-' }}</strong></td></tr>
                    <tr><td>Telefon</td><td>{{ $order->fulfilment?->recipient_phone ?: $order->customer_phone ?: '-' }}</td></tr>
                    <tr><td>Alamat</td><td>{{ $order->fulfilment?->shipping_address ?: '-' }}</td></tr>
                </table></section>
                <section><h2 class="section-label">Event Details</h2><table class="details-table">
                    <tr><td>Event</td><td>Majlis Perkahwinan</td></tr><tr><td>Pasangan</td><td>{{ $coupleNames ?: '-' }}</td></tr>
                    <tr><td>Tarikh</td><td>{{ $eventDates ?: '-' }}</td></tr><tr><td>Pakej</td><td>{{ $packageNames ?: '-' }}</td></tr>
                    <tr><td>Kuantiti</td><td>{{ $order->card_quantity ? number_format($order->card_quantity).' kad' : '-' }}</td></tr>
                    <tr><td>Penghantaran</td><td>{{ $deliveryLabel }}</td></tr>
                </table></section>
            </div>
            <table class="invoice-lines"><thead><tr><th class="number">No.</th><th class="description">Keterangan</th><th class="unit">Unit</th><th class="price">Harga ({{ $currency }})</th><th class="amount">Amaun ({{ $currency }})</th></tr></thead>
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
            <div class="summary-area"><div class="payment-note"><div class="section-label">Payment Details</div><strong>Bayaran melalui saluran rasmi KKK OS</strong><div class="muted">Simpan invois ini sebagai rujukan tempahan dan pembayaran.</div></div>
                <table class="totals"><tr><td>Subtotal</td><td>{{ $money($invoice->subtotal) }}</td></tr><tr><td>Postage / Courier</td><td>{{ $money($invoice->postage_amount) }}</td></tr>
                    @if ((float) $invoice->discount_amount > 0)<tr><td>Diskaun</td><td>-{{ $money($invoice->discount_amount) }}</td></tr>@endif
                    <tr class="grand-total"><td>Jumlah</td><td>{{ $money($invoice->total_amount) }}</td></tr><tr><td>Bayaran Diterima</td><td>{{ $money($invoice->amount_paid) }}</td></tr>
                    <tr class="balance"><td>Baki</td><td>{{ $money($invoice->balance_due) }}</td></tr></table></div>
            <footer class="page-footer"><span>Invois ini dijana oleh sistem. Tandatangan tidak diperlukan.</span><span>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Muka 1</span></footer>
        </section>
        <section class="page" aria-label="Terma dan syarat invois">
            <header><h1 class="terms-title">TERMA &amp; SYARAT / PROSES TEMPAHAN</h1><div class="terms-subtitle">Terms &amp; Conditions / Order Process</div></header><hr class="terms-rule">
            @foreach ($terms as $heading => $items)<section class="terms-section"><h2>{{ mb_strtoupper($heading) }}</h2><ol>@foreach ($items as $term)<li>{{ $term }}</li>@endforeach</ol></section>@endforeach
            <p class="thanks">Terima kasih kerana memilih King Kad Kahwin.</p>
            <footer class="page-footer"><span>Invois ini dijana oleh sistem. Tandatangan tidak diperlukan.</span><span>{{ $invoice->invoice_number }} &nbsp;|&nbsp; Muka 2</span></footer>
        </section>
    </main>
</body>
</html>
