<?php

namespace Tests\Feature;

use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    public function test_it_defaults_to_the_base_currency(): void
    {
        $this->get('/welcome')->assertInertia(
            fn (Assert $page) => $page->where('currency.code', 'AED')
        );
    }

    public function test_a_query_param_selects_an_active_currency(): void
    {
        $this->get('/welcome?currency=SAR')->assertInertia(
            fn (Assert $page) => $page->where('currency.code', 'SAR')
        );
    }

    public function test_a_cookie_selects_an_active_currency(): void
    {
        // Bypass EncryptCookies so the raw test cookie reaches the middleware; in
        // production the cookie is encrypted on write and decrypted on read.
        $this->withoutMiddleware(EncryptCookies::class)
            ->withUnencryptedCookie('currency', 'QAR')
            ->get('/welcome')
            ->assertInertia(fn (Assert $page) => $page->where('currency.code', 'QAR'));
    }

    public function test_an_unknown_currency_falls_back_to_the_base(): void
    {
        $this->get('/welcome?currency=ZZZ')->assertInertia(
            fn (Assert $page) => $page->where('currency.code', 'AED')
        );
    }

    public function test_an_inactive_currency_is_rejected(): void
    {
        Currency::whereKey('BDT')->update(['is_active' => false]);

        $this->get('/welcome?currency=BDT')->assertInertia(
            fn (Assert $page) => $page->where('currency.code', 'AED')
        );
    }

    public function test_the_active_currencies_are_shared(): void
    {
        $this->get('/welcome')->assertInertia(
            fn (Assert $page) => $page
                ->has('currencies', 8)
                ->where('currencies.0.code', 'AED')
        );
    }

    public function test_posting_a_currency_sets_the_cookie(): void
    {
        $this->post('/currency', ['currency' => 'SAR'])
            ->assertRedirect()
            ->assertCookie('currency', 'SAR');
    }

    public function test_posting_an_inactive_currency_is_rejected(): void
    {
        Currency::whereKey('BDT')->update(['is_active' => false]);

        $this->post('/currency', ['currency' => 'BDT'])
            ->assertSessionHasErrors('currency');
    }

    public function test_posting_an_unknown_currency_is_rejected(): void
    {
        $this->post('/currency', ['currency' => 'ZZZ'])
            ->assertSessionHasErrors('currency');
    }
}
