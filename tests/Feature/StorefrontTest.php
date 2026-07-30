<?php

namespace Tests\Feature;

use App\Catalog\CategoryResolver;
use App\Catalog\ProductPresenter;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_published_products(): void
    {
        $this->makeProduct('visible-piece');
        $this->makeProduct('draft-piece', ['status' => ProductStatus::Draft, 'published_at' => null]);

        $this->get('/shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/index')
                ->where('products.total', 1)
                ->where('products.data.0.slug', 'visible-piece'));
    }

    #[Test]
    public function a_category_page_shows_only_that_categorys_products(): void
    {
        $categories = (new CategoryResolver)->sync();

        $this->makeProduct('cinderella-gown', ['category_id' => $categories['gowns']->id]);
        $this->makeProduct('beaded-clutch', ['category_id' => $categories['bags']->id]);

        $this->get('/shop?category=gowns')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.slug', 'cinderella-gown'));
    }

    #[Test]
    public function every_configured_category_stays_in_the_navigation_even_when_empty(): void
    {
        $categories = (new CategoryResolver)->sync();

        $this->makeProduct('cinderella-gown', ['category_id' => $categories['gowns']->id]);

        /*
         * Jewellery and Shoes have nothing in them yet. They describe what the shop
         * sells, so they stay in the nav rather than appearing only once a product
         * happens to land in them.
         */
        $this->get('/shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('shopCategories', fn ($shared) => collect($shared)->pluck('slug')->all()
                    === ['gowns', 'modest-clothes', 'jewellery', 'bags', 'shoes']));
    }

    #[Test]
    public function an_empty_category_page_loads_rather_than_404ing(): void
    {
        (new CategoryResolver)->sync();

        $this->get('/shop?category=shoes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.total', 0)
                ->where('activeCategory.name', 'Shoes'));
    }

    #[Test]
    public function an_image_the_origin_has_deleted_is_never_rendered(): void
    {
        $product = $this->makeProduct('geekera-charging-station');

        // 122 images in the real import 404'd: the supplier removed the files.
        $product->images()->create([
            'src' => 'https://cdn.shopify.com/gone.jpg',
            'src_hash' => ProductImage::hashSrc('https://cdn.shopify.com/gone.jpg'),
            'position' => 1,
            'mirror_error' => 'Origin returned HTTP 404.',
        ]);

        $product->images()->create([
            'src' => 'https://cdn.shopify.com/pending.jpg',
            'src_hash' => ProductImage::hashSrc('https://cdn.shopify.com/pending.jpg'),
            'position' => 2,
        ]);

        $presented = (new ProductPresenter)->detail($product->fresh()->load(['images', 'variants', 'options', 'metafields']));

        $this->assertCount(1, $presented['images']);
        $this->assertSame('https://cdn.shopify.com/pending.jpg', $presented['images'][0]['url']);

        // The card falls back past the dead image rather than showing it.
        $this->assertSame('https://cdn.shopify.com/pending.jpg', $presented['image']);
    }

    #[Test]
    public function a_product_whose_images_are_all_dead_reports_no_image_at_all(): void
    {
        $product = $this->makeProduct('noorio-camera');

        $product->images()->create([
            'src' => 'https://cdn.shopify.com/gone.jpg',
            'src_hash' => ProductImage::hashSrc('https://cdn.shopify.com/gone.jpg'),
            'position' => 1,
            'mirror_error' => 'Origin returned HTTP 404.',
        ]);

        $card = (new ProductPresenter)->card($product->fresh()->load(['images', 'variants', 'metafields']));

        // Null is what makes the storefront draw its woven placeholder panel.
        $this->assertNull($card['image']);
    }

    #[Test]
    public function the_product_page_hides_shopifys_placeholder_option(): void
    {
        $product = $this->makeProduct('tapo-c200');
        $product->options()->create(['name' => 'Title', 'position' => 1]);

        $this->get('/shop/tapo-c200')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/product')
                // "Title / Default Title" is noise, not a choice a shopper makes.
                ->where('product.options', []));
    }

    private function makeProduct(string $handle, array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ], $attributes));

        $product->variants()->create([
            'option_key' => ProductVariant::makeOptionKey(null, 'Default Title', null, null),
            'option1' => 'Default Title',
            'price' => 129,
            'inventory_quantity' => 100,
        ]);

        return $product->syncVariantAggregates();
    }
}
