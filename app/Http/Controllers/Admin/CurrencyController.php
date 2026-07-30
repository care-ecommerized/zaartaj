<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff FX management.
 *
 * Rates normally track a daily live-FX job; this screen lets staff correct a rate
 * by hand and pin it with `manual_override` so the job leaves it alone, toggle a
 * currency's availability, or read when each rate was last refreshed.
 */
class CurrencyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/currencies/index', [
            'currencies' => Currency::query()
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency) => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimals' => $currency->decimals,
                    'rate_to_base' => (float) $currency->rate_to_base,
                    'is_active' => $currency->is_active,
                    'is_base' => $currency->is_base,
                    'manual_override' => $currency->manual_override,
                    'rate_updated_at' => $currency->rate_updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): RedirectResponse
    {
        $data = $request->validated();

        // The base is always 1:1 with itself and can neither be deactivated nor
        // pinned; conversions pivot through it, so its rate is not staff-editable.
        if ($currency->is_base) {
            $currency->forceFill([
                'is_active' => true,
                'manual_override' => false,
                'rate_to_base' => 1,
            ])->save();

            return back()->with('status', "{$currency->code} is the base currency and stays 1:1.");
        }

        $currency->fill([
            'rate_to_base' => $data['rate_to_base'],
            'is_active' => $data['is_active'],
            'manual_override' => $data['manual_override'],
            'rate_updated_at' => now(),
        ])->save();

        return back()->with('status', "{$currency->code} updated.");
    }
}
