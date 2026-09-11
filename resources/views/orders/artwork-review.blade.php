<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Artwork Review - {{ $order->order_id }}</title>
</head>
<body>
    <h1>Artwork Review</h1>
    <p><strong>Order ID:</strong> {{ $order->order_id }}</p>
    <p><strong>Status Order:</strong> {{ $order->status }}</p>
    @if (session('success'))<div>{{ session('success') }}</div>@endif
    @foreach ($order->designJobs->sortBy('side') as $designJob)
        @php($latestArtwork = $designJob->artworkVersions->sortByDesc('version_number')->first())
        <section>
            <h2>Pakej {{ ucfirst(strtolower($designJob->side)) }}</h2>
            <p>Status: {{ $designJob->status }}</p>
            @if ($latestArtwork)
                <p><strong>Artwork Version:</strong> v{{ $latestArtwork->version_number }}</p>
                <p><strong>File:</strong> {{ $latestArtwork->original_filename ?? $latestArtwork->storage_path }}</p>
                @if ($latestArtwork->preview_storage_path)<p>Preview tersedia: {{ $latestArtwork->preview_storage_path }}</p>@endif
            @else
                <p>Artwork belum tersedia.</p>
            @endif
            @if ($designJob->status === 'DESIGN_READY')
                <form method="POST" action="{{ route('orders.artwork.approve', ['orderId' => $order->order_id, 'designJobId' => $designJob->id]) }}">
                    @csrf
                    <button type="submit">Luluskan Artwork</button>
                </form>
                <form method="POST" action="{{ route('orders.artwork.correction', ['orderId' => $order->order_id, 'designJobId' => $designJob->id]) }}">
                    @csrf
                    <label>Pembetulan diperlukan<textarea name="correction_comment" required></textarea></label>
                    <button type="submit">Minta Pembetulan</button>
                </form>
            @elseif ($designJob->status === 'CORRECTION_REQUESTED')
                <p>Pembetulan sedang menunggu tindakan designer.</p>
            @elseif ($designJob->status === 'DESIGN_APPROVED')
                <p>Artwork ini telah diluluskan.</p>
            @endif
        </section>
    @endforeach
</body>
</html>
