<!doctype html>
<html lang="en" class="brand-customer-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="same-origin">
    <title>Order Proofing — KingKadKahwin</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-theme.css') }}?v={{ filemtime(public_path('css/customer-theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-service.css') }}?v={{ filemtime(public_path('css/customer-service.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/customer-brand.css') }}?v={{ filemtime(public_path('css/customer-brand.css')) }}">
</head>
<body class="utility-page brand-order-progress brand-order-proofing">
    <header class="site-header compact-header">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo-image" src="{{ asset('images/landing/kingkadkahwin-logo.png') }}" alt="" width="62" height="62" decoding="async">
            <span><b>KingKadKahwin</b><small>Beautiful cards, meaningful memories</small></span>
        </a>
        <nav aria-label="Proofing navigation">
            <a class="header-link nav-progress" href="{{ route('public.orders.progress', ['order_id' => $order->order_id]) }}">← Track Progress</a>
            <a class="header-link nav-progress" href="{{ route('home') }}">Back to Home</a>
        </nav>
    </header>

    <main class="proofing-page">
        @php
            $customerProofUrl = static fn (string $routeName, array $parameters): string =>
                \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    $routeName,
                    $proofingExpiresAt,
                    $parameters,
                    false,
                );
        @endphp

        <section class="proofing-hero">
            <div>
                <p class="eyebrow">Customer proofing</p>
                <h1>Your Order Progress</h1>
                <p>View the latest customer-visible updates and proofs shared by the KingKadKahwin team.</p>
            </div>
            <div class="proofing-order-id">
                <span>ORDER ID</span>
                <strong>{{ $order->order_id }}</strong>
                <b>{{ $progress['percentage'] }}%</b>
            </div>
            <div class="public-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}">
                <span style="width: {{ $progress['percentage'] }}%"></span>
            </div>
            <p class="proofing-current-status"><strong>{{ $progress['label'] }}</strong><span>{{ $progress['message'] }}</span></p>
        </section>

        <section class="proofing-updates" aria-labelledby="proofing-updates-title">
            <div class="proofing-heading">
                <div>
                    <p class="eyebrow">Proofs and updates</p>
                    <h2 id="proofing-updates-title">Shared with you</h2>
                </div>
                <p>Only files approved for customer viewing appear here.</p>
            </div>

            @php
                $productionProofs = $order->printJobs
                    ->flatMap(function ($job) {
                        return collect($job->progress_files ?? [])
                            ->values()
                            ->map(fn ($file, $index) => [
                                'job' => $job,
                                'file' => $file,
                                'index' => $index,
                            ])
                            ->filter(fn ($proof) => data_get($proof['file'], 'customer_visible') === true
                                && data_get($proof['file'], 'uploaded_by_role') === 'PRODUCTION'
                                && filled(data_get($proof['file'], 'path'))
                                && filled(data_get($proof['file'], 'uploaded_by_user_id')));
                    })
                    ->values();
                $designProofs = $order->designJobs
                    ->filter(fn ($job) => in_array($job->status, ['DESIGN_READY', 'CORRECTION_REQUESTED', 'DESIGN_APPROVED'], true))
                    ->map(function ($job) {
                        $artwork = $job->artworkVersions->sortByDesc('version_number')->first();
                        $files = collect($artwork?->preview_files ?: array_filter([
                            $artwork?->preview_storage_path ? ['path' => $artwork->preview_storage_path] : null,
                        ]))->filter(
                            fn ($file) => filled(data_get($file, 'watermarked_path')) || filled(data_get($file, 'path'))
                        )->values();

                        return $artwork && $files->isNotEmpty()
                            ? ['job' => $job, 'artwork' => $artwork, 'files' => $files]
                            : null;
                    })
                    ->filter()
                    ->values();
                $hasHandoverConfirmation = in_array($order->fulfilmentJob?->status, ['COMPLETED', 'COLLECTED'], true);
            @endphp

            @if ($designProofs->isNotEmpty())
                <div class="design-proof-grid" aria-label="Designer artwork proofs">
                    @foreach ($designProofs as $proof)
                        @php
                            $designJob = $proof['job'];
                            $artwork = $proof['artwork'];
                            $sideLabel = $designJob->side === 'LELAKI' ? 'Groom' : 'Bride';
                        @endphp
                        <article class="design-proof-card">
                            <div class="design-proof-card-heading">
                                <span>Designer Proof</span>
                                <b>Version {{ $artwork->version_number }}</b>
                            </div>
                            <h3>{{ $sideLabel }} Package</h3>
                            <p>Review the latest artwork preview uploaded by the Designer.</p>
                            <div class="design-proof-actions">
                                @foreach ($proof['files'] as $previewIndex => $previewFile)
                                    @php
                                        $previewType = strtoupper(data_get($previewFile, 'artwork_type', 'CARD'));
                                        $previewLabel = match ($previewType) {
                                            'BANNER' => 'Banner',
                                            'BANTING' => 'Bunting',
                                            default => count($proof['files']) > 1 ? 'Wedding Card '.($previewIndex + 1) : 'Wedding Card',
                                        };
                                    @endphp
                                    <a class="button button-primary" href="{{ $customerProofUrl('orders.artwork.preview', [
                                        'orderId' => $order->order_id,
                                        'designJobId' => $designJob->id,
                                        'file' => $previewIndex,
                                    ]) }}" target="_blank" rel="noopener noreferrer">
                                        View Designer Proof · {{ $sideLabel }} · {{ $previewLabel }}
                                    </a>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            <section class="design-proof-card production-proof-section" aria-labelledby="production-proof-title">
                <div class="design-proof-card-heading">
                    <span>Production Proof</span>
                    @if ($productionProofs->isNotEmpty())
                        <b>{{ $productionProofs->count() }} {{ \Illuminate\Support\Str::plural('file', $productionProofs->count()) }}</b>
                    @endif
                </div>
                <h3 id="production-proof-title">Production progress</h3>
                <p>View production progress files shared for your order.</p>

                @if ($productionProofs->isNotEmpty())
                    <div class="design-proof-actions">
                        @foreach ($productionProofs as $proof)
                            @php
                                $sideLabel = $proof['job']->side === 'LELAKI' ? 'Groom' : 'Bride';
                                $fileNumber = $loop->iteration;
                            @endphp
                            <a class="button button-primary" href="{{ $customerProofUrl('public.orders.production-proof', [
                                'orderId' => $order->order_id,
                                'printJobId' => $proof['job']->id,
                                'file' => $proof['index'],
                            ]) }}" target="_blank" rel="noopener noreferrer">
                                View Production Proof · {{ $sideLabel }} · File {{ $fileNumber }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="production-proof-empty">No production proof has been shared yet.</p>
                @endif
            </section>

            <section class="design-proof-card production-proof-section" aria-labelledby="packing-proof-title">
                <div class="design-proof-card-heading">
                    <span>Packing Proof</span>
                    @if ($packingProofAvailable)
                        <b>1 file</b>
                    @endif
                </div>
                <h3 id="packing-proof-title">Packing progress</h3>
                <p>View the packing photo shared for your order.</p>

                @if ($packingProofAvailable)
                    <div class="design-proof-actions">
                        <a class="button button-primary" href="{{ $customerProofUrl('orders.packing-proof.show', ['orderId' => $order->order_id]) }}" target="_blank" rel="noopener noreferrer">
                            View Packing Proof
                        </a>
                    </div>
                @else
                    <p class="production-proof-empty">No packing proof has been shared yet.</p>
                @endif
            </section>

            @php($fulfilmentJob = $order->fulfilmentJob)
            <section class="design-proof-card production-proof-section" aria-labelledby="handover-proof-title">
                <div class="design-proof-card-heading">
                    <span>{{ $fulfilmentJob?->method === 'PICKUP' ? 'Pickup Confirmation' : 'Courier Proof' }}</span>
                    @if ($fulfilmentJob?->status === 'COMPLETED' || $fulfilmentJob?->status === 'COLLECTED')
                        <b>Complete</b>
                    @endif
                </div>
                @if ($fulfilmentJob?->method === 'PICKUP')
                    <h3 id="handover-proof-title">Pickup confirmation</h3>
                    @if ($fulfilmentJob->status === 'COLLECTED')
                        <p>Your order was collected{{ $fulfilmentJob->collected_at ? ' on '.$fulfilmentJob->collected_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') : '' }}.</p>
                        @if (filled($fulfilmentJob->completion_reference))
                            <p>Collection reference: <strong>{{ $fulfilmentJob->completion_reference }}</strong></p>
                        @endif
                    @else
                        <p>Pickup confirmation will appear here once your order has been collected.</p>
                    @endif
                @elseif ($fulfilmentJob?->method === 'COURIER')
                    <h3 id="handover-proof-title">Courier proof</h3>
                    @if ($fulfilmentJob->status === 'COMPLETED' && filled($order->packingJob?->proof_storage_path))
                        <p>View the parcel handover photo and courier details.</p>
                        <div class="design-proof-actions">
                            <a class="button button-primary" href="{{ $customerProofUrl('orders.courier-proof.show', ['orderId' => $order->order_id]) }}" target="_blank" rel="noopener noreferrer">
                                View Courier Proof
                            </a>
                        </div>
                        @if (filled($fulfilmentJob->courier_provider) || filled($fulfilmentJob->tracking_number))
                            <p>{{ $fulfilmentJob->courier_provider }}{{ $fulfilmentJob->tracking_number ? ' · '.$fulfilmentJob->tracking_number : '' }}</p>
                        @endif
                    @else
                        <p>Courier proof will appear here once the parcel has been handed to the courier.</p>
                    @endif
                @else
                    <h3 id="handover-proof-title">Handover confirmation</h3>
                    <p>Pickup or courier proof will appear here when available.</p>
                @endif
            </section>

            @if ($order->progressUpdates->isNotEmpty())
                <ol class="proofing-list">
                    @foreach ($order->progressUpdates as $update)
                        <li>
                            <div class="proofing-update-meta">
                                <span>{{ match ($update->uploader_role) { 'DESIGNER' => 'Designer', 'PRODUCTION' => 'Production', 'OPERATION_MANAGEMENT' => 'OM', default => 'KKK Team' } }}</span>
                                <time datetime="{{ $update->published_at->toIso8601String() }}">{{ $update->published_at->timezone(config('app.display_timezone'))->format('d/m/Y, H:i') }}</time>
                            </div>
                            <h3>{{ str($update->order_status)->replace('_', ' ')->title() }}</h3>
                            <p>{{ $update->message ?: 'A new progress proof has been shared for your order.' }}</p>
                            @if ($update->hasAttachment())
                                <a class="button button-primary" href="{{ $customerProofUrl('orders.progress-updates.attachment', ['orderId' => $order->order_id, 'progressUpdate' => $update]) }}" target="_blank" rel="noopener">View Proof</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @elseif ($designProofs->isEmpty() && $productionProofs->isEmpty() && ! $packingProofAvailable && ! $hasHandoverConfirmation)
                <div class="proofing-empty">
                    <span aria-hidden="true">⌛</span>
                    <h3>No proof has been shared yet</h3>
                    <p>The latest customer-visible proof will appear here once it is uploaded by our team.</p>
                </div>
            @endif
        </section>
    </main>

    @php($orderId = $order->order_id)
    @include('public.partials.customer-service')
    @include('public.partials.theme-toggle')
</body>
</html>
