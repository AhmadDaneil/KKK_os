<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Your Order — KingKadKahwin</title>
</head>
<body>
    <main>
        <h1>Access Your Order Dashboard</h1>
        <p>Enter the customer reference and phone number used when booking.</p>
        @if ($errors->any())
            <div role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('orders.access.store') }}">
            @csrf
            <label for="customer_reference">Customer reference</label>
            <input id="customer_reference" name="customer_reference" value="{{ old('customer_reference') }}" required>
            <label for="customer_phone">Phone number</label>
            <input id="customer_phone" name="customer_phone" type="tel" required>
            <button type="submit">Access order</button>
        </form>
    </main>
</body>
</html>
