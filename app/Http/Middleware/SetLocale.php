<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = ['en', 'ar'];
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale = $request->query('lang') ?: $sessionLocale ?: config('app.locale', 'en');
        $locale = in_array($locale, $supported, true) ? $locale : 'en';

        app()->setLocale($locale);
        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
