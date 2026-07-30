<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CheckoutSession>
 */
class CheckoutSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'user_id' => null,
            'email' => $this->faker->safeEmail(),
            'phone' => '01'.$this->faker->numberBetween(3, 9).$this->faker->numerify('########'),
            'currency' => 'AED',
            'locale' => 'en',
            'cart' => [
                ['slug' => 'sample-gown', 'size' => null, 'quantity' => 1],
            ],
            'subtotal' => $this->faker->randomFloat(2, 100, 5000),
            'status' => CheckoutSession::STATUS_OPEN,
            'last_activity_at' => now(),
        ];
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => CheckoutSession::STATUS_ABANDONED,
            'reminder_sent_at' => now(),
        ]);
    }
}
