<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FlushCatalog extends Command
{
    protected $signature = 'catalog:flush {--keep-categories : Leave the category rows in place}';

    protected $description = 'Delete every product, image, tag and import record';

    /**
     * Empty the catalogue so a different supplier export can be imported cleanly.
     *
     * Orders are deliberately untouched: this removes what is for sale, not any
     * record of what has been sold.
     */
    public function handle(): int
    {
        $counts = [
            'products' => Product::withTrashed()->count(),
            'images' => ProductImage::count(),
            'categories' => Category::count(),
        ];

        if (! $this->confirm("Delete {$counts['products']} products and {$counts['images']} images?", true)) {
            $this->info('Nothing was deleted.');

            return self::SUCCESS;
        }

        // Truncate needs the foreign keys down; deletes cascade from products anyway.
        DB::transaction(function () {
            Product::withTrashed()->forceDelete();
            Tag::query()->delete();
            ProductImport::query()->delete();

            if (! $this->option('keep-categories')) {
                Category::query()->delete();
            }
        });

        // Mirrored image files would otherwise linger with nothing pointing at them.
        $disk = config('catalog.image_disk');

        if (Storage::disk($disk)->exists('products')) {
            Storage::disk($disk)->deleteDirectory('products');
        }

        $this->info('Catalogue emptied. Orders and users were left alone.');

        return self::SUCCESS;
    }
}
