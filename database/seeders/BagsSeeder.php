<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Evening bags sourced from Alibaba, priced for the Bangladeshi store.
 *
 * Retail price = (supplier USD price at the smallest quantity tier x 120 BDT)
 * rounded to the nearest 5, plus a flat 500 BDT profit per bag.
 *
 * Idempotent: keyed on handle, so re-running updates rather than duplicates.
 * Photos are pulled from storage/app/incoming-bags/<folder>/ — drop 1-5 image
 * files (1.jpg, 2.jpg ...) into a bag's folder and re-run to attach them.
 */
class BagsSeeder extends Seeder
{
    /** @var list<array<string,mixed>> */
    private array $bags = [
        [
            'folder' => 'bag-2',
            'handle' => 'attitude-black-velvet-ring-clutch',
            'title' => 'Attitude Black Velvet Ring Clutch',
            'price' => 1150, // PLACEHOLDER price — confirm the real Alibaba price.
            'body' => 'A structured black velvet clutch anchored by a bold polished-gold ring on the front, carried on a fine gold chain. Understated and modern — the piece that finishes an all-black evening look.',
        ],
        [
            'folder' => 'bag-1',
            'handle' => 'modern-cloud-clutch-bag',
            'title' => 'Modern Style Classic Cloud Clutch Bag',
            'price' => 1120, // $5.15
            'body' => 'A soft, gathered cloud-pouch clutch framed in polished gold — the easy evening bag that goes with everything. Smooth PU shell, magnetic top, detachable wrist chain. Approx. 20 x 7.5 x 11 cm.',
        ],
        [
            'folder' => 'bag-3',
            'handle' => 'retro-heart-chain-party-handbag',
            'title' => 'Retro Heart Chain Party Handbag',
            'price' => 860, // $3.00
            'body' => 'A glittering heart-shaped hard-case bag with a fine gold shoulder chain and a neat flap closure. European retro shape, roomy for its size. A romantic finish for weddings and dinners.',
        ],
        [
            'folder' => 'bag-4',
            'handle' => 'wrinkled-heart-evening-clutch',
            'title' => 'Wrinkled Heart Evening Clutch Purse',
            'price' => 1595, // $9.12
            'body' => 'A sweet pleated heart clutch in soft polyester with a hasp closure and slim carry chain. The wrinkled texture catches light beautifully. Made for party and dinner evenings.',
        ],
        [
            'folder' => 'bag-5',
            'handle' => 'velvet-rhinestone-party-handbag',
            'title' => 'Velvet Rhinestone Party Handbag',
            'price' => 880, // $3.17
            'body' => 'A plush velvet pillow bag quilted with scattered rhinestones, topped by a jewelled square handle and a drop chain. Rich, tactile and quietly glamorous. Approx. 19 x 7.5 x 10.5 cm.',
        ],
        [
            'folder' => 'bag-6',
            'handle' => 'glossy-pebble-clutch-gold-handle',
            'title' => 'Glossy Pebble Clutch with Gold Ring Handle',
            'price' => 1125, // $5.20
            'body' => 'A high-shine pebble-shaped clutch wrapped by a sculptural gold ring handle. A modern statement piece that photographs as well as it holds an evening\'s essentials.',
        ],
        [
            'folder' => 'bag-7',
            'handle' => 'acrylic-oval-chain-clutch',
            'title' => 'Acrylic Oval Chain Clutch',
            'price' => 1195, // $5.79
            'body' => 'A glossy marbled acrylic oval clutch with a delicate gold chain — worn as a sling, a crossbody or carried in hand. Lightweight, glossy and eye-catching under evening light.',
        ],
        [
            'folder' => 'bag-8',
            'handle' => 'ribbon-mini-gift-handbag',
            'title' => 'Ribbon Mini Gift Handbag',
            'price' => 525, // $0.20
            'body' => 'A dainty mini handbag finished with a printed silk-style ribbon — charming as a favour, a gift or a small keepsake bag for wedding and party tables.',
        ],
    ];

    public function run(): void
    {
        $category = Category::where('slug', 'bags')->first();

        if (! $category) {
            $this->command?->error('No "bags" category found — skipping BagsSeeder.');

            return;
        }

        foreach ($this->bags as $bag) {
            $product = Product::updateOrCreate(
                ['handle' => $bag['handle']],
                [
                    'title' => $bag['title'],
                    'body_html' => '<p>'.$bag['body'].'</p>',
                    'vendor' => 'Zaartaj Elegance',
                    'brand' => 'Zaartaj Elegance',
                    'product_type' => 'Bags',
                    'category_id' => $category->id,
                    'status' => ProductStatus::Active,
                    'published_at' => now(),
                    'seo_title' => $bag['title'].' — Zaartaj Elegance',
                    'seo_description' => strip_tags($bag['body']),
                ]
            );

            // A single default variant carries the price and stock.
            ProductVariant::updateOrCreate(
                ['product_id' => $product->id, 'option_key' => 'default'],
                [
                    'sku' => strtoupper(str_replace('-', '', $bag['handle'])),
                    'price' => $bag['price'],
                    'inventory_quantity' => 25,
                    'inventory_policy' => 'deny',
                    'inventory_tracker' => 'shopify',
                    'requires_shipping' => true,
                    'taxable' => false,
                    'position' => 1,
                ]
            );

            $product->syncVariantAggregates();

            $this->attachPhotos($product, $bag['folder']);

            $this->command?->info("Seeded {$bag['handle']} @ {$bag['price']} BDT");
        }
    }

    /**
     * Copy any image files from storage/app/incoming-bags/<folder> onto the public
     * disk and (re)create the product's image rows. No files = fallback tile.
     */
    private function attachPhotos(Product $product, string $folder): void
    {
        $source = storage_path('app/incoming-bags/'.$folder);

        if (! File::isDirectory($source)) {
            return;
        }

        $files = collect(File::files($source))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        if ($files->isEmpty()) {
            return;
        }

        // Re-sync: drop old rows and files, then re-add from the folder.
        $product->images()->delete();
        Storage::disk('public')->deleteDirectory('products/'.$product->id);

        $files->each(function ($file, $index) use ($product) {
            $name = ($index + 1).'-'.substr(md5($file->getFilename()), 0, 8).'.'.strtolower($file->getExtension());
            $path = 'products/'.$product->id.'/'.$name;

            Storage::disk('public')->put($path, File::get($file->getPathname()));

            ProductImage::create([
                'product_id' => $product->id,
                'src' => Storage::disk('public')->url($path),
                'src_hash' => md5($path),
                'position' => $index + 1,
                'alt' => $product->title,
                'disk' => 'public',
                'path' => $path,
                'mirrored_at' => now(),
            ]);
        });

        $this->command?->info("  attached {$files->count()} photo(s) to {$product->handle}");
    }
}
