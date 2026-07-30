<?php

namespace Database\Seeders;

use App\Catalog\CategoryResolver;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Create the storefront categories listed in config/catalog.php.
     *
     * Idempotent, so it can be re-run after editing the config to add or reorder
     * a category without touching the products already filed under the others.
     */
    public function run(CategoryResolver $resolver): void
    {
        $categories = $resolver->sync();

        $this->command?->info('Synced categories: '.implode(', ', array_keys($categories)));
    }
}
