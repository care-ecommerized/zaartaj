<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    public const METHOD_FLAT = 'flat';

    public const METHOD_WEIGHT = 'weight';

    public const METHOD_ORDER_VALUE = 'order_value';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shipping_zone_id',
        'method',
        'amount',
        'min_threshold',
        'max_threshold',
        'free_over',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'min_threshold' => 'decimal:3',
            'max_threshold' => 'decimal:3',
            'free_over' => 'decimal:2',
            'priority' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}
