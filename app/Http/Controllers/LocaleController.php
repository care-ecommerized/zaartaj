<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Storefront language switcher.
 *
 * Persists the shopper's chosen UI locale in a long-lived cookie that SetLocale
 * reads on subsequent requests. Only `en` and `ar` are accepted.
 */
class LocaleController extends Controller
{
    public function set(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['en', 'ar'])],
        ]);

        // One year, so a returning shopper keeps their language without re-picking.
        return back()->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
    }
}
