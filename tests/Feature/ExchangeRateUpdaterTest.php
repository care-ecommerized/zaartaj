<?php

namespace Tests\Feature;

use App\Currency\ExchangeRateUpdater;
use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateUpdaterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    private function fakeRates(array $rates): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => 'success',
                'base_code' => 'AED',
                'rates' => $rates,
            ]),
        ]);
    }

    public function test_it_updates_non_override_active_currencies(): void
    {
        $this->fakeRates(['AED' => 1, 'SAR' => 1.07, 'BDT' => 30.5]);

        $count = (new ExchangeRateUpdater)->update();

        $this->assertSame('1.07000000', Currency::findOrFail('SAR')->rate_to_base);
        $this->assertSame('30.50000000', Currency::findOrFail('BDT')->rate_to_base);
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_it_skips_the_base_currency(): void
    {
        $this->fakeRates(['AED' => 2, 'SAR' => 1.07]);

        (new ExchangeRateUpdater)->update();

        $this->assertSame('1.00000000', Currency::findOrFail('AED')->rate_to_base);
    }

    public function test_it_skips_manual_override_currencies(): void
    {
        Currency::whereKey('SAR')->update(['manual_override' => true, 'rate_to_base' => 9.99]);

        $this->fakeRates(['AED' => 1, 'SAR' => 1.07]);

        (new ExchangeRateUpdater)->update();

        $this->assertSame('9.99000000', Currency::findOrFail('SAR')->rate_to_base);
    }

    public function test_it_stamps_rate_updated_at(): void
    {
        Currency::whereKey('SAR')->update(['rate_updated_at' => now()->subYear()]);

        $this->fakeRates(['AED' => 1, 'SAR' => 1.07]);

        (new ExchangeRateUpdater)->update();

        $this->assertTrue(Currency::findOrFail('SAR')->rate_updated_at->isToday());
    }

    public function test_an_api_failure_leaves_rates_untouched(): void
    {
        $before = Currency::findOrFail('SAR')->rate_to_base;

        Http::fake(['*' => Http::response(null, 500)]);

        $count = (new ExchangeRateUpdater)->update();

        $this->assertSame(0, $count);
        $this->assertSame($before, Currency::findOrFail('SAR')->rate_to_base);
    }

    public function test_the_fx_update_command_runs(): void
    {
        $this->fakeRates(['AED' => 1, 'SAR' => 1.07]);

        $this->artisan('fx:update')->assertSuccessful();
    }
}
