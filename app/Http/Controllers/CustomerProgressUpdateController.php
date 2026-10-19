<?php

namespace App\Http\Controllers;

use App\Models\OrderProgressUpdate;
use App\Services\Orders\CustomerOrderSessionAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerProgressUpdateController extends Controller
{
    public function attachment(
        Request $request,
        string $orderId,
        OrderProgressUpdate $progressUpdate,
        CustomerOrderSessionAccessService $sessionAccess,
    ): StreamedResponse {
        $order = $sessionAccess->resolveProofing($request, $orderId);

        abort_unless(
            $progressUpdate->order_id === $order->id
                && $progressUpdate->isCustomerVisible()
                && $progressUpdate->hasAttachment(),
            404,
        );
        abort_unless(
            Storage::disk($progressUpdate->attachment_disk)->exists($progressUpdate->attachment_path),
            404,
        );

        return Storage::disk($progressUpdate->attachment_disk)->response(
            $progressUpdate->attachment_path,
            $progressUpdate->attachment_original_name ?: 'progress-attachment',
            ['Content-Type' => $progressUpdate->attachment_mime_type ?: 'application/octet-stream'],
        );
    }
}
