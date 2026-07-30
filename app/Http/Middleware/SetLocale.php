<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the shopper's UI locale (and reading direction) for the request.
 *
 * The storefront chrome is bilingual: English (LTR) and Arabic (RTL). This
 * middleware decides which one this request is served in, resolved from (in
 * order): a `?lang=` query param, a `locale` cookie, an `Accept-Language`
 * header that prefers Arabic, or the configured default. Only `en` and `ar`
 * are allowed; anything else falls through to the default.
 *
 * The resolved code is applied with app()->setLocale() and, alongside the
 * computed direction, stashed on the container so HandleInertiaRequests (which
 * runs after this) and the Blade root template can read them.
 */
class SetLocale
{
    /** The locales the storefront ships. */
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        $direction = $locale === 'ar' ? 'rtl' : 'ltr';

        app()->instance('locale', $locale);
        app()->instance('locale_direction', $direction);

        return $next($request);
    }

    /**
     * The active UI locale for this request.
     */
    private function resolve(Request $request): string
    {
        $param = $request->query('lang');
        if (is_string($param) && in_array($param = strtolower(trim($param)), self::SUPPORTED, true)) {
            return $param;
        }

        $cookie = $request->cookie('locale');
        if (is_string($cookie) && in_array($cookie = strtolower(trim($cookie)), self::SUPPORTED, true)) {
            return $cookie;
        }

        if ($this->prefersArabic($request)) {
            return 'ar';
        }

        $default = (string) config('app.locale');

        return in_array($default, self::SUPPORTED, true) ? $default : 'en';
    }

    /**
     * Whether the browser's Accept-Language header prefers Arabic over English.
     *
     * We only honour the header when Arabic is the first supported language it
     * lists; a header that leads with English (or lists neither) leaves the
     * decision to the configured default.
     */
    private function prefersArabic(Request $request): bool
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(substr((string) $language, 0, 2));

            if ($primary === 'ar') {
                return true;
            }

            if ($primary === 'en') {
                return false;
            }
        }

        return false;
    }
}
