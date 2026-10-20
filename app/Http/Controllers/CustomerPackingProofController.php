<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPackingProofController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $sessionAccess,
    ): StreamedResponse {
        $order = $sessionAccess->resolveProofing($request, $orderId);
        $order->loadMissing('packingJob');
        $packingJob = $order->packingJob;
        $path = $packingJob?->proof_storage_path;
        $mimeType = $packingJob?->proof_mime_type;

        abort_unless(
            $packingJob?->status === 'PACKED'
                && filled($path)
                && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
                && Storage::disk('local')->exists($path),
            404,
        );

        return Storage::disk('local')->response(
            $path,
            $packingJob->proof_original_name ?? 'packing-proof',
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
