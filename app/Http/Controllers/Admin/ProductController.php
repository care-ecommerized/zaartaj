<?php

namespace App\Http\Controllers\Admin;

use App\Catalog\MediaLimits;
use App\Catalog\ProductWriteService;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Requests\Admin\UploadProductImageRequest;
use App\Http\Requests\Admin\UploadProductVideoRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductWriteService $writer = new ProductWriteService) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:draft,active,archived'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $products = Product::query()
            ->with(['category:id,name', 'featuredImage'])
            ->withCount('variants')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $q) => $q->where('title', 'like', "%{$search}%")
                        ->orWhere('handle', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                )
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['category'] ?? null,
                fn (Builder $query, int $category) => $query->where('category_id', $category)
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'handle' => $product->handle,
                'title' => $product->title,
                'brand' => $product->brand,
                'status' => $product->status,
                'category' => $product->category?->name,
                'variants_count' => $product->variants_count,
                'min_price' => $product->min_price,
                'max_price' => $product->max_price,
                'total_inventory' => $product->total_inventory,
                'image' => $product->featuredImage?->url(),
            ]);

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => $filters,
            'statuses' => array_column(ProductStatus::cases(), 'value'),
            // Only branches that actually hold products are worth offering as a filter.
            'categories' => Category::query()
                ->whereHas('products')
                ->orderBy('path')
                ->get(['id', 'name', 'path']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', $this->formOptions());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        // Media is attached after the insert — the files are stored under the
        // product's id, which only exists once the row has been written.
        $product = $this->writer->create($request->safe()->except(['images', 'video']));

        $images = $this->writer->attachUploadedImages($product, (array) $request->file('images', []));

        if ($video = $request->file('video')) {
            $this->writer->attachUploadedVideo($product, $video);
        }

        $attached = count($images) + ($video ? 1 : 0);

        // Land on edit, where the rest of the media can be managed.
        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', $attached > 0
                ? "Product created with {$attached} media file(s)."
                : 'Product created. Add images and a video below.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['options', 'variants', 'images']);

        return Inertia::render('admin/products/edit', [
            ...$this->formOptions(),
            'product' => [
                'id' => $product->id,
                'handle' => $product->handle,
                'title' => $product->title,
                'title_ar' => $product->title_ar,
                'body_html' => $product->body_html,
                'body_html_ar' => $product->body_html_ar,
                'vendor' => $product->vendor,
                'brand' => $product->brand,
                'product_type' => $product->product_type,
                'category_id' => $product->category_id,
                'status' => $product->status->value,
                'seo_title' => $product->seo_title,
                'seo_title_ar' => $product->seo_title_ar,
                'seo_description' => $product->seo_description,
                'seo_description_ar' => $product->seo_description_ar,
                'option_name' => $product->options->first()?->name ?? '',
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    // The placeholder single-variant value reads as blank in the editor.
                    'option_value' => $variant->option1 === 'Default Title' ? '' : (string) $variant->option1,
                    'price' => (float) $variant->price,
                    'compare_at_price' => $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null,
                    'sku' => $variant->sku,
                    'weight' => $variant->grams,
                    'stock' => $variant->inventory_quantity,
                ])->values()->all(),
            ],
            'images' => $product->images->map(fn (ProductImage $image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'position' => $image->position,
                'alt' => $image->alt,
            ])->values()->all(),
            'video' => $product->hasVideo()
                ? ['url' => $product->videoUrl(), 'mime' => $product->video_mime]
                : null,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->writer->update($product, $request->validated());

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete: order line items keep resolving the (trashed) product row.
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    /**
     * PATCH a single variant's stock. The variant is scoped to the product.
     */
    public function updateInventory(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'inventory_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $this->writer->updateInventory($variant, (int) $data['inventory_quantity']);

        return back()->with('status', 'Inventory updated.');
    }

    public function uploadImage(UploadProductImageRequest $request, Product $product): RedirectResponse
    {
        $images = $this->writer->attachUploadedImages($product, $request->images());

        return back()->with('status', count($images) === 1
            ? 'Image uploaded.'
            : count($images).' images uploaded.');
    }

    public function uploadVideo(UploadProductVideoRequest $request, Product $product): RedirectResponse
    {
        // Replaces whatever clip the product had; a product carries one video.
        $this->writer->attachUploadedVideo($product, $request->file('video'));

        return back()->with('status', 'Video uploaded.');
    }

    public function deleteVideo(Product $product): RedirectResponse
    {
        $this->writer->deleteVideo($product);

        return back()->with('status', 'Video removed.');
    }

    public function deleteImage(Product $product, ProductImage $image): RedirectResponse
    {
        $this->writer->deleteImage($image);

        return back()->with('status', 'Image removed.');
    }

    public function reorderImages(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->writer->reorderImages($product, $data['ids']);

        return back()->with('status', 'Images reordered.');
    }

    public function show(Product $product): Response
    {
        $product->load(['category', 'options', 'variants', 'images', 'metafields', 'tags']);

        return Inertia::render('admin/products/show', [
            'product' => $product,
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url(),
                'position' => $image->position,
                'alt' => $image->alt,
                'mirrored' => $image->mirrored_at !== null,
                'mirror_error' => $image->mirror_error,
            ]),
            'video' => $product->hasVideo()
                ? ['url' => $product->videoUrl(), 'mime' => $product->video_mime]
                : null,
        ]);
    }

    /**
     * Shared form data for the create and edit screens.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            // The shop's curated categories, synced from config/catalog.php.
            'categories' => Category::query()
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => array_column(ProductStatus::cases(), 'value'),
            'baseCurrency' => config('payment.currency'),
            // So the upload hints quote the limits the validator actually enforces.
            'mediaLimits' => MediaLimits::forProps(),
        ];
    }
}
