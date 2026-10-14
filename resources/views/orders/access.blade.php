<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Tempahan — KingKadKahwin</title>
</head>
<body>
    <main>
        <h1>Akses Dashboard Tempahan</h1>
        <p>Masukkan rujukan pelanggan dan nombor telefon yang digunakan semasa booking.</p>
        @if ($errors->any())
            <div role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('orders.access.store') }}">
            @csrf
            <label for="customer_reference">Rujukan pelanggan</label>
            <input id="customer_reference" name="customer_reference" value="{{ old('customer_reference') }}" required>
            <label for="customer_phone">Nombor telefon</label>
            <input id="customer_phone" name="customer_phone" type="tel" required>
            <button type="submit">Akses tempahan</button>
        </form>
    </main>
</body>
</html>
