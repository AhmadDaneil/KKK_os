<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerCourierProofController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        CustomerOrderSessionAccessService $sessionAccess,
    ): StreamedResponse {
        $order = $sessionAccess->resolveProofing($request, $orderId);
        $order->loadMissing(['packingJob', 'fulfilmentJob']);
        $packingJob = $order->packingJob;
        $fulfilmentJob = $order->fulfilmentJob;

        abort_unless(
            $fulfilmentJob?->method === 'COURIER'
                && $fulfilmentJob->status === 'COMPLETED'
                && $packingJob
                && filled($packingJob->proof_storage_path)
                && Storage::disk('local')->exists($packingJob->proof_storage_path),
            404,
        );

        return Storage::disk('local')->response(
            $packingJob->proof_storage_path,
            $packingJob->proof_original_name ?? 'courier-proof',
            [
                'Content-Type' => $packingJob->proof_mime_type ?? 'application/octet-stream',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
