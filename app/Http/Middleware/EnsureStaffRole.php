<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isActiveStaff()) {
            abort(403);
        }

        $hasRequiredRole = collect($roles)
            ->contains(fn (string $role): bool => $user->hasStaffRole($role));

        if (! $user->isAdmin() && ! $hasRequiredRole) {
            abort(403);
        }

        return $next($request);
    }
}