<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MirrorProductImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    /**
     * Image types we are willing to write to disk.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    private const MAX_BYTES = 15 * 1024 * 1024;

    public function __construct(public readonly int $imageId) {}

    public function uniqueId(): string
    {
        return (string) $this->imageId;
    }

    /**
     * Copy a product image off the Shopify CDN and onto our own disk.
     *
     * Imported products point at cdn.shopify.com, which stops serving the moment
     * that store is closed. Until this runs, ProductImage::url() falls back to the
     * remote URL, so the storefront works either way.
     */
    public function handle(): void
    {
        $image = ProductImage::find($this->imageId);

        if (! $image || $image->mirrored_at !== null) {
            return;
        }

        $image->increment('mirror_attempts');

        $response = Http::timeout(30)
            ->retry(2, 500, throw: false)
            ->get($image->src);

        if (! $response->successful()) {
            $this->recordFailure($image, "Origin returned HTTP {$response->status()}.");

            return;
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = self::ALLOWED_TYPES[$contentType] ?? null;

        if ($extension === null) {
            // Don't write whatever an unexpected origin decided to hand back.
            $this->recordFailure($image, "Unsupported content type [{$contentType}].");

            return;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            $this->recordFailure($image, 'Image was empty or exceeded the size limit.');

            return;
        }

        // Must be a public disk; see config/catalog.php.
        $disk = config('catalog.image_disk');
        $path = sprintf(
            'products/%d/%s.%s',
            $image->product_id,
            Str::substr($image->src_hash, 0, 16),
            $extension,
        );

        Storage::disk($disk)->put($path, $body);

        $image->forceFill([
            'disk' => $disk,
            'path' => $path,
            'mirrored_at' => now(),
            'mirror_error' => null,
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        ProductImage::whereKey($this->imageId)->update([
            'mirror_error' => Str::limit($e->getMessage(), 500),
        ]);
    }

    private function recordFailure(ProductImage $image, string $message): void
    {
        $image->forceFill(['mirror_error' => $message])->save();

        // Surfaces in the queue's failed_jobs table once retries are exhausted.
        $this->fail($message);
    }
}
