<?php

namespace App\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Shapes a Product for the storefront.
 *
 * The React pages were written against a hand-written catalogue, and this keeps
 * that prop contract while the data now comes from the database.
 */
class ProductPresenter
{
    /**
     * The compact form used by grids and the "you may also like" rail.
     *
     * @return array<string, mixed>
     */
    public function card(Product $product): array
    {
        return [
            'slug' => $product->handle,
            'name' => $product->localizedTitle(),
            'brand' => $product->brand,
            'category' => $product->category?->slug,
            'categoryName' => $product->category?->localizedName(),
            'price' => (float) $product->min_price,
            'compareAtPrice' => $this->compareAtPrice($product),
            'material' => $this->material($product),
            'image' => $product->relationLoaded('images')
                ? $product->images->first(fn (ProductImage $image) => $image->isRenderable())?->url()
                : $product->featuredImage?->url(),
            'inStock' => $product->total_inventory > 0,
        ];
    }

    /**
     * Everything the product page needs.
     *
     * @return array<string, mixed>
     */
    public function detail(Product $product): array
    {
        $variants = $product->variants;

        return array_merge($this->card($product), [
            // Sanitised at import, so it is safe to render as markup.
            'description' => $product->localizedBody(),
            'blurb' => $this->blurb($product),
            'images' => $product->images
                ->filter(fn (ProductImage $image) => $image->isRenderable())
                ->map(fn (ProductImage $image) => ['url' => $image->url(), 'alt' => $image->alt ?? $product->localizedTitle()])
                ->values()
                ->all(),
            'options' => $this->options($product),
            'variants' => $variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'title' => $this->variantTitle($variant),
                'price' => (float) $variant->price,
                'compareAtPrice' => $variant->compare_at_price ? (float) $variant->compare_at_price : null,
                'available' => $variant->isPurchasable(),
                'inventory' => $variant->inventory_quantity,
            ])->values()->all(),
            'details' => $this->details($product),
            'totalInventory' => $product->total_inventory,
        ]);
    }

    /**
     * Selectable option values, with Shopify's placeholder removed.
     *
     * Products without real options still carry a "Title / Default Title" option;
     * showing it as a picker would be noise on every single product page.
     *
     * @return list<array{name: string, values: list<string>}>
     */
    private function options(Product $product): array
    {
        return $product->options
            ->map(function ($option) use ($product) {
                $column = 'option'.$option->position;

                $values = $product->variants
                    ->pluck($column)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return ['name' => $option->name, 'values' => $values];
            })
            ->reject(fn (array $option) => $option['values'] === []
                || (count($option['values']) === 1 && $option['values'][0] === 'Default Title'))
            ->values()
            ->all();
    }

    /**
     * Specification rows, built from the Shopify taxonomy attributes.
     *
     * @return list<array{label: string, value: string}>
     */
    private function details(Product $product): array
    {
        return $product->metafields
            ->map(fn ($metafield) => [
                // "connectivity-technology" reads better as "Connectivity technology".
                'label' => Str::ucfirst(str_replace('-', ' ', $metafield->key)),
                'value' => collect(explode(';', $metafield->value))
                    ->map(fn (string $term) => trim($term))
                    ->filter()
                    ->implode(', '),
            ])
            ->reject(fn (array $row) => $row['value'] === '')
            ->values()
            ->all();
    }

    private function variantTitle(ProductVariant $variant): string
    {
        $parts = array_filter([$variant->option1, $variant->option2, $variant->option3]);

        $title = implode(' / ', $parts);

        return $title === '' || $title === 'Default Title' ? 'Default' : $title;
    }

    /**
     * The strike-through price, taken from the cheapest variant that has one.
     */
    private function compareAtPrice(Product $product): ?float
    {
        if (! $product->relationLoaded('variants')) {
            return null;
        }

        $compare = $product->variants
            ->pluck('compare_at_price')
            ->filter()
            ->min();

        return $compare ? (float) $compare : null;
    }

    /**
     * A short line for cards, meta descriptions and the cart.
     */
    private function blurb(Product $product): string
    {
        if (filled($product->localizedSeoDescription())) {
            return $product->localizedSeoDescription();
        }

        return Str::limit(trim(html_entity_decode(strip_tags((string) $product->localizedBody()))), 160);
    }

    /**
     * The sub-heading under a product name.
     *
     * Falls back through the attributes most likely to be filled in, so a card
     * never shows a blank line.
     */
    private function material(Product $product): ?string
    {
        if ($product->relationLoaded('metafields')) {
            $material = $product->metafields->firstWhere('key', 'material')
                ?? $product->metafields->firstWhere('key', 'fabric');

            if ($material) {
                return Str::ucfirst(str_replace(';', ',', $material->value));
            }
        }

        return $product->product_type ?: $product->brand;
    }
}
