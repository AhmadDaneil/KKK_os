<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Local development CSRF exceptions
        |--------------------------------------------------------------------------
        |
        | Only local DEV endpoints are exempted. No production/customer endpoint
        | is included here, and these exceptions are not activated outside local.
        |
        */
        if (($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production') === 'local') {
            $middleware->validateCsrfTokens(except: [
                'dev/orders',
                'dev/orders/*/merge-jobs',
                'dev/orders/*/design-jobs',
                'dev/orders/*/balance-payment',
                'dev/payments/*/pay',
                'dev/orders/*/print-jobs',
                'dev/orders/*/packing-job',
                'dev/orders/*/fulfilment-job',
                'dev/fulfilment-jobs/*/ship',
                'dev/fulfilment-jobs/*/deliver',
                'dev/fulfilment-jobs/*/collect',
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
