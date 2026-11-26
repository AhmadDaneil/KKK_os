<?php

namespace App\Jobs;

use App\Models\CustomerEmailNotification;
use App\Services\Orders\CustomerOrderEmailNotificationService;
use App\Services\Orders\ValidateOrderCompletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCustomerEmailNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    public function handle(
        CustomerOrderEmailNotificationService $notifications,
        ValidateOrderCompletionService $completionValidator,
    ): void {
        $notifications->send($this->notificationId, $completionValidator);
    }

    public function failed(?Throwable $exception): void
    {
        CustomerEmailNotification::query()
            ->whereKey($this->notificationId)
            ->where('status', CustomerEmailNotification::STATUS_PENDING)
            ->update([
                'status' => CustomerEmailNotification::STATUS_FAILED,
                'error_code' => 'MAIL_DELIVERY_RETRIES_EXHAUSTED',
                'updated_at' => now(),
            ]);
    }
}
