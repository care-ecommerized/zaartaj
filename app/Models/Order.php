<?php

namespace App\Models;

use App\Currency\CurrencyService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderConfirmed;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_address',
        'customer_district',
        'customer_country',
        'customer_postcode',
        'subtotal',
        'shipping_total',
        'discount_total',
        'coupon_id',
        'coupon_code',
        'total',
        'currency',
        'base_currency',
        'fx_rate',
        'locale',
        'cod_amount',
        'payment_method',
        'payment_status',
        'note',
        'placed_at',
    ];

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_REFUNDED = 'refunded';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'fx_rate' => 'decimal:8',
            'cod_amount' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * The order total expressed in its presentment currency.
     *
     * `total` is always stored in the base currency; this applies the frozen
     * order-time rate and rounds to the presentment currency's precision through
     * the money authority, so it never drifts from what the customer was shown.
     */
    public function presentmentTotal(): float
    {
        $currencies = app(CurrencyService::class);

        return round((float) $this->total * (float) $this->fx_rate, $currencies->decimalsFor($this->currency), PHP_ROUND_HALF_UP);
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * A human-facing order reference, unique and hard to guess.
     *
     * The random tail is what lets a guest reach their confirmation page by
     * order number without it being enumerable.
     */
    public static function generateNumber(): string
    {
        do {
            $number = 'ZT-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (self::where('order_number', $number)->exists());

        return $number;
    }

    public function markPaid(): void
    {
        $wasUnpaid = $this->payment_status !== self::PAYMENT_PAID;

        $this->forceFill(['payment_status' => self::PAYMENT_PAID])->save();

        // Record the settlement once — guards against a callback and an IPN/retry
        // both marking the same order paid and doubling up the timeline.
        if ($wasUnpaid) {
            OrderEvent::record($this, 'payment', 'Payment confirmed', [
                'actor_type' => 'system',
                'meta' => ['payment_status' => self::PAYMENT_PAID],
            ]);
        }

        $this->confirm();
    }

    /**
     * Move the order into the Confirmed state, firing OrderConfirmed exactly
     * once. A no-op if it is already confirmed or has moved past it, so callers
     * (payment settlement, staff confirmation) can call it freely without
     * double-booking a shipment.
     */
    public function confirm(): void
    {
        if ($this->status !== OrderStatus::Pending) {
            return;
        }

        $this->forceFill(['status' => OrderStatus::Confirmed])->save();

        OrderConfirmed::dispatch($this);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * The order's activity timeline, newest first.
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest()->latest('id');
    }

    /**
     * The shipment currently representing this order, ignoring earlier failed attempts.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }
}
