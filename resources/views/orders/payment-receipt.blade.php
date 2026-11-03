<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->displayCustomerReference() ?: $order->order_id }}</title>
</head>
<body>
    <main>
        <h1>Payment Receipt</h1>
        <dl>
            <dt>{{ $order->displayCustomerReference() ? 'Customer reference' : 'Order ID' }}</dt>
            <dd>{{ $order->displayCustomerReference() ?: $order->order_id }}</dd>
            <dt>Payment type</dt>
            <dd>{{ $payment->payment_type === 'BOOKING_DEPOSIT' ? 'Booking deposit' : 'Remaining balance' }}</dd>
            <dt>Amount</dt>
            <dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd>
            <dt>Status</dt>
            <dd>Paid</dd>
            <dt>Payment date</dt>
            <dd>{{ $payment->paid_at?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</dd>
        </dl>
        <p>This receipt applies only to the transaction above.</p>
    </main>
</body>
</html>
