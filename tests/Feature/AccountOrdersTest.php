<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_orders_relation_returns_only_the_users_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id, 'placed_at' => now()]);
        Order::factory()->create(['user_id' => $other->id, 'placed_at' => now()]);
        Order::factory()->create(['user_id' => null, 'placed_at' => now()]);

        $this->assertCount(2, $user->orders()->get());
    }

    #[Test]
    public function the_account_order_index_lists_only_the_users_own_orders(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Order::factory()->create(['user_id' => $user->id, 'placed_at' => now()]);
        Order::factory()->create(['user_id' => $other->id, 'placed_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/orders/index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $mine->order_number)
            );
    }

    #[Test]
    public function the_account_order_show_is_forbidden_for_another_users_order(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $theirs = Order::factory()->create(['user_id' => $other->id, 'placed_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $theirs))
            ->assertForbidden();
    }

    #[Test]
    public function the_account_order_show_renders_the_users_own_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'placed_at' => now()]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/orders/show')
                ->where('order.order_number', $order->order_number)
            );
    }

    #[Test]
    public function the_checkout_page_includes_the_users_saved_addresses(): void
    {
        $user = User::factory()->create();
        $user->addresses()->create([
            'recipient_name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'address_line' => 'House 1, Road 2',
            'country' => 'BD',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/checkout')
                ->has('addresses', 1)
                ->where('addresses.0.recipient', 'Rahim Uddin')
            );
    }

    #[Test]
    public function a_guest_sees_no_addresses_on_the_checkout_page(): void
    {
        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/checkout')
                ->has('addresses', 0)
            );
    }
}
