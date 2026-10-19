<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProgressUpdate;
use App\Models\User;
use App\Services\Orders\CreateOrderProgressUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffOrderProgressUpdateController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        CreateOrderProgressUpdateService $service,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeUpload($user, $order);

        abort_if($order->isAssignmentLocked(), 422, 'Progress cannot be added after the order is closed.');

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000', 'required_without:attachment'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:20480',
                'required_without:message',
            ],
            'customer_visible' => ['sometimes', 'boolean'],
        ]);

        try {
            $service->create(
                $order,
                $user,
                $validated['message'] ?? null,
                $validated['attachment'] ?? null,
                (bool) ($validated['customer_visible'] ?? false),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['progress_update' => $exception->getMessage()]);
        }

        return back()->with('status', 'Progress update recorded successfully.');
    }

    public function attachment(Request $request, OrderProgressUpdate $progressUpdate): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->canViewAllProgressUpdates()
                || ($progressUpdate->uploader_user_id !== null
                    && $progressUpdate->uploader_user_id === $user->id),
            404,
        );

        abort_unless($progressUpdate->hasAttachment(), 404);
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

    private function authorizeUpload(User $user, Order $order): void
    {
        $authorized = match ($user->role) {
            User::ROLE_DESIGNER => $order->designJobs()
                ->where('assigned_user_id', $user->id)
                ->exists(),
            User::ROLE_PRODUCTION => $order->printJobs()
                ->where('assigned_user_id', $user->id)
                ->exists(),
            User::ROLE_OM => $order->packingJob()->exists()
                || $order->fulfilmentJob()->exists(),
            default => false,
        };

        abort_unless($user->isActiveStaff() && $authorized, 404);
    }
}
