<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database --isolated')
    ->dailyAt((string) config('backup.database.daily_at', '02:00'))
    ->timezone((string) config('app.timezone'))
    ->environments(['production'])
    ->withoutOverlapping(360)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('backup.enabled'));

Schedule::command('backup:files --isolated')
    ->dailyAt((string) config('backup.files.daily_at', '02:30'))
    ->timezone((string) config('app.timezone'))
    ->environments(['production'])
    ->withoutOverlapping(360)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('backup.enabled'));

Schedule::command('payments:reconcile-pending-billplz')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('services.billplz.enabled'));

Schedule::command('customers:send-order-reminders')
    ->everyThirtyMinutes()
    ->timezone((string) config('app.display_timezone', 'Asia/Kuala_Lumpur'))
    ->between('09:00', '21:00')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->when(static fn (): bool => (bool) config('customer_reminders.enabled'));
