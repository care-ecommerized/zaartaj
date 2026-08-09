<?php

namespace Tests\Feature\Admin;

use App\Catalog\CategoryResolver;
use App\Catalog\MediaLimits;
use App\Catalog\ProductPresenter;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /**
     * A minimal valid create payload with one variant.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Emerald Silk Gown',
            'status' => 'active',
            'variants' => [
                ['option_value' => '', 'price' => '499.00', 'stock' => 7],
            ],
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_product_with_one_variant_and_sets_aggregates(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload())
            ->assertRedirect();

        $product = Product::firstOrFail();

        // Handle generated from the title.
        $this->assertSame('emerald-silk-gown', $product->handle);
        $this->assertSame(ProductStatus::Active, $product->status);
        // Active publishes now.
        $this->assertNotNull($product->published_at);

        $this->assertCount(1, $product->variants);
        $this->assertSame('499.00', $product->min_price);
        $this->assertSame('499.00', $product->max_price);
        $this->assertSame(7, $product->total_inventory);
    }

    #[Test]
    public function a_draft_product_is_not_published(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['status' => 'draft']))
            ->assertRedirect();

        $product = Product::firstOrFail();

        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertNull($product->published_at);
    }

    #[Test]
    public function it_creates_a_product_with_multiple_variants_and_distinct_keys(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload([
                'option_name' => 'Size',
                'variants' => [
                    ['option_value' => 'Small', 'price' => '450.00', 'stock' => 3],
                    ['option_value' => 'Large', 'price' => '520.00', 'stock' => 4],
                ],
            ]))
            ->assertRedirect();

        $product = Product::with('variants', 'options')->firstOrFail();

        $this->assertCount(2, $product->variants);
        $this->assertSame('Size', $product->options->first()->name);

        // Distinct option keys, one per row.
        $this->assertSame(2, $product->variants->pluck('option_key')->unique()->count());

        // Aggregates span the cheapest and dearest variant, and sum the stock.
        $this->assertSame('450.00', $product->min_price);
        $this->assertSame('520.00', $product->max_price);
        $this->assertSame(7, $product->total_inventory);
    }

    #[Test]
    public function updating_variant_price_and_stock_recomputes_aggregates(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload([
                'title' => 'Emerald Silk Gown',
                'handle' => $product->handle,
                'variants' => [
                    ['option_value' => '', 'price' => '650.00', 'stock' => 12],
                ],
            ]))
            ->assertRedirect();

        $product->refresh();
        $this->assertSame('650.00', $product->min_price);
        $this->assertSame('650.00', $product->max_price);
        $this->assertSame(12, $product->total_inventory);
        $this->assertSame('650.00', $product->variants->first()->price);
    }

    #[Test]
    public function arabic_fields_persist_and_the_presenter_localises(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload([
            'title_ar' => 'فستان حريري زمردي',
            'body_html_ar' => '<p>وصف عربي</p>',
        ]));

        $product = Product::with('category', 'images', 'variants', 'metafields')->firstOrFail();
        $this->assertSame('فستان حريري زمردي', $product->title_ar);

        // English by default.
        app()->setLocale('en');
        $this->assertSame('Emerald Silk Gown', $product->localizedTitle());
        $this->assertSame('Emerald Silk Gown', (new ProductPresenter)->card($product)['name']);

        // Arabic when the locale is ar and the sibling is filled.
        app()->setLocale('ar');
        $this->assertSame('فستان حريري زمردي', $product->localizedTitle());
        $this->assertSame('فستان حريري زمردي', (new ProductPresenter)->card($product)['name']);
    }

    #[Test]
    public function an_untranslated_product_falls_back_to_english_in_arabic(): void
    {
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        app()->setLocale('ar');
        $this->assertSame('Emerald Silk Gown', $product->localizedTitle());
    }

    #[Test]
    public function an_uploaded_image_lands_on_the_public_disk_and_renders(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.images.store', $product), [
                'image' => UploadedFile::fake()->image('gown.jpg', 800, 1000),
            ])
            ->assertRedirect();

        $image = $product->images()->firstOrFail();

        $this->assertSame('public', $image->disk);
        $this->assertNotNull($image->mirrored_at);
        Storage::disk('public')->assertExists($image->path);

        // url() serves the local mirror (host-stripped), not the remote src.
        $this->assertStringContainsString($image->path, $image->url());

        // Deleting removes both the row and the file.
        $this->actingAs($admin)
            ->delete(route('admin.products.images.destroy', [$product, $image]))
            ->assertRedirect();

        $this->assertSame(0, $product->images()->count());
        Storage::disk('public')->assertMissing($image->path);
    }

    #[Test]
    public function the_inventory_endpoint_updates_qty_and_total(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::with('variants')->firstOrFail();
        $variant = $product->variants->first();

        $this->actingAs($admin)
            ->patch(route('admin.products.inventory', [$product, $variant]), ['inventory_quantity' => 30])
            ->assertRedirect();

        $this->assertSame(30, $variant->refresh()->inventory_quantity);
        $this->assertSame(30, $product->refresh()->total_inventory);
    }

    #[Test]
    public function destroy_soft_deletes_the_product(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        // Soft-deleted: the row survives so historic orders still resolve it.
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    #[Test]
    public function a_duplicate_handle_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->payload(['handle' => 'emerald-silk-gown']))
            ->assertSessionHasErrors('handle');

        $this->assertSame(1, Product::count());
    }

    #[Test]
    public function a_missing_title_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function at_least_one_variant_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload(['variants' => []]))
            ->assertSessionHasErrors('variants');

        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function every_new_route_is_invisible_to_a_non_admin(): void
    {
        (new CategoryResolver)->sync();
        $customer = User::factory()->create(['is_admin' => false]);

        // Seed a product to hang the member routes off.
        $this->actingAs($this->admin())->post(route('admin.products.store'), $this->payload());
        $product = Product::with('variants')->firstOrFail();
        $variant = $product->variants->first();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'src' => 'x',
            'src_hash' => ProductImage::hashSrc('x'),
            'position' => 1,
        ]);

        $this->actingAs($customer)->get(route('admin.products.create'))->assertNotFound();
        $this->actingAs($customer)->post(route('admin.products.store'), $this->payload())->assertNotFound();
        $this->actingAs($customer)->get(route('admin.products.edit', $product))->assertNotFound();
        $this->actingAs($customer)->put(route('admin.products.update', $product), $this->payload())->assertNotFound();
        $this->actingAs($customer)->delete(route('admin.products.destroy', $product))->assertNotFound();
        $this->actingAs($customer)->patch(route('admin.products.inventory', [$product, $variant]), ['inventory_quantity' => 1])->assertNotFound();
        $this->actingAs($customer)->post(route('admin.products.images.store', $product))->assertNotFound();
        $this->actingAs($customer)->delete(route('admin.products.images.destroy', [$product, $image]))->assertNotFound();
        $this->actingAs($customer)->post(route('admin.products.images.reorder', $product), ['ids' => [$image->id]])->assertNotFound();
        $this->actingAs($customer)->post(route('admin.products.video.store', $product))->assertNotFound();
        $this->actingAs($customer)->delete(route('admin.products.video.destroy', $product))->assertNotFound();

        // Nothing changed.
        $this->assertSame(1, Product::count());
    }

    #[Test]
    public function it_categorises_and_reorders_images(): void
    {
        Storage::fake('public');
        (new CategoryResolver)->sync();
        $admin = $this->admin();

        $gowns = Category::where('slug', 'gowns')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload(['category_id' => $gowns->id]));
        $product = Product::firstOrFail();
        $this->assertSame($gowns->id, $product->category_id);

        $this->actingAs($admin)->post(route('admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $this->actingAs($admin)->post(route('admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('b.jpg'),
        ]);

        $ids = $product->images()->orderBy('position')->pluck('id')->all();

        $this->actingAs($admin)
            ->post(route('admin.products.images.reorder', $product), ['ids' => array_reverse($ids)])
            ->assertRedirect();

        $reordered = $product->images()->orderBy('position')->pluck('id')->all();
        $this->assertSame(array_reverse($ids), $reordered);
    }

    #[Test]
    public function a_new_product_can_be_created_with_images_and_a_video(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->payload([
                'images' => [
                    UploadedFile::fake()->image('front.jpg', 800, 1000),
                    UploadedFile::fake()->image('back.png', 800, 1000),
                ],
                'video' => UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4'),
            ]))
            ->assertRedirect();

        $product = Product::with('images')->firstOrFail();

        // Both images stored, numbered in the order they were picked.
        $this->assertSame(2, $product->images->count());
        $this->assertSame([1, 2], $product->images->pluck('position')->all());

        foreach ($product->images as $image) {
            $this->assertSame('public', $image->disk);
            Storage::disk('public')->assertExists($image->path);
        }

        // The video lands on the video disk and renders host-stripped.
        $this->assertTrue($product->hasVideo());
        $this->assertSame('video/mp4', $product->video_mime);
        Storage::disk('public')->assertExists($product->video_path);
        $this->assertStringContainsString($product->video_path, (string) $product->videoUrl());
    }

    #[Test]
    public function several_images_upload_in_one_request_and_append_after_the_existing_ones(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('first.jpg')],
        ]));
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.images.store', $product), [
                'images' => [
                    UploadedFile::fake()->image('second.jpg'),
                    UploadedFile::fake()->image('third.jpg'),
                ],
            ])
            ->assertRedirect();

        $this->assertSame([1, 2, 3], $product->images()->orderBy('position')->pluck('position')->all());
    }

    #[Test]
    public function uploading_a_video_replaces_the_previous_file_and_deleting_clears_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.video.store', $product), [
                'video' => UploadedFile::fake()->create('first.mp4', 1024, 'video/mp4'),
            ])
            ->assertRedirect();

        $first = $product->refresh()->video_path;
        $this->assertNotNull($first);

        // Uploading again swaps the file rather than leaving an orphan behind.
        $this->actingAs($admin)->post(route('admin.products.video.store', $product), [
            'video' => UploadedFile::fake()->create('second.webm', 1024, 'video/webm'),
        ]);

        $second = $product->refresh()->video_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)
            ->delete(route('admin.products.video.destroy', $product))
            ->assertRedirect();

        $this->assertFalse($product->refresh()->hasVideo());
        $this->assertNull($product->videoUrl());
        Storage::disk('public')->assertMissing($second);
    }

    #[Test]
    public function the_create_form_is_told_limits_php_will_actually_honour(): void
    {
        // Far beyond any php.ini: the advertised ceiling has to come down to it,
        // otherwise PHP discards the body and the admin sees a session error.
        config(['catalog.video_max_kb' => 10_000_000]);

        $this->assertLessThanOrEqual(10_000_000, MediaLimits::videoMaxKb());
        $this->assertGreaterThan(0, MediaLimits::videoMaxKb());

        $this->actingAs($this->admin())
            ->get(route('admin.products.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/create')
                ->where('mediaLimits.videoMaxMb', (int) max(1, round(MediaLimits::videoMaxKb() / 1024)))
                ->has('mediaLimits.imageMaxMb')
                ->has('mediaLimits.imageBatchMax')
                ->has('mediaLimits.requestMaxMb')
                ->etc()
            );
    }

    #[Test]
    public function a_non_video_file_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.video.store', $product), [
                'video' => UploadedFile::fake()->create('invoice.pdf', 64, 'application/pdf'),
            ])
            ->assertSessionHasErrors('video');

        $this->assertFalse($product->refresh()->hasVideo());
    }
}
