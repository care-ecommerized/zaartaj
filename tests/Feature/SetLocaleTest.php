<?php

namespace Tests\Feature;

use App\Catalog\CategoryResolver;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_the_configured_locale(): void
    {
        $this->get('/welcome')->assertInertia(
            fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('direction', 'ltr')
        );
    }

    public function test_a_query_param_selects_arabic_and_flips_direction(): void
    {
        $this->get('/welcome?lang=ar')->assertInertia(
            fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('direction', 'rtl')
        );
    }

    public function test_a_cookie_selects_the_locale(): void
    {
        // Bypass EncryptCookies so the raw test cookie reaches the middleware; in
        // production the cookie is encrypted on write and decrypted on read.
        $this->withoutMiddleware(EncryptCookies::class)
            ->withUnencryptedCookie('locale', 'ar')
            ->get('/welcome')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar')->where('direction', 'rtl'));
    }

    public function test_an_arabic_accept_language_header_is_honoured(): void
    {
        $this->get('/welcome', ['Accept-Language' => 'ar,en;q=0.8'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
    }

    public function test_an_english_accept_language_header_stays_english(): void
    {
        $this->get('/welcome', ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_an_unknown_locale_falls_back_to_the_default(): void
    {
        $this->get('/welcome?lang=fr')->assertInertia(
            fn (Assert $page) => $page->where('locale', 'en')->where('direction', 'ltr')
        );
    }

    public function test_the_active_locale_translations_are_shared(): void
    {
        // English is ASCII, so assert its exact value; then assert the Arabic
        // catalogue ships a different, non-empty string under the same key.
        $english = null;

        // The keys contain literal dots, so read them with Collection::get
        // (exact key) rather than data_get (which would treat them as paths).
        $this->get('/welcome')->assertInertia(
            fn (Assert $page) => $page->where(
                'translations',
                function ($translations) use (&$english) {
                    $english = collect($translations)->get('cart.subtotal');

                    return $english === 'Subtotal';
                }
            )
        );

        $this->get('/welcome?lang=ar')->assertInertia(
            fn (Assert $page) => $page->where(
                'translations',
                fn ($translations) => is_string($arabic = collect($translations)->get('cart.subtotal'))
                    && $arabic !== ''
                    && $arabic !== $english
            )
        );
    }

    public function test_posting_a_locale_sets_the_cookie(): void
    {
        $this->post('/locale', ['locale' => 'ar'])
            ->assertRedirect()
            ->assertCookie('locale', 'ar');
    }

    public function test_posting_an_unknown_locale_is_rejected(): void
    {
        $this->post('/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_the_navigation_cache_is_keyed_per_locale(): void
    {
        (new CategoryResolver)->sync();

        Cache::forget('shop.nav.categories.en');
        Cache::forget('shop.nav.categories.ar');

        // An English request must only warm the English nav cache...
        $this->get('/shop?lang=en')->assertOk();

        $this->assertTrue(Cache::has('shop.nav.categories.en'));
        $this->assertFalse(Cache::has('shop.nav.categories.ar'));

        // ...and an Arabic request populates its own key rather than reusing it.
        $this->get('/shop?lang=ar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));

        $this->assertTrue(Cache::has('shop.nav.categories.ar'));
    }

    public function test_the_storefront_renders_under_arabic(): void
    {
        (new CategoryResolver)->sync();

        $this->get('/?lang=ar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('direction', 'rtl')->where('locale', 'ar'));
    }
}
