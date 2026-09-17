<?php

namespace App\Http\Middleware;

use App\Models\User;
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

        if ($user->role !== User::ROLE_ADMIN && ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}