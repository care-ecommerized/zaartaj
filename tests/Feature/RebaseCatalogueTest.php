<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebaseCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dry_run_reports_but_writes_nothing_and_leaves_the_guard_unset(): void
    {
        $product = $this->makeProduct('cinderella-gown', price: 33000, stock: 5);

        $this->artisan('catalogue:rebase', ['--dry-run' => true])->assertExitCode(0);

        // Prices are untouched: a dry run computes and prints only.
        $this->assertEquals(33000, $product->variants->first()->fresh()->price);
        $this->assertEquals(33000, $product->fresh()->min_price);

        // And the idempotency guard must not have been set.
        $this->assertDatabaseMissing('settings', ['key' => 'catalogue_rebased_at']);
    }

    private function makeProduct(string $handle, int $price, int $stock): Product
    {
        $product = Product::create([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);

        $product->variants()->create([
            'option_key' => ProductVariant::makeOptionKey(null, 'Default Title', null, null),
            'option1' => 'Default Title',
            'price' => $price,
            'inventory_quantity' => $stock,
        ]);

        return $product->syncVariantAggregates()->load('variants');
    }
}
