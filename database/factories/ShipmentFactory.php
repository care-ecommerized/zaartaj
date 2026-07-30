<?php

namespace Database\Factories;

use App\Delivery\Enums\DeliveryType;
use App\Delivery\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = Order::factory();

        return [
            'order_id' => $order,
            'courier' => 'steadfast',
            'invoice' => fn (array $attributes) => Order::find($attributes['order_id'])?->order_number
                ?? $this->faker->unique()->bothify('ZT######'),
            'status' => ShipmentStatus::Draft,
            'cod_amount' => $this->faker->randomFloat(2, 500, 8000),
            'delivery_type' => DeliveryType::Home,
            'attempts' => 0,
        ];
    }

    /**
     * A shipment the courier has already accepted.
     */
    public function dispatched(): static
    {
        return $this->state(fn () => [
            'consignment_id' => (string) $this->faker->unique()->numberBetween(1000000, 9999999),
            'tracking_code' => strtoupper($this->faker->bothify('########')),
            'status' => ShipmentStatus::InReview,
            'provider_status' => 'in_review',
            'dispatched_at' => now(),
            'last_synced_at' => now(),
        ]);
    }
}
