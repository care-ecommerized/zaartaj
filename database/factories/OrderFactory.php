<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = $this->faker->randomFloat(2, 500, 8000);

        return [
            'order_number' => 'ZT'.now()->format('ymd').Str::upper(Str::random(6)),
            'user_id' => null,
            'status' => OrderStatus::Confirmed,
            'customer_name' => $this->faker->name(),
            'customer_phone' => '01'.$this->faker->numberBetween(3, 9).$this->faker->numerify('########'),
            'customer_email' => $this->faker->safeEmail(),
            'customer_address' => $this->faker->address(),
            'total' => $total,
            'cod_amount' => $total,
            'note' => null,
        ];
    }
}
