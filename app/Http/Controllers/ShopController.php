<?php

namespace App\Http\Controllers;

use App\Catalog\ProductPresenter;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __construct(private readonly ProductPresenter $presenter) {}

    public function home(): Response
    {
        // The hero showcases one gown. Prefer the white wedding gown; fall back to
        // any in-stock gown, then any product, so the hero never renders empty.
        $hero = $this->published()
            ->where('handle', 'gorgeous-white-wedding-gown')
            ->first()
            ?? $this->published()
                ->whereHas('category', fn (Builder $query) => $query->where('slug', 'gowns'))
                ->orderByDesc('total_inventory')
                ->first();

        // The home rails only ever show products that carry a real photo — a
        // product with no renderable image is skipped so no blank tile appears.
        $withPhoto = fn (Builder $query): Builder => $query->whereHas('images', fn (Builder $q) => $q->renderable());

        $inStock = fn (Builder $query): Builder => $withPhoto($query)->where('total_inventory', '>', 0);

        // Home-page curation, chosen by the shop owner.
        // - $hiddenHandles are kept off the home page entirely.
        // - $newArrivalHandles are the exact pieces shown in "New Arrivals", in
        //   this order (rather than auto-filling by newest).
        $hiddenHandles = ['large-green', 'large-maroon', 'dra-small-brown'];
        $newArrivalHandles = ['black-clutch-evening-bag', 'modern-cloud-clutch-bag', 'wrinkled-heart-evening-clutch', 'retro-heart-chain-party-handbag'];

        $newArrivals = $this->published()
            ->whereIn('handle', $newArrivalHandles)
            ->get()
            ->sortBy(fn ($product) => array_search($product->handle, $newArrivalHandles))
            ->values();

        // Best-selling: in-stock, excluding the hidden pieces and whatever is
        // already pinned to New Arrivals, so the two rails never repeat.
        $bestSelling = $inStock($this->published())
            ->whereNotIn('handle', array_merge($hiddenHandles, $newArrivalHandles))
            ->orderByDesc('total_inventory')
            ->limit(10)
            ->get();

        // A 4-piece showcase for a top-level category (incl. its descendants), so
        // the home page tells a first-time visitor what the house sells.
        $showcase = function (string $slug) use ($withPhoto, $hiddenHandles): array {
            $category = Category::where('slug', $slug)->first();

            if (! $category) {
                return [];
            }

            return $this->cards(
                $withPhoto($this->published())
                    ->whereIn('category_id', $this->descendantIds($category))
                    ->whereNotIn('handle', $hiddenHandles)
                    ->latest('id')
                    ->limit(4)
                    ->get()
            );
        };

        return Inertia::render('shop/home', [
            'hero' => $hero ? $this->presenter->card($hero) : null,
            'newArrivals' => $this->cards($newArrivals),
            'bestSelling' => $this->cards($bestSelling),
            'showcaseGowns' => $showcase('gowns'),
            'showcaseJewellery' => $showcase('jewellery'),
            'showcaseBags' => $showcase('bags'),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'in:featured,price-asc,price-desc,newest'],
        ]);

        $category = isset($filters['category'])
            ? Category::where('slug', $filters['category'])->first()
            : null;

        $products = $this->published()
            ->when($category, function (Builder $query, Category $category) {
                // A shopper picking "Electronics" expects everything beneath it, not
                // just products pinned to that exact node.
                $query->whereIn('category_id', $this->descendantIds($category));
            })
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    fn (Builder $q) => $q->where('title', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                )
            )
            ->when(
                ($filters['sort'] ?? 'featured') === 'price-asc',
                fn (Builder $query) => $query->orderBy('min_price')
            )
            ->when(
                ($filters['sort'] ?? null) === 'price-desc',
                fn (Builder $query) => $query->orderByDesc('min_price')
            )
            ->when(
                ($filters['sort'] ?? null) === 'newest',
                fn (Builder $query) => $query->latest('id')
            )
            ->when(
                ($filters['sort'] ?? 'featured') === 'featured',
                // In-stock first, so the grid does not open with sold-out pieces.
                fn (Builder $query) => $query->orderByDesc('total_inventory')->orderBy('title')
            )
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('shop/index', [
            'products' => $products->through(fn (Product $product) => $this->presenter->card($product)),
            'filters' => $filters,
            'activeCategory' => $category ? [
                'slug' => $category->slug,
                'name' => $category->name,
                'path' => $category->path,
            ] : null,
        ]);
    }

    public function show(string $handle): Response
    {
        $product = $this->published()
            ->with(['options', 'metafields', 'variants', 'images'])
            ->where('handle', $handle)
            ->firstOrFail();

        return Inertia::render('shop/product', [
            'product' => $this->presenter->detail($product),
            'related' => $this->cards(
                $this->published()
                    ->where('id', '!=', $product->id)
                    ->when(
                        $product->category_id,
                        fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId)
                    )
                    ->limit(3)
                    ->get()
            ),
        ]);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function cards($products): array
    {
        return $products->map(fn (Product $product) => $this->presenter->card($product))->values()->all();
    }

    private function published(): Builder
    {
        return Product::query()
            ->published()
            ->with(['category:id,name,slug', 'featuredImage', 'variants', 'metafields']);
    }

    /**
     * A category and everything below it.
     *
     * The tree is only a few levels deep, so walking it in PHP is cheaper and far
     * more readable than a recursive CTE.
     *
     * @return list<int>
     */
    private function descendantIds(Category $category): array
    {
        $ids = [$category->id];
        $frontier = [$category->id];

        while ($frontier !== []) {
            $children = Category::whereIn('parent_id', $frontier)->pluck('id')->all();

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }
}
