<?php

namespace App\Providers;

use App\Contracts\Backup\DatabaseSnapshotter;
use App\Services\Backup\NativeDatabaseSnapshotter;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DatabaseSnapshotter::class, NativeDatabaseSnapshotter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
