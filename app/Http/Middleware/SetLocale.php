<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED = ['en', 'ar'];

    /**
     * Public-facing routes that should default to Arabic.
     *
     * @var array<int, string>
     */
    private const PUBLIC_ROUTES = [
        'home',
        'login',
        'apply',
        'application.status',
        'downloads.terms-and-conditions',
        'downloads.membership-application-form-template',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = $request->session()->get('locale');
        $userLocale = $request->user()?->preferred_locale;

        $locale = $sessionLocale ?: $userLocale;

        if (! is_string($locale) || $locale === '') {
            $routeName = $request->route()?->getName();

            if (is_string($routeName) && in_array($routeName, self::PUBLIC_ROUTES, true)) {
                $locale = 'ar';
            } else {
                $locale = config('app.locale', 'en');
            }
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
