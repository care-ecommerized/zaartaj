<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A currency the storefront can price and charge in.
 *
 * The `currencies` table is the source of truth for exchange rates; the legacy
 * config('payment.exchange_rates') array is only a fallback. Rates are expressed
 * as `rate_to_base` — units of this currency per 1 unit of the base (AED).
 */
class Currency extends Model
{
    /**
     * The ISO code is the primary key, not an autoincrement id.
     */
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimals',
        'rate_to_base',
        'is_active',
        'is_base',
        'manual_override',
        'rate_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'rate_to_base' => 'decimal:8',
            'is_active' => 'boolean',
            'is_base' => 'boolean',
            'manual_override' => 'boolean',
            'rate_updated_at' => 'datetime',
        ];
    }

    /**
     * Currencies a shopper may be charged in.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The base currency every rate pivots through (the is_base row).
     */
    public static function base(): self
    {
        return static::query()->where('is_base', true)->firstOrFail();
    }

    /**
     * The `rate_to_base` for a code — units of it per 1 base unit.
     */
    public static function rateFor(string $code): float
    {
        return (float) static::query()->whereKey($code)->value('rate_to_base');
    }
}
