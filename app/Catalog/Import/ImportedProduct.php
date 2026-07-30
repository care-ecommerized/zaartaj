<?php

namespace App\Catalog\Import;

use App\Models\Product;

/**
 * Outcome of importing a single product group.
 */
class ImportedProduct
{
    /**
     * @param  list<int>  $imageIdsAwaitingMirror
     */
    public function __construct(
        public readonly Product $product,
        public readonly bool $wasCreated,
        public readonly array $imageIdsAwaitingMirror = [],
    ) {}
}
