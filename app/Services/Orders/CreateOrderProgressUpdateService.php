<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderProgressUpdate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CreateOrderProgressUpdateService
{
    public function create(
        Order $order,
        User $uploader,
        ?string $message,
        ?UploadedFile $attachment,
        bool $customerVisible,
    ): OrderProgressUpdate {
        $message = filled($message) ? trim((string) $message) : null;
        $diskName = 'local';
        $path = null;
        $fileHash = null;

        if ($attachment) {
            $fileHash = hash_file('sha256', $attachment->getRealPath());
            $extension = strtolower($attachment->extension() ?: 'bin');
            $path = Storage::disk($diskName)->putFileAs(
                'order-progress/'.$order->id.'/'.Str::uuid(),
                $attachment,
                'attachment.'.$extension,
            );

            if ($path === false) {
                throw new RuntimeException('Fail progress tidak dapat disimpan.');
            }
        }

        $visibility = $customerVisible
            ? OrderProgressUpdate::VISIBILITY_CUSTOMER
            : OrderProgressUpdate::VISIBILITY_INTERNAL;
        $deduplicationKey = hash('sha256', implode('|', [
            $order->id,
            $uploader->id,
            $uploader->role,
            $order->status,
            $visibility,
            mb_strtolower($message ?? ''),
            $fileHash ?? '',
        ]));

        try {
            return DB::transaction(fn () => OrderProgressUpdate::create([
                'order_id' => $order->id,
                'uploader_user_id' => $uploader->id,
                'uploader_role' => $uploader->role,
                'order_status' => $order->status,
                'message' => $message,
                'visibility' => $visibility,
                'published_at' => $customerVisible ? now() : null,
                'attachment_disk' => $attachment ? $diskName : null,
                'attachment_path' => $path,
                'attachment_original_name' => $attachment?->getClientOriginalName(),
                'attachment_mime_type' => $attachment?->getMimeType(),
                'attachment_size' => $attachment?->getSize(),
                'attachment_sha256' => $fileHash,
                'deduplication_key' => $deduplicationKey,
            ]));
        } catch (QueryException $exception) {
            if ($path) {
                Storage::disk($diskName)->delete($path);
            }

            if (str_contains(strtolower($exception->getMessage()), 'deduplication')) {
                throw new RuntimeException('Kemas kini progress yang sama telah direkodkan.', previous: $exception);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk($diskName)->delete($path);
            }

            throw $exception;
        }
    }
}
