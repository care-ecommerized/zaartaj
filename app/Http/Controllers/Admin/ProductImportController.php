<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductImportRequest;
use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/products/imports/index', [
            'imports' => ProductImport::query()
                ->with('user:id,name')
                ->latest()
                ->paginate(20)
                ->through(fn (ProductImport $import) => [
                    'id' => $import->id,
                    'filename' => $import->original_filename,
                    'status' => $import->status,
                    'rows_read' => $import->rows_read,
                    'products_created' => $import->products_created,
                    'products_updated' => $import->products_updated,
                    'images_queued' => $import->images_queued,
                    'failures' => $import->failures,
                    'error' => $import->error,
                    'uploaded_by' => $import->user?->name,
                    'created_at' => $import->created_at,
                    'finished_at' => $import->finished_at,
                ]),
        ]);
    }

    /**
     * Accept a Shopify product export and hand it to the queue.
     *
     * The request returns as soon as the file is on disk. Parsing a 60-column export
     * with hundreds of products and downloading their images is far past what a web
     * request should attempt.
     */
    public function store(StoreProductImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        // The private disk: an uploaded catalogue is not something to serve publicly.
        $path = $file->store('imports', 'local');

        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
        ]);

        ProcessProductImport::dispatch($import);

        return redirect()
            ->route('admin.products.imports.show', $import)
            ->with('status', 'Import queued. Products will appear as the file is processed.');
    }

    public function show(ProductImport $import): Response
    {
        return Inertia::render('admin/products/imports/show', [
            'import' => [
                'id' => $import->id,
                'filename' => $import->original_filename,
                'status' => $import->status,
                'rows_read' => $import->rows_read,
                'products_created' => $import->products_created,
                'products_updated' => $import->products_updated,
                'images_queued' => $import->images_queued,
                'failures' => $import->failures,
                'error' => $import->error,
                'started_at' => $import->started_at,
                'finished_at' => $import->finished_at,
            ],
            // The point of the failure log is naming the rows that did not import.
            'failedRows' => $import->rows()->paginate(50),
        ]);
    }
}
