<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'countries',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    /**
     * @param  Builder<ShippingZone>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Whether this zone is the catch-all — it lists no countries and so prices
     * any destination no other zone claims.
     */
    public function isCatchAll(): bool
    {
        return empty($this->countries);
    }

    /**
     * Whether this zone covers the given ISO-3166-1 alpha-2 country code.
     */
    public function covers(string $iso2): bool
    {
        $codes = array_map('strtoupper', $this->countries ?? []);

        return in_array(strtoupper($iso2), $codes, true);
    }
}
