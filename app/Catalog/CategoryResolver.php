<?php

namespace App\Catalog;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Maps an imported product onto one of the shop's own categories.
 *
 * Supplier exports describe products with Shopify's taxonomy, which is far too
 * granular for a storefront this size — one import produced 82 nodes. The shop
 * navigates by a handful of curated categories instead, defined in config/catalog.php.
 */
class CategoryResolver
{
    /**
     * Categories keyed by slug, loaded once per request.
     *
     * @var array<string, Category>|null
     */
    private ?array $categories = null;

    /**
     * Pick the category for a product, from whatever text the export gives us.
     *
     * The taxonomy path is the most reliable signal, then the supplier's own type
     * and tags, and the title last — a title is the noisiest of the four, but for
     * exports that leave the taxonomy blank it is all there is.
     */
    public function resolve(?string $taxonomyPath, ?string $type = null, ?string $tags = null, ?string $title = null): ?Category
    {
        $haystack = Str::lower(implode(' ', array_filter([$taxonomyPath, $type, $tags, $title])));

        foreach ((array) config('catalog.category_rules', []) as $slug => $keywords) {
            foreach ((array) $keywords as $keyword) {
                if (str_contains($haystack, Str::lower($keyword))) {
                    return $this->find($slug);
                }
            }
        }

        $fallback = config('catalog.fallback_category');

        return $fallback ? $this->find($fallback) : null;
    }

    /**
     * Create the configured categories, and return them keyed by slug.
     *
     * Safe to call repeatedly: existing rows are updated in place rather than
     * duplicated, so re-ordering the config re-orders the navigation.
     *
     * @return array<string, Category>
     */
    public function sync(): array
    {
        $synced = [];

        foreach (array_values((array) config('catalog.categories', [])) as $position => $definition) {
            $category = Category::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'path' => $definition['name'],
                    'path_hash' => sha1($definition['name']),
                    'depth' => 0,
                    'position' => $position,
                    'is_visible' => true,
                ]
            );

            $synced[$category->slug] = $category;
        }

        $this->categories = $synced;

        return $synced;
    }

    private function find(string $slug): ?Category
    {
        if ($this->categories === null) {
            $this->categories = Category::whereNull('parent_id')->get()->keyBy('slug')->all();
        }

        // A rule can name a category that has not been created yet.
        return $this->categories[$slug] ?? null;
    }
}
