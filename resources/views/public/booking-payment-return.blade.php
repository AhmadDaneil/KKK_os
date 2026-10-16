<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Status — KingKadKahwin</title>
</head>
<body>
    <main>
        <h1>Booking payment status</h1>
        @if ($payment->status === 'PAID')
            <p>Payment was successful.</p>
            <p>Customer reference: <strong>{{ $order->customer_reference }}</strong></p>
            <p><a href="{{ route('orders.dashboard', ['orderId' => $order->order_id]) }}">Continue to your order dashboard</a></p>
            <p>Save this customer reference. You can also use it with your phone number to access your order again.</p>
        @else
            <p>Your payment is being confirmed. Please check again shortly.</p>
        @endif
    </main>
</body>
</html>
