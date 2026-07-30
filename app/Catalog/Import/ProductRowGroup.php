<?php

namespace App\Catalog\Import;

/**
 * Every CSV line belonging to one product handle.
 *
 * A Shopify export spreads a product across consecutive lines: the first carries
 * the product fields plus its first variant and image, and each line after it adds
 * another variant or another image, with the product columns left blank.
 */
class ProductRowGroup
{
    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  list<int>  $lineNumbers
     */
    public function __construct(
        public readonly string $handle,
        public readonly array $rows,
        public readonly array $lineNumbers,
    ) {}

    public function firstLine(): int
    {
        return $this->lineNumbers[0] ?? 0;
    }

    /**
     * The line that carries the product-level columns.
     *
     * Normally the first, but an export that has been hand-edited can leave the
     * title blank on it, so we take the first row that actually has one.
     *
     * @return array<string, string|null>
     */
    public function primaryRow(): array
    {
        foreach ($this->rows as $row) {
            if (filled($row['Title'] ?? null)) {
                return $row;
            }
        }

        return $this->rows[0] ?? [];
    }

    /**
     * Rows that describe a variant.
     *
     * Image-only continuation lines leave the price column blank, which is what
     * separates them from a genuine second variant.
     *
     * @return list<array<string, string|null>>
     */
    public function variantRows(): array
    {
        $rows = array_values(array_filter(
            $this->rows,
            fn (array $row) => filled($row['Variant Price'] ?? null)
        ));

        // A product with no price anywhere still needs one variant to be orderable.
        return $rows ?: [$this->primaryRow()];
    }

    /**
     * Every distinct image across the group, in the order the file lists them.
     *
     * @return list<array{src: string, position: int, alt: string|null, variant_src: string|null}>
     */
    public function images(): array
    {
        $images = [];

        foreach ($this->rows as $row) {
            $src = trim((string) ($row['Image Src'] ?? ''));

            if ($src === '' || isset($images[$src])) {
                continue;
            }

            $images[$src] = [
                'src' => $src,
                'position' => (int) ($row['Image Position'] ?? count($images) + 1),
                'alt' => filled($row['Image Alt Text'] ?? null) ? $row['Image Alt Text'] : null,
                'variant_src' => filled($row['Variant Image'] ?? null) ? trim((string) $row['Variant Image']) : null,
            ];
        }

        return array_values($images);
    }
}
