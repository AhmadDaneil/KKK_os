<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resit {{ $order->customer_reference }}</title>
</head>
<body>
    <main>
        <h1>Resit Pembayaran</h1>
        <dl>
            <dt>Rujukan pelanggan</dt>
            <dd>{{ $order->customer_reference }}</dd>
            <dt>Jenis pembayaran</dt>
            <dd>{{ $payment->payment_type === 'BOOKING_DEPOSIT' ? 'Booking' : 'Baki' }}</dd>
            <dt>Jumlah</dt>
            <dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd>
            <dt>Status</dt>
            <dd>PAID</dd>
            <dt>Tarikh pembayaran</dt>
            <dd>{{ $payment->paid_at?->format('d/m/Y H:i') }}</dd>
        </dl>
        <p>Resit ini merujuk kepada transaksi di atas sahaja.</p>
    </main>
</body>
</html>
