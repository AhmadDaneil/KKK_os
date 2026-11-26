<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('gmail-api', static fn (array $config): GmailApiTransport => new GmailApiTransport(
            clientId: (string) ($config['client_id'] ?? ''),
            clientSecret: (string) ($config['client_secret'] ?? ''),
            refreshToken: (string) ($config['refresh_token'] ?? ''),
        ));

        Order::observe(OrderObserver::class);

        RateLimiter::for('balance-payment', function (Request $request): Limit {
            $orderId = (string) $request->route('orderId');

            return Limit::perMinute(10)->by($request->ip().'|'.$orderId);
        });

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return route('admin.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
