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
     * The active presentment currency code for this request.
     */
    private function resolve(Request $request): string
    {
        $base = $this->baseCode();

        $requested = $request->query('currency') ?? $request->cookie('currency');

        if (is_string($requested)) {
            $requested = strtoupper(trim($requested));

            if (in_array($requested, $this->activeCodes(), true)) {
                return $requested;
            }
        }

        return $base;
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
