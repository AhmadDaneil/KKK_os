<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Final Review - {{ $review['order_id'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 960px; margin: 32px auto; padding: 0 16px; }
        section { border: 1px solid #ddd; border-radius: 8px; padding: 18px; margin-bottom: 18px; }
        h1, h2 { margin-top: 0; }
        dl { display: grid; grid-template-columns: 220px 1fr; gap: 8px 16px; }
        dt { font-weight: bold; }
        .notice { background: #f6f6f6; padding: 12px; border-radius: 6px; }
        button { padding: 12px 18px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Final Review</h1>
    <p class="notice">Sila semak maklumat yang telah dibersihkan oleh sistem sebelum membuat pengesahan akhir.</p>

    <section>
        <h2>Order</h2>
        <dl>
            <dt>Order ID</dt><dd>{{ $review['order_id'] }}</dd>
            <dt>Jumlah Pakej</dt><dd>{{ $review['package_count'] }}</dd>
            <dt>Pengantin Lelaki</dt><dd>{{ $review['couple']['groom_name'] }}</dd>
            <dt>Pengantin Perempuan</dt><dd>{{ $review['couple']['bride_name'] }}</dd>
        </dl>
    </section>

    @foreach ($review['package_sides'] as $side)
        <section>
            <h2>Pakej {{ ucfirst(strtolower($side['side'])) }}</h2>
            <dl>
                <dt>Kod Design</dt><dd>{{ $side['design']['design_code'] }}</dd>
                <dt>Nama Bapa</dt><dd>{{ $side['parents']['father_name'] }}</dd>
                <dt>Nama Ibu</dt><dd>{{ $side['parents']['mother_name'] }}</dd>
                <dt>Tarikh Majlis</dt><dd>{{ $side['event']['event_date'] }}</dd>
                <dt>Masa</dt><dd>{{ $side['event']['meal_time'] }}</dd>
                <dt>Tempat</dt><dd>{{ $side['event']['venue_name'] }}</dd>
                <dt>Alamat</dt><dd>{{ $side['event']['full_address'] }}</dd>
            </dl>

            <h3>Contact Persons</h3>
            <ol>
                @foreach ($side['event']['contacts'] as $contact)
                    <li>{{ $contact['name'] }} — {{ $contact['phone'] }}</li>
                @endforeach
            </ol>
        </section>
    @endforeach

    <section>
        <h2>Fulfilment</h2>
        <dl>
            <dt>Kaedah</dt><dd>{{ $review['fulfilment']['method'] }}</dd>
            @if ($review['fulfilment']['method'] === 'COURIER')
                <dt>Penerima</dt><dd>{{ $review['fulfilment']['recipient_name'] }}</dd>
                <dt>Telefon</dt><dd>{{ $review['fulfilment']['recipient_phone'] }}</dd>
                <dt>Alamat</dt><dd>{{ $review['fulfilment']['shipping_address'] }}</dd>
            @endif
        </dl>
    </section>

    <form method="POST" action="{{ route('orders.confirm.store', ['orderId' => $review['order_id']]) }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <p>
            <label>
                <input type="checkbox" name="responsibility_acknowledged" value="1" required>
                Saya telah menyemak maklumat di atas dan mengesahkan bahawa ia betul.
            </label>
        </p>

        <button type="submit">Sahkan Maklumat Tempahan</button>
    </form>
</body>
</html>
