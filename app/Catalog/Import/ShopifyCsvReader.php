<?php

namespace App\Catalog\Import;

use Generator;
use RuntimeException;

class ShopifyCsvReader
{
    /**
     * Header names mapped to their column index.
     *
     * @var array<string, int>
     */
    private array $columns = [];

    /**
     * Metafield columns, keyed by header name.
     *
     * @var array<string, array{namespace: string, key: string}>
     */
    private array $metafieldColumns = [];

    public function __construct(private readonly string $path) {}

    /**
     * Stream the file one product at a time.
     *
     * The file is read line by line and buffered only for the handle currently
     * being assembled. A 60-column export with thousands of products would not
     * survive being loaded whole, and this keeps memory flat regardless of size.
     *
     * @return Generator<int, ProductRowGroup>
     */
    public function products(): Generator
    {
        $handle = @fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open the uploaded file at [{$this->path}].");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === [null]) {
                throw new RuntimeException('The uploaded file is empty.');
            }

            $this->mapColumns($header);

            $currentHandle = null;
            $buffer = [];
            $lines = [];
            $lineNumber = 1;

            while (($record = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // fgetcsv yields [null] for a blank line.
                if ($record === [null]) {
                    continue;
                }

                $row = $this->toAssoc($record);
                $rowHandle = trim((string) ($row['Handle'] ?? ''));

                // A continuation line can legitimately repeat the handle or leave it
                // blank; either way it belongs to the product being assembled.
                if ($rowHandle === '') {
                    $rowHandle = $currentHandle;
                }

                if ($rowHandle === null || $rowHandle === '') {
                    continue;
                }

                if ($currentHandle !== null && $rowHandle !== $currentHandle) {
                    yield new ProductRowGroup($currentHandle, $buffer, $lines);
                    $buffer = [];
                    $lines = [];
                }

                $currentHandle = $rowHandle;
                $buffer[] = $row;
                $lines[] = $lineNumber;
            }

            if ($currentHandle !== null && $buffer !== []) {
                yield new ProductRowGroup($currentHandle, $buffer, $lines);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, array{namespace: string, key: string}>
     */
    public function metafieldColumns(): array
    {
        return $this->metafieldColumns;
    }

    /**
     * @param  list<string|null>  $header
     */
    private function mapColumns(array $header): void
    {
        foreach ($header as $index => $name) {
            $name = trim((string) $name);

            if ($index === 0) {
                // Excel writes a UTF-8 BOM ahead of the first header, which would
                // otherwise make the "Handle" column unfindable by name.
                $name = preg_replace('/^\x{FEFF}/u', '', $name) ?? $name;
            }

            if ($name === '') {
                continue;
            }

            $this->columns[$name] = $index;

            // e.g. "Color (product.metafields.shopify.color-pattern)".
            if (preg_match('/\(product\.metafields\.([^.)]+)\.([^)]+)\)/', $name, $matches) === 1) {
                $this->metafieldColumns[$name] = [
                    'namespace' => $matches[1],
                    'key' => $matches[2],
                ];
            }
        }

        if (! isset($this->columns['Handle'])) {
            throw new RuntimeException('This does not look like a Shopify product export: no "Handle" column was found.');
        }
    }

    /**
     * @param  list<string|null>  $record
     * @return array<string, string|null>
     */
    private function toAssoc(array $record): array
    {
        $row = [];

        foreach ($this->columns as $name => $index) {
            $value = $record[$index] ?? null;

            if ($value !== null) {
                $value = trim($value);
                $value = $value === '' ? null : Encoding::fix($value);
            }

            $row[$name] = $value;
        }

        return $row;
    }
}
