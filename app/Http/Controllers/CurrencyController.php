<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Storefront currency switcher.
 *
 * Persists the shopper's chosen presentment currency in a long-lived cookie that
 * SetCurrency reads on subsequent requests. Display-only: the choice changes which
 * currency prices are shown in, never what the server charges.
 */
class CurrencyController extends Controller
{
    public function set(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => [
                'required',
                'string',
                'size:3',
                function (string $attribute, mixed $value, callable $fail): void {
                    $active = Currency::query()->active()
                        ->whereKey(strtoupper((string) $value))
                        ->exists();

                    if (! $active) {
                        $fail('The selected currency is not available.');
                    }
                },
            ],
        ]);

        $code = strtoupper($validated['currency']);

        // One year, so a returning shopper keeps their currency without re-picking.
        return back()->withCookie(cookie('currency', $code, 60 * 24 * 365));
    }
}
