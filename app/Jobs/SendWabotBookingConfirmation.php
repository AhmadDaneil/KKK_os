<?php

namespace App\Jobs;

use App\Models\WabotMessageLog;
use App\Services\Wabot\WabotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

class SendWabotBookingConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public int $messageLogId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WabotService $wabot): void
    {
        $log = WabotMessageLog::query()->with(['order', 'payment'])->findOrFail($this->messageLogId);
        if ($log->status === 'SENT' || $log->status === 'FAILED') {
            return;
        }

        $order = $log->order;
        $payment = $log->payment;
        if ($order->source !== 'WABOT' || $payment->status !== 'PAID' || ! filled($order->customer_reference)) {
            throw new RuntimeException('The booking confirmation is not ready.');
        }

        $encryptedUrl = $payment->metadata['customer_access']['dashboard_url_encrypted'] ?? null;
        if (! is_string($encryptedUrl) || $encryptedUrl === '') {
            throw new RuntimeException('The dashboard link is not available.');
        }

        $dashboardUrl = Crypt::decryptString($encryptedUrl);
        $message = "Booking anda telah berjaya ✅\n\n"
            ."No Tempahan: {$order->customer_reference}\n"
            .'Bayaran Booking: RM'.number_format((float) $payment->amount, 2)."\n\n"
            ."Sila lengkapkan maklumat tempahan anda di:\n{$dashboardUrl}";

        $log->update(['attempts' => $log->attempts + 1, 'requested_at' => now(), 'status' => 'PENDING']);
        $result = $wabot->sendText((string) $order->wabot_channel, $log->phone, $message);
        $log->update([
            'status' => $result['success'] ? 'SENT' : ($result['retryable'] ? 'PENDING' : 'FAILED'),
            'http_status' => $result['http_status'],
            'provider_response' => $result['response'],
            'error_code' => $result['error_code'],
            'sent_at' => $result['success'] ? now() : null,
        ]);

        if ($result['retryable']) {
            throw new RuntimeException('The Wabot send failed with a retryable error.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        WabotMessageLog::query()->whereKey($this->messageLogId)->where('status', '!=', 'SENT')->update([
            'status' => 'FAILED',
            'error_code' => 'RETRIES_EXHAUSTED',
        ]);
    }
}
