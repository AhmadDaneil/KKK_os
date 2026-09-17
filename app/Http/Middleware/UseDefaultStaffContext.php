<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseDefaultStaffContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentUser = $request->user();

        if ($currentUser?->isActiveStaff()) {
            return $next($request);
        }

        abort_unless(
            in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true),
            403,
            'Staff overview tidak membenarkan perubahan tanpa konteks pengguna yang sah.'
        );

        $request->attributes->set('staff_overview_mode', true);

        $overviewUser = new User([
            'name' => 'Staff Overview',
            'email' => 'overview@local.invalid',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        Auth::setUser($overviewUser);
        $request->setUserResolver(fn () => $overviewUser);

        try {
            return $next($request);
        } finally {
            Auth::forgetGuards();
        }
    }
}
