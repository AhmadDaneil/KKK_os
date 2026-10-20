<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBookingIntent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.wabot.inbound_api_key', '');
        $provided = (string) $request->header('X-KKK-API-Key', '');

        if ($configured === '' || $provided === '' || ! hash_equals($configured, $provided)) {
            Log::warning('Rejected unauthorized booking intent request.');

            return response()->json(['message' => 'Unauthorized.', 'code' => 'UNAUTHORIZED'], 401);
        }

        return $next($request);
    }
}
