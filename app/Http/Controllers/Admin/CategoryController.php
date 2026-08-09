<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff management of the shop's own categories. Kept flat (top-level only) for
 * now — the parent/child columns stay so a category can be split into
 * sub-categories later without a migration.
 *
 * Every write forgets the per-locale storefront navigation cache so the header
 * and footer pick the change up on the next request.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->withCount('products')
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'name_ar' => $category->name_ar,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'position' => $category->position,
                    'is_visible' => $category->is_visible,
                    'image_url' => $category->imageUrl(),
                    'products_count' => $category->products_count,
                ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

        Category::create([
            'parent_id' => null,
            'name' => $data['name'],
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'image_path' => $request->file('image') ? $this->storeImage($request->file('image')) : null,
            'is_visible' => $data['is_visible'],
            'path' => $data['name'],
            'path_hash' => sha1($slug),
            'depth' => 0,
            'position' => (int) Category::query()->max('position') + 1,
        ]);

        $this->forgetNavCache();

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name'], $category->id);

        $category->update([
            'name' => $data['name'],
            'name_ar' => $data['name_ar'] ?? null,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'image_path' => $this->resolveImage($request, $category),
            'is_visible' => $data['is_visible'],
            'path' => $data['name'],
            'path_hash' => sha1($slug),
        ]);

        $this->forgetNavCache();

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // products.category_id is nullOnDelete, so filed products are simply
        // un-categorised rather than deleted.
        $this->deleteImage($category->image_path);
        $category->delete();

        $this->forgetNavCache();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach (array_values($validated['ids']) as $position => $id) {
                Category::whereKey($id)->update(['position' => $position]);
            }
        });

        $this->forgetNavCache();

        return redirect()->back();
    }

    /**
     * A unique slug derived from the given source, appending -2, -3… until it is
     * free. The optional id is the row being updated, ignored so a category keeps
     * its own slug.
     */
    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * The image path to persist on update: a freshly uploaded file replaces the
     * old one (deleting it), an explicit `remove_image` clears it, and otherwise
     * the existing path is kept untouched.
     */
    private function resolveImage(UpdateCategoryRequest $request, Category $category): ?string
    {
        if ($request->file('image')) {
            $this->deleteImage($category->image_path);

            return $this->storeImage($request->file('image'));
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($category->image_path);

            return null;
        }

        return $category->image_path;
    }

    /**
     * Store an uploaded category photo on the public image disk and return its
     * path. These files are ours (not a supplier CDN), so no mirroring applies.
     */
    private function storeImage(UploadedFile $file): string
    {
        return Storage::disk(config('catalog.image_disk'))->putFile('categories', $file);
    }

    /**
     * Delete a stored category photo, tolerating an already-missing file.
     */
    private function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(config('catalog.image_disk'))->delete($path);
        }
    }

    /**
     * Forget the per-locale storefront navigation cache the Inertia middleware
     * builds under these keys (see HandleInertiaRequests::navigationCategories).
     */
    private function forgetNavCache(): void
    {
        Cache::forget('shop.nav.categories.en');
        Cache::forget('shop.nav.categories.ar');
    }
}
