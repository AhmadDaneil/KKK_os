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
