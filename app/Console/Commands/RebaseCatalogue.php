<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebaseCatalogue extends Command
{
    protected $signature = 'catalogue:rebase {--dry-run : Compute and print the change without writing anything}
                            {--rate=33 : Base units of the OLD currency per 1 unit of the new base (1 AED = 33 BDT)}
                            {--force : Run again even though the guard says it already happened}';

    protected $description = 'One-time re-base of catalogue prices from BDT into the new AED base currency';

    /**
     * The settings key that records a completed live run, so a second run cannot
     * silently divide the (already re-based) prices a second time.
     */
    private const GUARD_KEY = 'catalogue_rebased_at';

    /**
     * Divide every catalogue price by the FX rate to move it into the AED base.
     *
     * This is destructive and one-directional, so it is guarded three ways: a
     * --dry-run that only reports, a settings-row guard that blocks a re-run, and
     * a wrapping transaction so a mid-run failure leaves the catalogue untouched.
     */
    public function handle(): int
    {
        $rate = (float) $this->option('rate');

        if ($rate <= 0) {
            $this->error('The --rate must be a positive number.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && $this->alreadyRebased() && ! $this->option('force')) {
            $this->error('The catalogue has already been re-based (see the settings guard). Pass --force to override.');

            return self::FAILURE;
        }

        $productCount = Product::withTrashed()->count();

        if ($dryRun) {
            $this->previewSample($rate);
            $this->info("Dry run: {$productCount} products would be re-based at 1 : {$rate}. Nothing was written.");

            return self::SUCCESS;
        }

        $variantsTouched = DB::transaction(function () use ($rate) {
            $touched = 0;

            Product::withTrashed()->chunkById(100, function ($products) use ($rate, &$touched) {
                foreach ($products as $product) {
                    // Rebase the product's own denormalised range first; for a
                    // product with variants syncVariantAggregates() will overwrite
                    // it, but a variant-less product still gets moved correctly.
                    $product->forceFill([
                        'min_price' => $this->divide($product->min_price, $rate),
                        'max_price' => $this->divide($product->max_price, $rate),
                    ])->save();

                    foreach ($product->variants()->get() as $variant) {
                        $variant->forceFill([
                            'price' => $this->divide($variant->price, $rate) ?? 0,
                            'compare_at_price' => $this->divide($variant->compare_at_price, $rate),
                            'cost_per_item' => $this->divide($variant->cost_per_item, $rate),
                        ])->save();

                        $touched++;
                    }

                    $product->syncVariantAggregates();
                }
            });

            return $touched;
        });

        $this->markRebased();

        $this->info("Re-based {$productCount} products and {$variantsTouched} variants at 1 : {$rate}. Guard set.");

        return self::SUCCESS;
    }

    /**
     * Print a before/after sample of the first five products so a human can sanity
     * -check the rate before committing to the live run.
     */
    private function previewSample(float $rate): void
    {
        $rows = Product::withTrashed()
            ->orderBy('id')
            ->take(5)
            ->get()
            ->map(fn (Product $product) => [
                $product->handle,
                $this->money($product->min_price),
                $this->money($this->divide($product->min_price, $rate)),
                $this->money($product->max_price),
                $this->money($this->divide($product->max_price, $rate)),
            ])
            ->all();

        $this->table(
            ['Handle', 'min (before)', 'min (after)', 'max (before)', 'max (after)'],
            $rows,
        );
    }

    /**
     * Divide a price into the new base, rounding HALF_UP to 2dp. Nulls pass through
     * (an absent compare-at/cost stays absent).
     */
    private function divide(int|float|string|null $value, float $rate): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value / $rate, 2, PHP_ROUND_HALF_UP);
    }

    private function money(int|float|string|null $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 2);
    }

    private function alreadyRebased(): bool
    {
        return DB::table('settings')->where('key', self::GUARD_KEY)->exists();
    }

    private function markRebased(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::GUARD_KEY],
            ['value' => now()->toIso8601String(), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
