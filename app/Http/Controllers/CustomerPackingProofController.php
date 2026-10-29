<?php

namespace App\Http\Controllers;

use App\Models\PackingJobProof;
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

    public function showFile(
        Request $request,
        string $orderId,
        PackingJobProof $packingJobProof,
        CustomerOrderSessionAccessService $sessionAccess,
    ): StreamedResponse {
        $order = $sessionAccess->resolveProofing($request, $orderId);
        $packingJob = $order->packingJob;
        $path = $packingJobProof->storage_path;
        $mimeType = $packingJobProof->mime_type;

        abort_unless(
            $packingJob?->id === $packingJobProof->packing_job_id
                && $packingJob->status === 'PACKED'
                && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
                && filled($path)
                && Storage::disk('local')->exists($path),
            404,
        );

        return Storage::disk('local')->response(
            $path,
            $packingJobProof->original_name,
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
