<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Setting;
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
            // Admin-only chrome counters for the admin shell's notifications bell.
            // Null for guests/customers so the storefront never pays for the query.
            'adminBadges' => $request->user()?->is_admin === true ? $this->adminBadges() : null,
            // Site chrome: the header and footer render this on every page.
            'shopCategories' => $this->navigationCategories(),
            // Store identity + contact + socials the footer renders, editable from
            // the admin Settings screen. Blank values fall back on the frontend.
            'storeSettings' => $this->storeSettings(),
            // The shopper's active presentment currency and the selector's options.
            'currency' => $this->presentmentCurrency(),
            'currencies' => $this->activeCurrencies(),
            // UI locale + reading direction SetLocale resolved for this request,
            // and the active-locale message catalogue the React chrome reads.
            'locale' => app()->getLocale(),
            'direction' => app()->has('locale_direction') ? (string) app('locale_direction') : 'ltr',
            'translations' => $this->translations(app()->getLocale()),
            // Public config the Tabby/Tamara promo widgets read on product/cart
            // pages. Only publishable keys are exposed — never the secret keys.
            'bnpl' => $this->bnplConfig(),
        ]);
    }

    /**
     * Cheap counters for the admin shell's notifications bell: orders awaiting
     * confirmation and open checkout sessions worth chasing. Guarded to admins by
     * the caller; wrapped so an unseeded table never breaks a render.
     *
     * @return array{pendingOrders: int, openCheckouts: int, total: int}
     */
    protected function adminBadges(): array
    {
        try {
            $pendingOrders = Order::query()
                ->where('status', OrderStatus::Pending->value)
                ->count();

            $openCheckouts = CheckoutSession::query()
                ->where('status', CheckoutSession::STATUS_OPEN)
                ->count();

            return [
                'pendingOrders' => $pendingOrders,
                'openCheckouts' => $openCheckouts,
                'total' => $pendingOrders + $openCheckouts,
            ];
        } catch (Throwable) {
            return ['pendingOrders' => 0, 'openCheckouts' => 0, 'total' => 0];
        }
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
     * Public config for the Tabby/Tamara BNPL promo widgets.
     *
     * The widgets quote instalments in the provider's settlement currency, which
     * is the store's base currency (AED), so product prices need no conversion.
     * Only the publishable keys go to the client; the secret keys never leave the
     * server.
     *
     * @return array{currency: string, tabby: array{publicKey: string, merchantCode: string}, tamara: array{publicKey: string, country: string, lang: string}}
     */
    protected function bnplConfig(): array
    {
        return [
            'currency' => (string) config('payment.gateways.tabby.currency', config('payment.currency')),
            'tabby' => [
                'publicKey' => (string) config('payment.gateways.tabby.public_key', ''),
                'merchantCode' => (string) config('payment.gateways.tabby.merchant_code', ''),
            ],
            'tamara' => [
                'publicKey' => (string) config('payment.gateways.tamara.public_key', ''),
                'country' => (string) config('payment.gateways.tamara.country', 'AE'),
                'lang' => app()->getLocale(),
            ],
        ];
    }

    /**
     * The store identity, contact details and social links the footer renders.
     *
     * These mirror the canonical keys the admin Settings screen writes; each
     * value is a string (empty when unset) so the footer can fall back to its
     * own defaults for anything the owner has not filled in yet.
     *
     * @return array<string, string>
     */
    protected function storeSettings(): array
    {
        $keys = [
            'store.name', 'store.email', 'store.phone', 'store.address', 'store.city', 'store.country',
            'social.facebook', 'social.instagram', 'social.youtube', 'social.linkedin',
        ];

        try {
            $map = Setting::many($keys);
        } catch (Throwable) {
            $map = [];
        }

        return array_map(fn ($value) => (string) ($value ?? ''), array_merge(array_fill_keys($keys, ''), $map));
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
                ->get(['name', 'name_ar', 'slug', 'image_path'])
                ->map(fn (Category $category) => [
                    'slug' => $category->slug,
                    'name' => $category->localizedName(),
                    // The admin-uploaded photo, if any; the home tiles fall back to
                    // the bundled house image, then the teal gradient, when null.
                    'image' => $category->imageUrl(),
                ])
                ->values()
                ->all()
        );
    }
}
