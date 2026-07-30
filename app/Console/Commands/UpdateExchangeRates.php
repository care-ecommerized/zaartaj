<?php

namespace App\Console\Commands;

use App\Currency\ExchangeRateUpdater;
use Illuminate\Console\Command;

/**
 * Refresh presentment FX rates from the live provider.
 */
class UpdateExchangeRates extends Command
{
    protected $signature = 'fx:update';

    protected $description = 'Refresh currency exchange rates from the live FX provider';

    public function handle(ExchangeRateUpdater $updater): int
    {
        $updated = $updater->update();

        $this->info("Refreshed {$updated} currency rate(s).");

        return self::SUCCESS;
    }
}
