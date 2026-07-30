<?php

namespace Tests\Feature;

use App\Catalog\CategoryResolver;
use App\Catalog\Import\Encoding;
use App\Catalog\Import\HtmlSanitizer;
use App\Catalog\Import\ImportedProduct;
use App\Catalog\Import\ProductImporter;
use App\Catalog\Import\ProductRowGroup;
use App\Enums\ImportStatus;
use App\Enums\ProductStatus;
use App\Jobs\MirrorProductImage;
use App\Jobs\ProcessProductImport;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The subset of the Shopify export's 60+ columns that the importer reads.
     *
     * @var list<string>
     */
    private const HEADER = [
        'Handle', 'Title', 'Body (HTML)', 'Vendor', 'Product Category', 'Type', 'Tags', 'Published',
        'Option1 Name', 'Option1 Value', 'Option2 Name', 'Option2 Value', 'Option3 Name', 'Option3 Value',
        'Variant SKU', 'Variant Grams', 'Variant Inventory Tracker', 'Variant Inventory Policy',
        'Variant Fulfillment Service', 'Variant Price', 'Variant Compare At Price',
        'Variant Requires Shipping', 'Variant Taxable', 'Variant Barcode',
        'Image Src', 'Image Position', 'Image Alt Text', 'SEO Title', 'SEO Description',
        'Color (product.metafields.shopify.color-pattern)',
        'Connectivity technology (product.metafields.shopify.connectivity-technology)',
        'Variant Image', 'Variant Weight Unit', 'Variant Tax Code', 'Cost per item', 'Status',
    ];

    #[Test]
    public function it_imports_a_product_with_its_continuation_image_rows(): void
    {
        $path = $this->writeCsv([
            $this->productRow(),
            // Continuation lines: same handle, product columns blank, image only.
            $this->imageRow('geekera-3-in-1-wireless-charging-station', 'https://cdn.shopify.com/b.jpg', 2),
            $this->imageRow('geekera-3-in-1-wireless-charging-station', 'https://cdn.shopify.com/c.jpg', 3),
        ]);

        $this->importFile($path);

        $product = Product::where('handle', 'geekera-3-in-1-wireless-charging-station')->firstOrFail();

        $this->assertSame('Ecommerized Shop', $product->vendor);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNotNull($product->published_at);
        $this->assertCount(3, $product->images);
        $this->assertCount(1, $product->variants);
        $this->assertSame('129.00', $product->variants->first()->price);
    }

    #[Test]
    public function it_repairs_the_double_encoded_text_the_export_is_full_of(): void
    {
        // An em dash written as UTF-8 and read back as Latin-1: "â" plus two C1 bytes.
        $mangled = "GEEKERA Charging Station \u{00E2}\u{0080}\u{0094} Travel & Home";

        $path = $this->writeCsv([
            $this->productRow(['Title' => $mangled]),
        ]);

        $this->importFile($path);

        $product = Product::where('handle', 'geekera-3-in-1-wireless-charging-station')->firstOrFail();

        $this->assertSame('GEEKERA Charging Station — Travel & Home', $product->title);
        $this->assertStringNotContainsString('â', $product->title);
    }

    #[Test]
    public function it_repairs_mangled_arabic_without_touching_healthy_text(): void
    {
        // "بسهولة" as UTF-8 bytes read back as Latin-1.
        $mangled = "\u{00D8}\u{00A8}\u{00D8}\u{00B3}\u{00D9}\u{0087}\u{00D9}\u{0088}\u{00D9}\u{0084}\u{00D8}\u{00A9}";

        $this->assertSame('بسهولة', Encoding::fix($mangled));

        // Text that is already correct must survive untouched.
        $this->assertSame('Café — Zaartaj', Encoding::fix('Café — Zaartaj'));
        $this->assertSame('বাংলা', Encoding::fix('বাংলা'));
        $this->assertSame('Plain ASCII title', Encoding::fix('Plain ASCII title'));
    }

    #[Test]
    public function it_strips_the_chatgpt_markup_some_descriptions_contain(): void
    {
        $dump = '<div class="flex flex-col text-sm pb-25"><section data-turn="assistant">'
            .'<div data-message-author-role="assistant" data-message-id="abc">'
            .'<p data-start="190" data-end="732">Track your health with the Etekcity Smart Body Scale.</p>'
            .'<script>alert(1)</script>'
            .'<a href="javascript:alert(2)">bad link</a>'
            .'</div></section></div>';

        $clean = (new HtmlSanitizer)->clean($dump);

        $this->assertStringContainsString('Track your health with the Etekcity Smart Body Scale.', $clean);
        $this->assertStringNotContainsString('data-message-author-role', $clean);
        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        // The wrapper is unwrapped, not deleted, so the anchor text survives.
        $this->assertStringContainsString('bad link', $clean);
    }

    #[Test]
    public function it_maps_suppliers_taxonomy_onto_the_shops_own_categories(): void
    {
        (new CategoryResolver)->sync();

        $path = $this->writeCsv([
            $this->productRow([
                'Handle' => 'cinderella-gown',
                'Product Category' => 'Apparel & Accessories > Costumes & Accessories > Costumes > Costume Dresses',
            ]),
            $this->productRow([
                'Handle' => 'beaded-clutch',
                'Title' => 'Beaded Evening Clutch',
                'Product Category' => 'Apparel & Accessories > Handbags, Wallets & Cases > Handbags',
            ]),
            $this->productRow([
                'Handle' => 'everyday-abaya',
                'Title' => 'Everyday Abaya',
                'Product Category' => 'Apparel & Accessories > Clothing',
            ]),
        ]);

        $this->importFile($path);

        // Shopify's 4-level path collapses to one curated category.
        $this->assertSame('gowns', Product::where('handle', 'cinderella-gown')->first()->category->slug);
        $this->assertSame('bags', Product::where('handle', 'beaded-clutch')->first()->category->slug);
        $this->assertSame('modest-clothes', Product::where('handle', 'everyday-abaya')->first()->category->slug);

        // Only the configured categories exist — no taxonomy nodes leak in.
        $this->assertSame(
            ['gowns', 'modest-clothes', 'jewellery', 'bags', 'shoes'],
            Category::navigable()->pluck('slug')->all(),
        );

        // The original taxonomy is still recorded for reference.
        $this->assertStringContainsString(
            'Costume Dresses',
            Product::where('handle', 'cinderella-gown')->value('shopify_category'),
        );
    }

    #[Test]
    public function a_product_matching_no_rule_is_left_uncategorised_rather_than_guessed(): void
    {
        (new CategoryResolver)->sync();

        $path = $this->writeCsv([
            $this->productRow([
                'Handle' => 'scented-candle',
                'Title' => 'Scented Candle',
                'Product Category' => 'Home & Garden > Decor > Candles',
                'Type' => '',
                'Tags' => '',
            ]),
        ]);

        $this->importFile($path);

        $this->assertNull(Product::where('handle', 'scented-candle')->first()->category_id);
    }

    #[Test]
    public function it_explodes_semicolon_delimited_metafields_for_filtering(): void
    {
        $path = $this->writeCsv([
            $this->productRow([
                'Color (product.metafields.shopify.color-pattern)' => 'black; red',
                'Connectivity technology (product.metafields.shopify.connectivity-technology)' => 'bluetooth; wireless',
            ]),
        ]);

        $this->importFile($path);

        $product = Product::where('handle', 'geekera-3-in-1-wireless-charging-station')->firstOrFail();

        $this->assertSame('black; red', $product->metafields()->where('key', 'color-pattern')->value('value'));
        $this->assertEqualsCanonicalizing(
            ['black', 'red', 'bluetooth', 'wireless'],
            $product->metafieldValues()->pluck('value')->all(),
        );

        $this->assertTrue(
            Product::whereMetafield('color-pattern', 'red')->where('id', $product->id)->exists()
        );
        $this->assertFalse(
            Product::whereMetafield('color-pattern', 'green')->where('id', $product->id)->exists()
        );
    }

    #[Test]
    public function re_importing_the_same_file_updates_instead_of_duplicating(): void
    {
        $path = $this->writeCsv([
            $this->productRow(),
            $this->imageRow('geekera-3-in-1-wireless-charging-station', 'https://cdn.shopify.com/b.jpg', 2),
        ]);

        $this->importFile($path);

        $product = Product::where('handle', 'geekera-3-in-1-wireless-charging-station')->firstOrFail();
        // Stock is owned by us, not the file, so a re-import must not reset it.
        $product->variants()->update(['inventory_quantity' => 25]);

        $updated = $this->writeCsv([
            $this->productRow(['Title' => 'GEEKERA Charging Station (2026)', 'Variant Price' => '149.00']),
            $this->imageRow('geekera-3-in-1-wireless-charging-station', 'https://cdn.shopify.com/b.jpg', 2),
        ]);

        $this->importFile($updated);

        $this->assertSame(1, Product::count());
        $this->assertSame(1, Product::first()->variants()->count());
        $this->assertSame(2, Product::first()->images()->count());

        $product->refresh();
        $this->assertSame('GEEKERA Charging Station (2026)', $product->title);
        $this->assertSame('149.00', $product->variants->first()->price);
        $this->assertSame(25, $product->variants->first()->inventory_quantity);
        $this->assertSame(25, $product->total_inventory);
    }

    #[Test]
    public function a_broken_row_is_logged_without_abandoning_the_rest_of_the_file(): void
    {
        $path = $this->writeCsv([
            $this->productRow(['Handle' => 'broken-product', 'Title' => 'Malformed product']),
            $this->productRow(),
        ]);

        $import = ProductImport::create([
            'original_filename' => basename($path),
            'disk' => 'local',
            'path' => $this->storeToLocalDisk($path),
        ]);

        /*
         * Fail one specific product. Provoking a real database error is not portable
         * — SQLite, which the suite runs on, ignores VARCHAR limits that MySQL
         * enforces — and what matters here is that the job isolates the failure.
         */
        $importer = new class extends ProductImporter
        {
            public function import(ProductRowGroup $group, array $metafieldColumns = []): ImportedProduct
            {
                if ($group->handle === 'broken-product') {
                    throw new \RuntimeException('Column count does not match.');
                }

                return parent::import($group, $metafieldColumns);
            }
        };

        (new ProcessProductImport($import))->handle($importer);

        $import->refresh();

        $this->assertSame(ImportStatus::CompletedWithErrors, $import->status);
        $this->assertSame(1, $import->failures);
        $this->assertSame('broken-product', $import->rows()->first()->handle);
        $this->assertSame('Column count does not match.', $import->rows()->first()->message);

        // The healthy product still landed, and is counted.
        $this->assertSame(1, $import->products_created);
        $this->assertDatabaseHas('products', ['handle' => 'geekera-3-in-1-wireless-charging-station']);
    }

    #[Test]
    public function it_rejects_a_file_that_is_not_a_shopify_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "name,price\nSomething,10\n");

        $import = ProductImport::create([
            'original_filename' => 'wrong.csv',
            'disk' => 'local',
            'path' => 'imports/wrong.csv',
        ]);

        Storage::disk('local')->put('imports/wrong.csv', file_get_contents($path));

        try {
            (new ProcessProductImport($import))->handle(new ProductImporter);
        } catch (\RuntimeException $e) {
            // Rethrown so the queue records the failure.
        }

        $import->refresh();

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertStringContainsString('Handle', (string) $import->error);
    }

    #[Test]
    public function an_admin_can_upload_an_export_and_it_is_queued_rather_than_parsed_inline(): void
    {
        Queue::fake();
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $csv = implode("\n", [
            implode(',', self::HEADER),
            implode(',', array_fill(0, count(self::HEADER), '')),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.products.imports.store'), [
            'file' => UploadedFile::fake()->createWithContent('products_export_1.csv', $csv),
        ]);

        $import = ProductImport::firstOrFail();

        $response->assertRedirect(route('admin.products.imports.show', $import));
        $this->assertSame('products_export_1.csv', $import->original_filename);
        $this->assertSame(ImportStatus::Pending, $import->status);

        Storage::disk('local')->assertExists($import->path);
        Queue::assertPushed(ProcessProductImport::class);
    }

    #[Test]
    public function the_admin_area_is_invisible_to_a_signed_in_customer(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.products.index'))->assertNotFound();
        $this->actingAs($customer)->post(route('admin.products.imports.store'))->assertNotFound();
        $this->actingAs($customer)->get(route('admin.products.imports.index'))->assertNotFound();
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_page_rather_than_a_404(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function it_queues_one_mirror_job_per_newly_imported_image(): void
    {
        Queue::fake();

        $path = $this->writeCsv([
            $this->productRow(),
            $this->imageRow('geekera-3-in-1-wireless-charging-station', 'https://cdn.shopify.com/b.jpg', 2),
        ]);

        $import = ProductImport::create([
            'original_filename' => 'export.csv',
            'disk' => 'local',
            'path' => $this->storeToLocalDisk($path),
        ]);

        (new ProcessProductImport($import))->handle(new ProductImporter);

        Queue::assertPushed(MirrorProductImage::class, 2);
        $this->assertSame(2, $import->refresh()->images_queued);
    }

    /**
     * Run a CSV through the importer the same way the queued job does.
     */
    private function importFile(string $path): ProductImport
    {
        $import = ProductImport::create([
            'original_filename' => basename($path),
            'disk' => 'local',
            'path' => $this->storeToLocalDisk($path),
        ]);

        (new ProcessProductImport($import))->handle(new ProductImporter);

        return $import;
    }

    private function storeToLocalDisk(string $path): string
    {
        $relative = 'imports/'.basename($path);

        Storage::disk('local')->put($relative, file_get_contents($path));

        return $relative;
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'shopify').'.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, self::HEADER);

        foreach ($rows as $row) {
            // Emit in header order, leaving unspecified columns blank as Shopify does.
            fputcsv($handle, array_map(fn (string $column) => $row[$column] ?? '', self::HEADER));
        }

        fclose($handle);

        return $path;
    }

    /**
     * A product line, modelled on the first GEEKERA row of the real export.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function productRow(array $overrides = []): array
    {
        return array_merge([
            'Handle' => 'geekera-3-in-1-wireless-charging-station',
            'Title' => 'GEEKERA 3 in 1 Wireless Charging Station',
            'Body (HTML)' => '<p>Upgrade your charging experience.</p>',
            'Vendor' => 'Ecommerized Shop',
            'Product Category' => 'Electronics > Electronics Accessories > Power > Wireless Chargers',
            'Published' => 'true',
            'Option1 Name' => 'Title',
            'Option1 Value' => 'Default Title',
            'Variant Grams' => '0.0',
            'Variant Inventory Tracker' => 'shopify',
            'Variant Inventory Policy' => 'deny',
            'Variant Fulfillment Service' => 'manual',
            'Variant Price' => '129.00',
            'Variant Requires Shipping' => 'true',
            'Variant Taxable' => 'true',
            'Image Src' => 'https://cdn.shopify.com/a.jpg',
            'Image Position' => '1',
            'Variant Weight Unit' => 'kg',
            'Status' => 'active',
        ], $overrides);
    }

    /**
     * A continuation line: the handle repeats, everything else but the image is blank.
     *
     * @return array<string, string>
     */
    private function imageRow(string $handle, string $src, int $position): array
    {
        return [
            'Handle' => $handle,
            'Image Src' => $src,
            'Image Position' => (string) $position,
        ];
    }
}
