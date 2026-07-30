<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductMetafield extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'namespace',
        'key',
        'value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductMetafieldValue::class);
    }

    /**
     * Split a raw metafield value into its individual terms.
     *
     * Shopify packs multi-selects into one cell as "bluetooth; wireless".
     *
     * @return list<string>
     */
    public static function splitValue(string $value): array
    {
        return collect(explode(';', $value))
            ->map(fn (string $term) => trim($term))
            ->filter()
            // A cell can repeat a term; the values table is unique per metafield.
            ->unique()
            ->values()
            ->all();
    }
}
