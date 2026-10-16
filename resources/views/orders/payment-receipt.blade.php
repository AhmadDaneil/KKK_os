<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->customer_reference }}</title>
</head>
<body>
    <main>
        <h1>Payment Receipt</h1>
        <dl>
            <dt>Customer reference</dt>
            <dd>{{ $order->customer_reference }}</dd>
            <dt>Payment type</dt>
            <dd>{{ $payment->payment_type === 'BOOKING_DEPOSIT' ? 'Booking deposit' : 'Remaining balance' }}</dd>
            <dt>Amount</dt>
            <dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd>
            <dt>Status</dt>
            <dd>Paid</dd>
            <dt>Payment date</dt>
            <dd>{{ $payment->paid_at?->format('d/m/Y H:i') }}</dd>
        </dl>
        <p>This receipt applies only to the transaction above.</p>
    </main>
</body>
</html>
