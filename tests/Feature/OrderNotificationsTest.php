<?php

namespace Tests\Feature;

use App\Checkout\CheckoutService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminNewOrderNotification;
use App\Notifications\OrderMailEvent;
use App\Notifications\OrderNotification;
use App\Orders\OrderNotifier;
use App\Orders\OrderStateMachine;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        $this->seed(ShippingZoneSeeder::class);

        // Deterministic admin recipient for the new-order alert assertions.
        Setting::set('notifications.order_email', 'shop@zaartaj.test');
    }

    #[Test]
    public function placing_an_order_invoices_the_customer_and_alerts_the_shop(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);

        $this->place('cinderella-gown', email: 'rahim@example.com');

        Notification::assertSentOnDemand(
            OrderNotification::class,
            fn (OrderNotification $n, array $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'rahim@example.com'
                && $n->mailEvent() === OrderMailEvent::Placed,
        );

        Notification::assertSentOnDemand(
            AdminNewOrderNotification::class,
            fn ($n, array $channels, $notifiable) => in_array('shop@zaartaj.test', (array) ($notifiable->routes['mail'] ?? []), true),
        );
    }

    #[Test]
    public function a_guest_order_without_an_email_does_not_notify_the_customer_but_still_alerts_the_shop(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);

        $this->place('cinderella-gown', email: null);

        Notification::assertSentOnDemandTimes(OrderNotification::class, 0);
        Notification::assertSentOnDemandTimes(AdminNewOrderNotification::class, 1);
    }

    #[Test]
    public function the_order_stores_the_active_locale_and_the_email_uses_it(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);

        $this->app->setLocale('ar');
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');

        $this->assertSame('ar', $order->fresh()->locale);

        Notification::assertSentOnDemand(
            OrderNotification::class,
            fn (OrderNotification $n) => $n->locale === 'ar',
        );
    }

    #[Test]
    public function cancelling_an_order_emails_the_customer(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');

        app(OrderStateMachine::class)->apply($order, OrderStatus::Cancelled, 'Staffer');

        Notification::assertSentOnDemand(
            OrderNotification::class,
            fn (OrderNotification $n) => $n->mailEvent() === OrderMailEvent::Cancelled,
        );
    }

    #[Test]
    public function returning_an_order_emails_the_customer(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');

        // Returned is only reachable from a shipped/delivered order.
        $order->forceFill(['status' => OrderStatus::Shipped])->save();
        app(OrderStateMachine::class)->apply($order, OrderStatus::Returned, 'Staffer');

        Notification::assertSentOnDemand(
            OrderNotification::class,
            fn (OrderNotification $n) => $n->mailEvent() === OrderMailEvent::Returned,
        );
    }

    #[Test]
    public function an_admin_can_mark_a_paid_order_refunded_and_the_customer_is_emailed(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');
        $order->forceFill(['payment_status' => Order::PAYMENT_PAID])->save();

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.refund', $order))
            ->assertRedirect();

        $this->assertSame(Order::PAYMENT_REFUNDED, $order->fresh()->payment_status);

        Notification::assertSentOnDemand(
            OrderNotification::class,
            fn (OrderNotification $n) => $n->mailEvent() === OrderMailEvent::Refunded,
        );
    }

    #[Test]
    public function an_unpaid_order_cannot_be_refunded(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');

        $this->actingAs($this->admin())
            ->patch(route('admin.orders.refund', $order))
            ->assertSessionHasErrors('refund');

        $this->assertSame(Order::PAYMENT_UNPAID, $order->fresh()->payment_status);
        Notification::assertNotSentTo(
            Notification::route('mail', 'rahim@example.com'),
            OrderNotification::class,
            fn (OrderNotification $n) => $n->mailEvent() === OrderMailEvent::Refunded,
        );
    }

    #[Test]
    public function a_status_that_does_not_change_sends_nothing(): void
    {
        Notification::fake();
        $this->makeProduct('cinderella-gown', price: 400, stock: 5);
        $order = $this->place('cinderella-gown', email: 'rahim@example.com');

        app(OrderNotifier::class)->statusChanged($order, OrderStatus::Shipped, OrderStatus::Shipped);

        // Only the placed email from checkout — no extra send from the no-op move.
        Notification::assertSentOnDemandTimes(OrderNotification::class, 1);
    }

    /**
     * Place an order through the real checkout service (fires the placed seam).
     */
    private function place(string $slug, ?string $email): Order
    {
        return app(CheckoutService::class)->place(
            [['slug' => $slug, 'size' => null, 'quantity' => 1]],
            [
                'name' => 'Rahim Uddin',
                'phone' => '01712345678',
                'email' => $email,
                'address' => 'House 1, Road 2, Gulshan',
                'country' => 'BD',
                'district' => 'Dhaka',
            ],
            PaymentMethod::CashOnDelivery,
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function makeProduct(string $handle, int $price, int $stock): Product
    {
        $product = Product::create([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);

        $product->variants()->create([
            'option_key' => ProductVariant::makeOptionKey(null, 'Default Title', null, null),
            'option1' => 'Default Title',
            'price' => $price,
            'inventory_quantity' => $stock,
        ]);

        return $product->syncVariantAggregates()->load('variants');
    }
}
