<?php

namespace Tests\Feature;

use App\Currency\CurrencyException;
use App\Currency\CurrencyService;
use App\Models\Currency;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    private function service(): CurrencyService
    {
        return new CurrencyService;
    }

    public function test_converting_the_base_into_itself_is_a_no_op(): void
    {
        $this->assertSame(100.0, $this->service()->convert(100, 'AED'));
    }

    public function test_converting_the_base_out_multiplies_by_the_rate(): void
    {
        // 1 AED = 33 BDT, so a 100 AED total is 3,300 BDT.
        $this->assertSame(3300.0, $this->service()->convert(100, 'BDT'));
    }

    public function test_a_three_decimal_currency_keeps_three_places(): void
    {
        $this->assertSame(3, $this->service()->decimalsFor('KWD'));
        // 1000 AED * 0.083 = 83.000 KWD, rounded to the currency's 3 places.
        $this->assertSame(83.0, $this->service()->convert(1000, 'KWD'));
    }

    public function test_rounding_is_half_up(): void
    {
        // A clean, binary-exact case: 0.125 at 2dp rounds up to 0.13, never 0.12.
        Currency::updateOrCreate(['code' => 'TST'], [
            'name' => 'Test', 'symbol' => 'T', 'decimals' => 2,
            'rate_to_base' => 0.125, 'is_active' => true, 'is_base' => false,
        ]);

        $this->assertSame(0.13, $this->service()->convert(1, 'TST'));
    }

    public function test_format_respects_the_currency_precision(): void
    {
        $this->assertSame('AED 1,234.50', $this->service()->format(1234.5, 'AED'));
        $this->assertSame('KWD 1.500', $this->service()->format(1.5, 'KWD'));
    }

    public function test_a_missing_currency_throws(): void
    {
        $this->expectException(CurrencyException::class);

        $this->service()->convert(100, 'ZZZ');
    }

    public function test_an_inactive_currency_throws(): void
    {
        Currency::whereKey('BDT')->update(['is_active' => false]);

        $this->expectException(CurrencyException::class);

        $this->service()->convert(100, 'BDT');
    }
}
