<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Resolve the shopper's presentment currency for the request.
 *
 * Presentment is display-only — the server still charges from the DB in the base
 * currency (AED). This middleware only decides which currency prices are *shown*
 * in, resolved from (in order): a `?currency=` query param, a `currency` cookie,
 * or the base currency as the default.
 *
 * The resolved code is stashed on the container as `presentment_currency` so
 * HandleInertiaRequests (which runs after this) can share it with the frontend.
 * Everything here is defensive: a missing or unseeded `currencies` table must
 * never break a page render, so failures fall back to the config base.
 */
class SetCurrency
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->instance('presentment_currency', $this->resolve($request));

        return $next($request);
    }

    /**
     * Country → presentment currency. Used when the shopper has not chosen one
     * explicitly and the request carries a geo country (e.g. Cloudflare's
     * CF-IPCountry header in production). Countries not listed fall through to
     * the Taka default.
     *
     * @var array<string, string>
     */
    private const COUNTRY_CURRENCY = [
        'BD' => 'BDT',
        'AE' => 'AED',
        'SA' => 'SAR',
        'QA' => 'QAR',
        'KW' => 'KWD',
        'OM' => 'OMR',
        'BH' => 'BHD',
        'US' => 'USD',
    ];

    /**
     * The active presentment currency code for this request, resolved from (in
     * order): an explicit `?currency=` / cookie choice, the visitor's country
     * from a CDN geo header, then Taka as the house default.
     */
    private function resolve(Request $request): string
    {
        $active = $this->activeCodes();

        // 1. An explicit choice from the switcher (query param or saved cookie).
        $requested = $request->query('currency') ?? $request->cookie('currency');

        if (is_string($requested)) {
            $requested = strtoupper(trim($requested));

            if (in_array($requested, $active, true)) {
                return $requested;
            }
        }

        // 2. The visitor's country, when a geo header is present (production CDN).
        $country = strtoupper(trim((string) ($request->header('CF-IPCountry') ?? $request->header('X-Country') ?? '')));
        $byCountry = self::COUNTRY_CURRENCY[$country] ?? null;

        if ($byCountry !== null && in_array($byCountry, $active, true)) {
            return $byCountry;
        }

        // 3. Default to Taka for this Bangladesh-based shop; the config base is
        //    the last resort if BDT is somehow inactive.
        return in_array('BDT', $active, true) ? 'BDT' : $this->baseCode();
    }

    /**
     * The base currency code, falling back to config when the table is unseeded.
     */
    private function baseCode(): string
    {
        try {
            return Currency::base()->code;
        } catch (Throwable) {
            return (string) config('payment.currency');
        }
    }

    /**
     * The codes a shopper may currently be shown prices in.
     *
     * @return list<string>
     */
    private function activeCodes(): array
    {
        try {
            return Currency::query()->active()->pluck('code')->all();
        } catch (Throwable) {
            return [];
        }
    }
}
