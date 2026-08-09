<?php

namespace App\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The single funnel for manual product writes from the admin.
 *
 * Everything an admin can change about a product — its fields, its Arabic
 * siblings, its category, its option names, its variants and its stock — flows
 * through here in a transaction, and every write ends by re-syncing the
 * denormalised price range and stock total so the storefront never reads a stale
 * aggregate. This complements the CSV importer (App\Catalog\Import\ProductImporter),
 * which owns the bulk path; neither touches the other.
 */
class ProductWriteService
{
    /**
     * Create a product and its variants from validated admin input.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = new Product;

            $this->fillProduct($product, $data);
            $product->handle = $this->uniqueHandle($data['handle'] ?? null, (string) $data['title']);
            $product->save();

            $this->syncOptions($product, $data);
            $this->syncVariants($product, $data['variants'] ?? []);

            $product->syncVariantAggregates();

            return $product->refresh();
        });
    }

    /**
     * Update a product and reconcile its variants against the submitted rows.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $this->fillProduct($product, $data);

            // An empty handle field leaves the existing slug alone rather than
            // regenerating one from the (possibly edited) title on every save.
            if (filled($data['handle'] ?? null)) {
                $product->handle = $this->uniqueHandle($data['handle'], (string) $data['title'], $product->id);
            }

            $product->save();

            $this->syncOptions($product, $data);
            $this->syncVariants($product, $data['variants'] ?? []);

            $product->syncVariantAggregates();

            return $product->refresh();
        });
    }

    /**
     * Set a single variant's stock and re-sync the product total.
     */
    public function updateInventory(ProductVariant $variant, int $quantity): ProductVariant
    {
        $variant->update(['inventory_quantity' => max(0, $quantity)]);

        $variant->product->syncVariantAggregates();

        return $variant;
    }

    /**
     * Change a product's status, applying the published_at rules.
     */
    public function adjustStatus(Product $product, ProductStatus $status): Product
    {
        $this->applyStatus($product, $status);
        $product->save();

        return $product;
    }

    /**
     * Store an uploaded image on the public image disk and record it.
     *
     * The record is written as already-mirrored (disk/path set, mirrored_at now)
     * so ProductImage::url() serves it straight from local storage and the remote
     * mirror job never looks at it — these files did not come from a supplier CDN.
     */
    public function attachUploadedImage(Product $product, UploadedFile $file, ?int $position = null): ProductImage
    {
        $disk = config('catalog.image_disk');

        $path = Storage::disk($disk)->putFile("products/{$product->id}", $file);

        $position ??= (int) $product->images()->max('position') + 1;

        return $product->images()->create([
            // A local, unique path — so it doubles as both the src and the digest key.
            'src' => Storage::disk($disk)->url($path),
            'src_hash' => ProductImage::hashSrc($path),
            'position' => $position,
            'alt' => $product->title,
            'disk' => $disk,
            'path' => $path,
            'mirrored_at' => now(),
        ]);
    }

    /**
     * Store a batch of uploaded images, appending them after the existing ones.
     *
     * Positions are assigned once up front rather than re-read per file, so a
     * batch keeps the order the admin picked it in.
     *
     * @param  array<int, UploadedFile|null>  $files
     * @return list<ProductImage>
     */
    public function attachUploadedImages(Product $product, array $files): array
    {
        $position = (int) $product->images()->max('position');

        $created = [];

        foreach (array_filter($files) as $file) {
            $created[] = $this->attachUploadedImage($product, $file, ++$position);
        }

        return $created;
    }

    /**
     * Store an uploaded video on the video disk, replacing any existing one.
     *
     * A product carries at most one clip, so the previous file is deleted rather
     * than left orphaned on the disk.
     */
    public function attachUploadedVideo(Product $product, UploadedFile $file): Product
    {
        $this->deleteVideo($product);

        $disk = config('catalog.video_disk');

        $path = Storage::disk($disk)->putFile("products/{$product->id}/video", $file);

        $product->forceFill([
            'video_disk' => $disk,
            'video_path' => $path,
            // Read off the upload: the browser's <video> needs the source type.
            'video_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
        ])->save();

        return $product;
    }

