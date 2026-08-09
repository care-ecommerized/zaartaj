<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single entry in an order's activity timeline — placed, paid, status change,
 * shipment booked, or a staff comment. Written at the order lifecycle seams so
 * the admin detail page can show a faithful, reverse-chronological history.
 */
class OrderEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'type',
        'title',
        'body',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'actor_name',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Record a timeline entry against an order. The single writer used at every
     * lifecycle seam so callers stay a one-liner and the shape stays consistent.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(Order $order, string $type, string $title, array $attributes = []): self
    {
        return $order->events()->create(array_merge([
            'type' => $type,
            'title' => $title,
        ], $attributes));
    }
}
