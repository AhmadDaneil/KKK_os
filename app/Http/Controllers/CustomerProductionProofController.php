<?php

namespace App\Http\Controllers;

use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerProductionProofController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        int $printJobId,
        int $file,
        CustomerOrderSessionAccessService $sessionAccess,
    ): StreamedResponse {
        $order = $sessionAccess->resolveProofing($request, $orderId);
        $printJob = $order->printJobs()->findOrFail($printJobId);
        $progressFile = data_get($printJob->progress_files ?? [], $file);
        $path = data_get($progressFile, 'path');
        $mimeType = data_get($progressFile, 'mime_type', 'application/octet-stream');

        abort_unless(
            data_get($progressFile, 'customer_visible') === true
                && data_get($progressFile, 'uploaded_by_role') === 'PRODUCTION'
                && filled(data_get($progressFile, 'uploaded_by_user_id'))
                && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)
                && filled($path)
                && Storage::disk('local')->exists($path),
            404,
        );

        return Storage::disk('local')->response(
            $path,
            data_get($progressFile, 'original_name', 'production-proof'),
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