    /**
     * Remove a product's video, deleting the stored file. A no-op without one.
     */
    public function deleteVideo(Product $product): Product
    {
        if (! $product->hasVideo()) {
            return $product;
        }

        if (Storage::disk($product->video_disk)->exists($product->video_path)) {
            Storage::disk($product->video_disk)->delete($product->video_path);
        }

        $product->forceFill([
            'video_disk' => null,
            'video_path' => null,
            'video_mime' => null,
        ])->save();

        return $product;
    }

    /**
     * Delete an image, removing the stored file for an uploaded one.
     */
    public function deleteImage(ProductImage $image): void
    {
        // Only uploads carry a local file; imported images point at a remote CDN.
        if ($image->disk && $image->path && Storage::disk($image->disk)->exists($image->path)) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $image->delete();
    }

    /**
     * Re-number a product's images from an ordered list of ids.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderImages(Product $product, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $id) {
            $product->images()->whereKey($id)->update(['position' => $index + 1]);
        }
    }

    /**
     * Fill the scalar product columns, including the Arabic siblings.
     *
     * @param  array<string, mixed>  $data
     */
    private function fillProduct(Product $product, array $data): void
    {
        $product->fill([
            'title' => $data['title'],
            'title_ar' => $data['title_ar'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'body_html_ar' => $data['body_html_ar'] ?? null,
            'vendor' => $data['vendor'] ?? null,
            'brand' => $data['brand'] ?? null,
            'product_type' => $data['product_type'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_title_ar' => $data['seo_title_ar'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'seo_description_ar' => $data['seo_description_ar'] ?? null,
        ]);

        $this->applyStatus($product, ProductStatus::from($data['status']));
    }

    /**
     * Apply a status and keep published_at consistent with it.
     *
     * Active publishes now if it was never published; Draft unpublishes; Archived
     * keeps whatever publish date it had so it can be restored to Active later.
     */
    private function applyStatus(Product $product, ProductStatus $status): void
    {
        $product->status = $status;

        if ($status === ProductStatus::Active) {
            $product->published_at ??= now();
        } elseif ($status === ProductStatus::Draft) {
            $product->published_at = null;
        }
    }

    /**
     * Replace the product's option names. The manual editor exposes one option
     * (e.g. "Size"); a blank name means a single-variant product with no picker.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncOptions(Product $product, array $data): void
    {
        $product->options()->delete();

        $name = trim((string) ($data['option_name'] ?? ''));

        if ($name !== '') {
            $product->options()->create(['name' => $name, 'position' => 1]);
        }
    }

    /**
     * Reconcile variants against the submitted rows, keyed on option_key.
     *
     * Matches the importer's identity model: a row is recognised by its SKU or,
     * lacking one, its option value. Rows no longer present are removed.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncVariants(Product $product, array $rows): void
    {
        $seen = [];
        $position = 0;

        foreach ($rows as $row) {
            $position++;

            // A single-variant product carries Shopify's "Default Title" placeholder,
            // which the presenter hides — so it never renders as a stray picker.
            $optionValue = trim((string) ($row['option_value'] ?? '')) ?: 'Default Title';
            $sku = filled($row['sku'] ?? null) ? trim((string) $row['sku']) : null;

            $optionKey = ProductVariant::makeOptionKey($sku, $optionValue, null, null);

            // A repeated key (two blank rows, say) collapses onto one; the later wins.
            $seen[$optionKey] = true;

            $variant = $product->variants()->firstOrNew(['option_key' => $optionKey]);

            $variant->fill([
                'sku' => $sku,
                'option1' => $optionValue,
                'price' => $this->decimal($row['price'] ?? null) ?? 0,
                'compare_at_price' => $this->decimal($row['compare_at_price'] ?? null),
                'grams' => (int) round((float) ($row['weight'] ?? 0)),
                'weight_unit' => 'g',
                'inventory_quantity' => (int) ($row['stock'] ?? 0),
                'inventory_policy' => $row['inventory_policy'] ?? 'deny',
                'position' => $position,
            ]);

            $variant->save();
        }

        $product->variants()
            ->whereNotIn('option_key', array_keys($seen) ?: [''])
            ->delete();
    }

    /**
     * A URL-safe, de-duplicated handle. Falls back to the title when none given.
     */
    private function uniqueHandle(?string $handle, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($handle ?: $title) ?: 'product';

        $candidate = $base;
        $suffix = 2;

        while (
            Product::withTrashed()
                ->where('handle', $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Parse a decimal money field, tolerating stray separators; null when blank.
     */
    private function decimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }
}
