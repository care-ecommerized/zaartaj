<?php

namespace App\Jobs;

use App\Catalog\Import\ProductImporter;
use App\Catalog\Import\ShopifyCsvReader;
use App\Enums\ImportStatus;
use App\Models\ProductImport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProductImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * A failed import is resumed by re-uploading, not by retrying: a partial run has
     * already written some products, and a blind retry would restart from line one.
     */
    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(public readonly ProductImport $import) {}

    public function uniqueId(): string
    {
        return (string) $this->import->id;
    }

    public function handle(ProductImporter $importer): void
    {
        $this->import->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        $imageIds = [];

        try {
            $reader = new ShopifyCsvReader($this->import->absolutePath());

            $created = 0;
            $updated = 0;
            $rowsRead = 0;

            foreach ($reader->products() as $group) {
                $rowsRead += count($group->rows);

                try {
                    $result = $importer->import($group, $reader->metafieldColumns());

                    $result->wasCreated ? $created++ : $updated++;
                    $imageIds = array_merge($imageIds, $result->imageIdsAwaitingMirror);
                } catch (Throwable $e) {
                    /*
                     * One malformed product must not abandon the other 300. The row is
                     * logged against the import so the admin screen can name it.
                     */
                    $this->import->recordFailure(
                        $group->firstLine(),
                        $e->getMessage(),
                        $group->handle,
                        ['exception' => $e::class],
                    );

                    Log::warning('Product import row failed.', [
                        'import_id' => $this->import->id,
                        'handle' => $group->handle,
                        'line' => $group->firstLine(),
                        'exception' => $e,
                    ]);
                }
            }

            $this->import->refresh()->update([
                'status' => $this->import->failures > 0
                    ? ImportStatus::CompletedWithErrors
                    : ImportStatus::Completed,
                'rows_read' => $rowsRead,
                'products_created' => $created,
                'products_updated' => $updated,
                'images_queued' => count($imageIds),
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The file itself was unreadable or not a Shopify export.
            $this->import->update([
                'status' => ImportStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        /*
         * Mirroring is dispatched only after the catalogue is committed, so a failure
         * downloading images can never roll back imported products.
         */
        foreach ($imageIds as $imageId) {
            MirrorProductImage::dispatch($imageId);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            'error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
