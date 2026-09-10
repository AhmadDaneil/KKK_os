<?php

namespace App\Providers;

use App\Contracts\Photoshop\CardQuantityProviderContract;
use App\Services\Photoshop\DatabaseCardQuantityProvider;
use Illuminate\Support\ServiceProvider;

class PhotoshopExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CardQuantityProviderContract::class,
            DatabaseCardQuantityProvider::class,
        );
    }
}
