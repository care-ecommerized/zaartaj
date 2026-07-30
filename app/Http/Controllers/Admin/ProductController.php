<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
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
        ]);
    }
}
