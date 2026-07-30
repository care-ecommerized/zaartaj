<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Currency;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'isAdmin' => $request->user()?->is_admin === true,
            ],
            // Site chrome: the header and footer render this on every page.
            'shopCategories' => $this->navigationCategories(),
            // The shopper's active presentment currency and the selector's options.
            'currency' => $this->presentmentCurrency(),
            'currencies' => $this->activeCurrencies(),
            // UI locale + reading direction SetLocale resolved for this request,
            // and the active-locale message catalogue the React chrome reads.
            'locale' => app()->getLocale(),
            'direction' => app()->has('locale_direction') ? (string) app('locale_direction') : 'ltr',
            'translations' => $this->translations(app()->getLocale()),
        ]);
    }

    /**
     * The active presentment currency SetCurrency resolved for this request.
     *
     * Falls back to the config base with sensible defaults when the currencies
     * table is unseeded, so a fresh install never breaks the header.
     *
     * @return array{code: string, symbol: string, decimals: int}
     */
    protected function presentmentCurrency(): array
    {
        $code = app()->has('presentment_currency')
            ? (string) app('presentment_currency')
            : (string) config('payment.currency');

        try {
            $currency = Currency::query()->whereKey($code)->first();

            if ($currency) {
                return [
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                    'decimals' => (int) $currency->decimals,
                ];
            }
        } catch (Throwable) {
            // Fall through to the config default below.
        }

        return ['code' => $code, 'symbol' => $code, 'decimals' => 2];
    }

    /**
     * The active currencies the storefront selector offers.
     *
     * @return list<array{code: string, symbol: string, decimals: int, rate_to_base: float}>
     */
    protected function activeCurrencies(): array
    {
        try {
            return Currency::query()->active()
                ->orderByDesc('is_base')
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency) => [
                    'code' => $currency->code,
                    'symbol' => $currency->symbol,
                    'decimals' => (int) $currency->decimals,
                    'rate_to_base' => (float) $currency->rate_to_base,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The active-locale UI message catalogue, loaded from lang/{locale}.json.
     *
     * Cached per locale so the JSON is read from disk once every ten minutes.
     * A missing or malformed file never breaks a render — the chrome falls back
     * to its message keys on the frontend when a string is absent.
     *
     * @return array<string, string>
     */
    protected function translations(string $locale): array
    {
        return Cache::remember(
            'shop.translations.'.$locale,
            now()->addMinutes(10),
            function () use ($locale): array {
                $path = lang_path("{$locale}.json");

                if (! is_file($path)) {
                    return [];
                }

                $decoded = json_decode((string) file_get_contents($path), true);

                return is_array($decoded) ? $decoded : [];
            }
        );
    }

    /**
     * The shop's categories, in the order config/catalog.php lists them.
     *
     * Every category is shown, including ones nothing has been filed under yet:
     * they are a deliberate description of what the shop sells, so an empty
     * Jewellery page reads as "coming soon" rather than the category vanishing
     * until the first product arrives.
     *
     * @return list<array{slug: string, name: string}>
     */
    protected function navigationCategories(): array
    {
        // Per-locale key so an Arabic request never serves the English-cached
        // navigation (and vice versa) once category names become translatable.
        return Cache::remember(
            'shop.nav.categories.'.app()->getLocale(),
            now()->addMinutes(10),
            fn () => Category::navigable()
                ->get(['name', 'slug'])
                ->map(fn (Category $category) => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                ])
                ->values()
                ->all()
        );
    }
}
