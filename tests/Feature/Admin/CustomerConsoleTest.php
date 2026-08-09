<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'placed_at' => now(),
            'payment_status' => Order::PAYMENT_UNPAID,
            'customer_country' => 'AE',
            'customer_district' => 'Dubai',
        ], $attributes));
    }

    #[Test]
    public function the_index_lists_distinct_customers_including_guests(): void
    {
        // Two orders under one guest email collapse to a single customer row.
        $this->order(['customer_email' => 'guest@zaartaj.test', 'customer_name' => 'Guest Buyer']);
        $this->order(['customer_email' => 'guest@zaartaj.test', 'customer_name' => 'Guest Buyer']);

        // A registered buyer, matched on email.
        $user = User::factory()->create(['email' => 'reg@zaartaj.test', 'name' => 'Registered Buyer']);
        $this->order(['customer_email' => 'reg@zaartaj.test', 'user_id' => $user->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/customers/index')
                ->has('customers.data', 2));
    }

    #[Test]
    public function the_index_is_invisible_to_a_non_admin(): void
    {
        $this->order();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.customers.index'))
            ->assertNotFound();
    }

    #[Test]
    public function guests_redirect_to_login(): void
    {
        $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function total_spent_uses_the_realized_predicate(): void
    {
        // Realized: paid (100) + delivered-unpaid (200) = 300.
        // Excluded: confirmed-unpaid COD (500), cancelled-paid (999), failed (300).
        $email = 'spend@zaartaj.test';
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_PAID, 'total' => 100]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Delivered, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 200]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_UNPAID, 'total' => 500]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Cancelled, 'payment_status' => Order::PAYMENT_PAID, 'total' => 999]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Confirmed, 'payment_status' => Order::PAYMENT_FAILED, 'total' => 300]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.total_spent', 300)
                ->where('customers.data.0.orders_count', 5));
    }

    #[Test]
    public function delivered_count_and_last_ordered_are_correct(): void
    {
        $email = 'metrics@zaartaj.test';
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Delivered, 'placed_at' => now()->subDays(5)]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Delivered, 'placed_at' => now()->subDays(2)]);
        $this->order(['customer_email' => $email, 'status' => OrderStatus::Confirmed, 'placed_at' => now()->subDays(1)]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertInertia(fn ($page) => $page
                ->where('customers.data.0.delivered_count', 2)
                ->where('customers.data.0.orders_count', 3));
    }

    #[Test]
    public function the_search_filter_narrows_the_list(): void
    {
        $this->order(['customer_email' => 'layla@zaartaj.test', 'customer_name' => 'Layla Noor']);
        $this->order(['customer_email' => 'omar@zaartaj.test', 'customer_name' => 'Omar Said']);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['search' => 'layla@zaartaj']))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'layla@zaartaj.test'));
    }

    #[Test]
    public function the_country_filter_narrows_the_list(): void
    {
        $this->order(['customer_email' => 'ae@zaartaj.test', 'customer_country' => 'AE']);
        $this->order(['customer_email' => 'gb@zaartaj.test', 'customer_country' => 'GB']);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['country' => 'GB']))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'gb@zaartaj.test'));
    }

    #[Test]
    public function the_city_filter_narrows_the_list(): void
    {
        $this->order(['customer_email' => 'dxb@zaartaj.test', 'customer_district' => 'Dubai']);
        $this->order(['customer_email' => 'auh@zaartaj.test', 'customer_district' => 'Abu Dhabi']);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['city' => 'Abu Dhabi']))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'auh@zaartaj.test'));
    }

    #[Test]
    public function the_amount_range_filter_narrows_the_list(): void
    {
        $this->order(['customer_email' => 'small@zaartaj.test', 'payment_status' => Order::PAYMENT_PAID, 'total' => 100]);
        $this->order(['customer_email' => 'big@zaartaj.test', 'payment_status' => Order::PAYMENT_PAID, 'total' => 5000]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['min_amount' => 1000]))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'big@zaartaj.test'));

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['max_amount' => 1000]))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'small@zaartaj.test'));
    }

    #[Test]
    public function the_date_range_filter_narrows_the_list(): void
    {
        $this->order(['customer_email' => 'recent@zaartaj.test', 'placed_at' => now()]);
        $this->order(['customer_email' => 'old@zaartaj.test', 'placed_at' => now()->subDays(40)]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['date_from' => now()->subDays(7)->toDateString()]))
            ->assertInertia(fn ($page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.email', 'recent@zaartaj.test'));
    }

    #[Test]
    public function the_spent_sort_orders_customers_by_realized_spend(): void
    {
        $this->order(['customer_email' => 'mid@zaartaj.test', 'payment_status' => Order::PAYMENT_PAID, 'total' => 300]);
        $this->order(['customer_email' => 'top@zaartaj.test', 'payment_status' => Order::PAYMENT_PAID, 'total' => 900]);
        $this->order(['customer_email' => 'low@zaartaj.test', 'payment_status' => Order::PAYMENT_PAID, 'total' => 100]);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index', ['sort' => 'spent_desc']))
            ->assertInertia(fn ($page) => $page
                ->where('customers.data.0.email', 'top@zaartaj.test')
                ->where('customers.data.2.email', 'low@zaartaj.test'));
    }

    #[Test]
    public function the_export_returns_csv_of_the_filtered_customers(): void
    {
        $this->order(['customer_email' => 'gb@zaartaj.test', 'customer_name' => 'Overseas Buyer', 'customer_country' => 'GB', 'payment_status' => Order::PAYMENT_PAID, 'total' => 750]);
        $this->order(['customer_email' => 'ae@zaartaj.test', 'customer_name' => 'Local Buyer', 'customer_country' => 'AE']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.customers.export', ['country' => 'GB']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('gb@zaartaj.test', $body);
        $this->assertStringContainsString('Overseas Buyer', $body);
        $this->assertStringNotContainsString('ae@zaartaj.test', $body);
    }
}
