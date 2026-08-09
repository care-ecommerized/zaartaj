<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Staff management of the storefront's settings: store identity, contact,
 * social links and the EN/AR policy bodies. Everything is persisted to the
 * key/value `settings` table under the canonical keys declared here — the same
 * keys the storefront reads, so this list is the single source of truth.
 */
class SettingsController extends Controller
{
    /**
     * The canonical setting keys and their defaults. The storefront reads these
     * exact keys, so keep them in lockstep with any consumer.
     *
     * @var array<string, string>
     */
    private const DEFAULTS = [
        // Store identity & contact.
        'store.name' => 'Zaartaj Elegance',
        'store.email' => 'support@zaartaj.com',
        'store.phone' => '',
        'store.address' => '',
        'store.city' => '',
        'store.country' => 'AE',

        // Social links.
        'social.facebook' => '',
        'social.instagram' => '',
        'social.youtube' => '',
        'social.linkedin' => '',

        // Store policies (EN/AR bodies).
        'policy.privacy.en' => '',
        'policy.privacy.ar' => '',
        'policy.terms.en' => '',
        'policy.terms.ar' => '',
        'policy.shipping.en' => '',
        'policy.shipping.ar' => '',
        'policy.returns.en' => '',
        'policy.returns.ar' => '',

        // Where new-order notifications are sent.
        'notifications.order_email' => '',
    ];

    public function edit(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => $this->currentSettings(),
            'baseCurrency' => config('payment.currency'),
            'currencyCount' => $this->activeCurrencyCount(),
            'zoneCount' => $this->activeZoneCount(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        // Validation has already run (typed FormRequest); read each canonical key
        // by its flat dotted name so the payload maps straight onto the store.
        $pairs = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $pairs[$key] = $request->input($key);
        }

        Setting::setMany($pairs);

        return redirect()
            ->route('admin.settings.index')
            ->with('status', 'Settings saved.');
    }

    /**
     * Every canonical key mapped to its stored value or default.
     *
     * @return array<string, string>
     */
    private function currentSettings(): array
    {
        $stored = Setting::map();

        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = (string) ($stored[$key] ?? $default);
        }

        return $settings;
    }

    /**
     * Active currency count for the read-only Operations panel. Guarded so an
     * unseeded table never breaks the page.
     */
    private function activeCurrencyCount(): int
    {
        try {
            return Currency::query()->active()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Active shipping-zone count for the read-only Operations panel. Guarded so
     * an unseeded table never breaks the page.
     */
    private function activeZoneCount(): int
    {
        try {
            return ShippingZone::query()->active()->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
