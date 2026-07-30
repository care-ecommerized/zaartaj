<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Seed the currency table with AED as the base and the storefront's launch
     * markets.
     *
     * Rates are `rate_to_base` — units of the currency per 1 AED. The GCC pegs
     * (SAR, QAR) are near-constant; KWD/OMR/BHD/USD are approximate placeholders
     * to be replaced by the live FX job later. Idempotent via updateOrCreate, so
     * re-running never duplicates or clobbers a manually-overridden rate's flags.
     */
    public function run(): void
    {
        $currencies = [
            // code   name              symbol  decimals  rate_to_base  base
            ['AED', 'UAE Dirham', 'AED', 2, 1, true],
            ['SAR', 'Saudi Riyal', 'SAR', 2, 1.02, false],
            ['QAR', 'Qatari Riyal', 'QAR', 2, 1.00, false],
            ['KWD', 'Kuwaiti Dinar', 'KWD', 3, 0.083, false],
            ['OMR', 'Omani Rial', 'OMR', 3, 0.105, false],
            ['BHD', 'Bahraini Dinar', 'BHD', 3, 0.103, false],
            ['USD', 'US Dollar', '$', 2, 0.272, false],
            ['BDT', 'Bangladeshi Taka', '৳', 2, 33, false],
        ];

        foreach ($currencies as [$code, $name, $symbol, $decimals, $rate, $isBase]) {
            Currency::updateOrCreate(['code' => $code], [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'rate_to_base' => $rate,
                'is_active' => true,
                'is_base' => $isBase,
                'rate_updated_at' => now(),
            ]);
        }
    }
}
