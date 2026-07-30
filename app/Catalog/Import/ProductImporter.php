<?php

namespace App\Catalog\Import;

use App\Catalog\CategoryResolver;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMetafield;
use App\Models\ProductVariant;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImporter
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer = new HtmlSanitizer,
        private readonly CategoryResolver $categories = new CategoryResolver,
    ) {}

    /**
     * Create or update one product and everything hanging off it.
     *
     * Keyed on the handle, so re-running the same export updates in place rather
     * than duplicating. The whole group is written in a transaction: a product with
     * half its variants is worse than a product that failed outright and got logged.
     *
     * @param  array<string, array{namespace: string, key: string}>  $metafieldColumns
     */
    public function import(ProductRowGroup $group, array $metafieldColumns = []): ImportedProduct
    {
        return DB::transaction(function () use ($group, $metafieldColumns) {
            $row = $group->primaryRow();

            $product = Product::withTrashed()->firstOrNew(['handle' => $group->handle]);
            $wasCreated = ! $product->exists;

            if ($product->trashed()) {
                $product->restore();
            }

            // Mapped onto the shop's own categories, not Shopify's taxonomy tree.
            $category = $this->categories->resolve(
                $row['Product Category'] ?? null,
                $row['Type'] ?? null,
                $row['Tags'] ?? null,
                $row['Title'] ?? null,
            );

            $product->fill([
                'title' => $row['Title'] ?? $group->handle,
                'body_html' => $this->sanitizer->clean($row['Body (HTML)'] ?? null),
                'vendor' => $row['Vendor'] ?? null,
                'product_type' => $row['Type'] ?? null,
                'category_id' => $category?->id,
                'shopify_category' => $row['Product Category'] ?? null,
                'status' => $this->status($row['Status'] ?? null),
                'published_at' => $this->publishedAt($row, $product),
                'seo_title' => $row['SEO Title'] ?? null,
                'seo_description' => $row['SEO Description'] ?? null,
            ]);

            $product->save();

            $this->syncOptions($product, $row);
            $this->syncVariants($product, $group);
            $imageIds = $this->syncImages($product, $group);
            $this->syncMetafields($product, $row, $metafieldColumns);
            $this->syncTags($product, $row['Tags'] ?? null);

            $product->syncVariantAggregates();

            return new ImportedProduct($product, $wasCreated, $imageIds);
        });
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function syncOptions(Product $product, array $row): void
    {
        $product->options()->delete();

        foreach ([1, 2, 3] as $position) {
            $name = $row["Option{$position} Name"] ?? null;

            if (blank($name)) {
                continue;
            }

            /*
             * Products without real options still get Shopify's "Title / Default
             * Title" placeholder. It is kept so the variant shape round-trips, and
             * the storefront hides a single-value option rather than the importer
             * having to guess which ones are meaningful.
             */
            $product->options()->create([
                'name' => trim($name),
                'position' => $position,
            ]);
        }
    }

    private function syncVariants(Product $product, ProductRowGroup $group): void
    {
        $seen = [];
        $position = 0;

        foreach ($group->variantRows() as $row) {
            $position++;

            $optionKey = ProductVariant::makeOptionKey(
                $row['Variant SKU'] ?? null,
                $row['Option1 Value'] ?? null,
                $row['Option2 Value'] ?? null,
                $row['Option3 Value'] ?? null,
            );

            // Two rows can collapse onto the same key when the export repeats a
            // variant; the later row wins rather than causing a unique violation.
            $seen[$optionKey] = true;

            $variant = $product->variants()->firstOrNew(['option_key' => $optionKey]);

            $variant->fill([
                'sku' => $row['Variant SKU'] ?? null,
                'barcode' => $row['Variant Barcode'] ?? null,
                'option1' => $row['Option1 Value'] ?? null,
                'option2' => $row['Option2 Value'] ?? null,
                'option3' => $row['Option3 Value'] ?? null,
                'price' => $this->decimal($row['Variant Price'] ?? null) ?? 0,
                'compare_at_price' => $this->decimal($row['Variant Compare At Price'] ?? null),
                'cost_per_item' => $this->decimal($row['Cost per item'] ?? null),
                'grams' => (int) round((float) ($row['Variant Grams'] ?? 0)),
                'weight_unit' => $row['Variant Weight Unit'] ?? 'kg',
                'requires_shipping' => $this->boolean($row['Variant Requires Shipping'] ?? null, true),
                'taxable' => $this->boolean($row['Variant Taxable'] ?? null, true),
                'tax_code' => $row['Variant Tax Code'] ?? null,
                'inventory_tracker' => $row['Variant Inventory Tracker'] ?? null,
                'inventory_policy' => $row['Variant Inventory Policy'] ?? 'deny',
                'fulfillment_service' => $row['Variant Fulfillment Service'] ?? 'manual',
                'position' => $position,
            ]);

            /*
             * Stock is only taken from the file when the export actually carries the
             * column — some do, some don't. When it is absent the count stays ours, so
             * a re-import can never silently wipe quantities an admin has set.
             */
            $quantity = $row['Variant Inventory Qty'] ?? null;

            if (filled($quantity) && is_numeric(trim($quantity))) {
                $variant->inventory_quantity = (int) trim($quantity);
            } elseif (! $variant->exists) {
                $variant->inventory_quantity = 0;
            }

            $variant->save();
        }

        /*
         * Variants dropped from the export are removed. Once order line items exist
         * this needs to become a soft delete, or historic orders lose what was bought.
         */
        $product->variants()
            ->whereNotIn('option_key', array_keys($seen))
            ->delete();
    }

    /**
     * @return list<int> IDs of images that still need mirroring
     */
    private function syncImages(Product $product, ProductRowGroup $group): array
    {
        $hashes = [];
        $awaitingMirror = [];

        foreach ($group->images() as $image) {
            $hash = ProductImage::hashSrc($image['src']);
            $hashes[] = $hash;

            $record = $product->images()->firstOrNew(['src_hash' => $hash]);

            $record->fill([
                'src' => $image['src'],
                'position' => $image['position'],
                'alt' => $image['alt'],
            ]);

            $record->save();

            if ($record->mirrored_at === null) {
                $awaitingMirror[] = $record->id;
            }
        }

        $product->images()
            ->whereNotIn('src_hash', $hashes ?: [''])
            ->delete();

        return $awaitingMirror;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, array{namespace: string, key: string}>  $metafieldColumns
     */
    private function syncMetafields(Product $product, array $row, array $metafieldColumns): void
    {
        // Cascades to product_metafield_values.
        $product->metafields()->delete();

        foreach ($metafieldColumns as $column => $definition) {
            $value = $row[$column] ?? null;

            if (blank($value)) {
                continue;
            }

            $metafield = $product->metafields()->create([
                'namespace' => $definition['namespace'],
                'key' => $definition['key'],
                'value' => $value,
            ]);

            foreach (ProductMetafield::splitValue($value) as $term) {
                $metafield->values()->create([
                    'product_id' => $product->id,
                    'key' => $definition['key'],
                    // The values table is indexed, so terms are clipped to fit.
                    'value' => Str::limit($term, 191, ''),
                ]);
            }
        }
    }

    private function syncTags(Product $product, ?string $tags): void
    {
        if (blank($tags)) {
            $product->tags()->sync([]);

            return;
        }

        $ids = collect(explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(function (string $name) {
                return Tag::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                )->id;
            })
            ->all();

        $product->tags()->sync($ids);
    }

    private function status(?string $status): ProductStatus
    {
        return ProductStatus::tryFrom(strtolower(trim((string) $status))) ?? ProductStatus::Draft;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function publishedAt(array $row, Product $product): ?string
    {
        $published = $this->boolean($row['Published'] ?? null, false);

        if (! $published) {
            return null;
        }

        // Keep the original publish date across re-imports.
        return ($product->published_at ?? now())->toDateTimeString();
    }

    private function boolean(?string $value, bool $default): bool
    {
        if (blank($value)) {
            return $default;
        }

        return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function decimal(?string $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        // Tolerate thousands separators and stray currency symbols.
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));

        return is_numeric($clean) ? (float) $clean : null;
    }
}
