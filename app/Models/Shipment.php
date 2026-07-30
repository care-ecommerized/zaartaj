<?php

namespace App\Models;

use App\Delivery\Enums\DeliveryType;
use App\Delivery\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'courier',
        'invoice',
        'consignment_id',
        'tracking_code',
        'status',
        'provider_status',
        'cod_amount',
        'delivery_type',
        'note',
        'failure_reason',
        'attempts',
        'dispatched_at',
        'delivered_at',
        'last_synced_at',
        'last_response',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delivery_type' => DeliveryType::class,
            'cod_amount' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Shipments the courier still owes us a final answer on.
     */
    public function scopeTrackable(Builder $query): Builder
    {
        return $query->whereNotNull('consignment_id')->whereNotIn('status', [
            ShipmentStatus::Delivered->value,
            ShipmentStatus::PartiallyDelivered->value,
            ShipmentStatus::Cancelled->value,
        ]);
    }
}
