<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'name_ar',
        'slug',
        'path',
        'path_hash',
        'depth',
        'position',
        'description',
        'image_path',
        'is_visible',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'position' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The category name in the active locale, falling back to English.
     *
     * Prefers the Arabic name only for an Arabic request and only when it has
     * been filled in, so an untranslated category still reads correctly.
     */
    public function localizedName(): string
    {
        if (app()->getLocale() === 'ar' && filled($this->name_ar)) {
            return $this->name_ar;
        }

        return $this->name;
    }

    /**
     * The public URL of the uploaded category photo, or null when none is set.
     *
     * For a local, same-origin disk the host is stripped so the image is not
     * pinned to APP_URL (which 404s when the site is reached on another host,
     * e.g. `php artisan serve` or a LAN IP). This mirrors ProductImage::url().
     */
    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        $url = Storage::disk(config('catalog.image_disk'))->url($this->image_path);

        // The image disk is same-origin (public), so strip the host and return a
        // root-relative path rather than pinning the image to APP_URL.
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The shop's own categories, in navigation order.
     *
     * Kept flat for now, but the parent/child columns stay so a category can be
     * broken into sub-categories later without a migration.
     */
    public function scopeNavigable(Builder $query): Builder
    {
        return $query->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('position')
            ->orderBy('name');
    }
}
