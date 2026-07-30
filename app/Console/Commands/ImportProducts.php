<?php

namespace App\Console\Commands;

use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportProducts extends Command
{
    protected $signature = 'products:import {file : Path to a Shopify product export CSV}';

    protected $description = 'Import a Shopify product export from the command line';

    /**
     * The same pipeline the admin upload uses, for seeding and for re-running an
     * export without going through the browser.
     */
    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_readable($file)) {
            $this->error("Cannot read [{$file}].");

            return self::FAILURE;
        }

        $path = 'imports/'.now()->format('YmdHis').'-'.basename($file);
        Storage::disk('local')->put($path, file_get_contents($file));

        $import = ProductImport::create([
            'original_filename' => basename($file),
            'disk' => 'local',
            'path' => $path,
        ]);

        $this->info("Importing {$import->original_filename} ...");

        // Run inline: the point of this command is to see the result immediately.
        dispatch_sync(new ProcessProductImport($import));

        $import->refresh();

        $this->table(['Metric', 'Count'], [
            ['Status', $import->status->value],
            ['Rows read', $import->rows_read],
            ['Products created', $import->products_created],
            ['Products updated', $import->products_updated],
            ['Images queued', $import->images_queued],
            ['Failures', $import->failures],
        ]);

        foreach ($import->rows()->limit(10)->get() as $row) {
            $this->warn("Line {$row->line_number} ({$row->handle}): {$row->message}");
        }

        return $import->failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
