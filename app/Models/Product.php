<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'handle',
        'title',
        'body_html',
        'vendor',
        'brand',
        'product_type',
        'category_id',
        'shopify_category',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'min_price',
        'max_price',
        'total_inventory',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * The image shown on listing cards.
     *
     * Skips images the origin has already 404'd on, so a card falls back to the
     * placeholder rather than showing a broken image.
     */
    public function featuredImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->renderable()->orderBy('position');
    }

    public function metafields(): HasMany
    {
        return $this->hasMany(ProductMetafield::class);
    }

    public function metafieldValues(): HasMany
    {
        return $this->hasMany(ProductMetafieldValue::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Products that should be visible to a shopper.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Filter by an exploded taxonomy attribute, e.g. ('color', 'black').
     */
    public function scopeWhereMetafield(Builder $query, string $key, string $value): Builder
    {
        return $query->whereHas(
            'metafieldValues',
            fn (Builder $q) => $q->where('key', $key)->where('value', $value)
        );
    }

    /**
     * Recalculate the denormalised price range and stock total from the variants.
     *
     * Call after any variant write. Returns the model so it can be chained.
     */
    public function syncVariantAggregates(): self
    {
        /*
         * Queried through the query builder rather than the relation: the relation
         * carries an `order by position`, and MySQL rejects an aggregate select that
         * orders by an ungrouped column. SQLite accepts it, so this only shows up
         * against the real database.
         */
        $aggregate = DB::table('product_variants')
            ->where('product_id', $this->id)
            ->selectRaw('MIN(price) AS min_price, MAX(price) AS max_price, SUM(inventory_quantity) AS total_inventory')
            ->first();

        $this->forceFill([
            'min_price' => $aggregate?->min_price,
            'max_price' => $aggregate?->max_price,
            // Oversold variants shouldn't drag a product's total below zero.
            'total_inventory' => max(0, (int) ($aggregate?->total_inventory ?? 0)),
        ])->save();

        return $this;
    }
}
