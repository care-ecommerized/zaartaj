<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'src',
        'src_hash',
        'position',
        'alt',
        'disk',
        'path',
        'mirrored_at',
        'mirror_attempts',
        'mirror_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'mirrored_at' => 'datetime',
            'mirror_attempts' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public static function hashSrc(string $src): string
    {
        return sha1(trim($src));
    }

    /**
     * Images still pointing at the remote CDN.
     */
    public function scopeAwaitingMirror(Builder $query): Builder
    {
        return $query->whereNull('mirrored_at');
    }

    /**
     * Images that will actually load.
     *
     * An image is safe to render once it has been mirrored, or while it is still
     * queued and the origin has not complained. A supplier that has deleted the
     * file answers 404 to the mirror job, and rendering that URL anyway produces a
     * broken image where the woven placeholder belongs.
     */
    public function scopeRenderable(Builder $query): Builder
    {
        return $query->where(
            fn (Builder $sub) => $sub->whereNotNull('mirrored_at')->orWhereNull('mirror_error')
        );
    }

    public function isRenderable(): bool
    {
        return $this->mirrored_at !== null || $this->mirror_error === null;
    }

    /**
     * The URL to render: the local mirror once it exists, the origin until then.
     *
     * For a local-driver disk the host is stripped, leaving a root-relative URL.
     * Storage::url() builds those from APP_URL, which pins every image to one
     * hostname — so images 404 whenever the site is reached on another, whether
     * that is `php artisan serve`, an IP on the LAN, or staging running with a
     * production APP_URL. A same-origin disk needs no host at all.
     */
    public function url(): string
    {
        if (! $this->mirrored_at || ! $this->disk || ! $this->path) {
            return $this->src;
        }

        $url = Storage::disk($this->disk)->url($this->path);

        if (config("filesystems.disks.{$this->disk}.driver") !== 'local') {
            // S3 and friends are genuinely on another host.
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path === false || $path === null ? $url : $path;
    }
}
