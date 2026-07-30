<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'option1',
        'option2',
        'option3',
        'option_key',
        'price',
        'compare_at_price',
        'cost_per_item',
        'grams',
        'weight_unit',
        'requires_shipping',
        'taxable',
        'tax_code',
        'inventory_tracker',
        'inventory_policy',
        'fulfillment_service',
        'inventory_quantity',
        'position',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_per_item' => 'decimal:2',
            'grams' => 'integer',
            'requires_shipping' => 'boolean',
            'taxable' => 'boolean',
            'inventory_quantity' => 'integer',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Stable identity for a variant within its product.
     *
     * Prefers the SKU. The Shopify export we import has none, so the option triple
     * is the fallback — without it a re-import would insert duplicates every run.
     */
    public static function makeOptionKey(?string $sku, ?string $option1, ?string $option2, ?string $option3): string
    {
        if (filled($sku)) {
            return substr('sku:'.sha1(trim($sku)), 0, 64);
        }

        $options = implode('|', array_map(
            fn (?string $value) => trim((string) $value),
            [$option1, $option2, $option3]
        ));

        return substr('opt:'.sha1($options), 0, 64);
    }

    /**
     * Whether the variant can be added to a cart right now.
     */
    public function isPurchasable(): bool
    {
        return $this->inventory_quantity > 0 || $this->inventory_policy === 'continue';
    }
}
