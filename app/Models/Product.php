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
use Illuminate\Support\Facades\Storage;

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
        'title_ar',
        'body_html',
        'body_html_ar',
        'vendor',
        'brand',
        'product_type',
        'category_id',
        'shopify_category',
        'status',
        'published_at',
        'seo_title',
        'seo_title_ar',
        'seo_description',
        'seo_description_ar',
        'min_price',
        'max_price',
        'total_inventory',
        'video_disk',
        'video_path',
        'video_mime',
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

    public function hasVideo(): bool
    {
        return filled($this->video_disk) && filled($this->video_path);
    }

    /**
     * The URL of the uploaded product video, or null when there is none.
     *
     * Host-stripped for a local-driver disk for the same reason ProductImage::url()
     * strips it: Storage::url() builds the host from APP_URL, which would 404 the
     * clip whenever the site is reached on any other hostname.
     */
    public function videoUrl(): ?string
    {
        if (! $this->hasVideo()) {
            return null;
        }

        $url = Storage::disk($this->video_disk)->url($this->video_path);

        if (config("filesystems.disks.{$this->video_disk}.driver") !== 'local') {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path === false || $path === null ? $url : $path;
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
     * The title in the active locale, falling back to the English column.
     *
     * The `_ar` sibling is only preferred for an Arabic request and only when it
     * has actually been filled in, so a product that has not been translated yet
     * still reads correctly in either language.
     */
    public function localizedTitle(): string
    {
        return $this->localized('title', 'title_ar');
    }

    /**
     * The description (HTML) in the active locale, English otherwise.
     */
    public function localizedBody(): ?string
    {
        return $this->localized('body_html', 'body_html_ar');
    }

    /**
     * The SEO title in the active locale, English otherwise.
     */
    public function localizedSeoTitle(): ?string
    {
        return $this->localized('seo_title', 'seo_title_ar');
    }

    /**
     * The SEO description in the active locale, English otherwise.
     */
    public function localizedSeoDescription(): ?string
    {
        return $this->localized('seo_description', 'seo_description_ar');
    }

    /**
     * Return the Arabic sibling for an Arabic request when it is non-empty,
     * otherwise the base column.
     */
    private function localized(string $base, string $arabic): ?string
    {
        if (app()->getLocale() === 'ar' && filled($this->{$arabic})) {
            return $this->{$arabic};
        }

        return $this->{$base};
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
