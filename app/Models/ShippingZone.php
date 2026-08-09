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
        'districts',
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
            'districts' => 'array',
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

    /**
     * Whether this zone is scoped to specific districts within its country.
     */
    public function isDistrictScoped(): bool
    {
        return ! empty($this->districts);
    }

    /**
     * Whether this zone's district scope includes the given district. Matched
     * case-insensitively. A zone with no districts is country-wide and never
     * matches here — the resolver treats it as the fallback for the country.
     */
    public function coversDistrict(?string $district): bool
    {
        if ($district === null || ! $this->isDistrictScoped()) {
            return false;
        }

        $names = array_map(fn ($d) => strtolower(trim((string) $d)), $this->districts);

        return in_array(strtolower(trim($district)), $names, true);
    }
}
