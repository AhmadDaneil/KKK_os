<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status Pembayaran — KingKadKahwin</title>
</head>
<body>
    <main>
        <h1>Status pembayaran booking</h1>
        @if ($payment->status === 'PAID')
            <p>Pembayaran telah berjaya.</p>
            <p>Rujukan pelanggan: <strong>{{ $order->customer_reference }}</strong></p>
            <p><a href="{{ route('orders.dashboard', ['orderId' => $order->order_id]) }}">Teruskan ke dashboard tempahan</a></p>
            <p>Simpan rujukan pelanggan ini. Anda juga boleh menggunakannya bersama nombor telefon untuk mengakses tempahan semula.</p>
        @else
            <p>Pembayaran sedang disahkan. Sila semak semula sebentar lagi.</p>
        @endif
    </main>
</body>
</html>
