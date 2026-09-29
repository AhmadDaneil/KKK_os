<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApplicationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', 'ms');
        app()->setLocale(in_array($locale, ['ms', 'en'], true) ? $locale : 'ms');

        return $next($request);
    }
}
