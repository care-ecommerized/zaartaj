<?php

namespace Tests\Feature;

use App\Checkout\CheckoutSessionService;
use App\Enums\ProductStatus;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\AbandonedCheckoutReminder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ShippingZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AbandonedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ---- capture -----------------------------------------------------------

    #[Test]
    public function capture_upserts_by_token_and_sets_activity_and_best_effort_subtotal(): void
    {
        $this->makeProduct('capture-gown', price: 1200, stock: 5);
        $token = (string) Str::uuid();
        $service = app(CheckoutSessionService::class);

        $service->capture($token, [
            'email' => 'shopper@example.com',
            'phone' => '01712345678',
            'currency' => 'AED',
            'locale' => 'en',
            'cart' => [['slug' => 'capture-gown', 'size' => null, 'quantity' => 2]],
        ]);

        $session = CheckoutSession::where('token', $token)->firstOrFail();
        $this->assertSame('shopper@example.com', $session->email);
        $this->assertSame(CheckoutSession::STATUS_OPEN, $session->status);
        $this->assertNotNull($session->last_activity_at);
        // 2 × 1200, priced from the catalogue, not the client.
        $this->assertEquals(2400, $session->subtotal);

        // A second capture on the same token updates the one row, never duplicates.
        $service->capture($token, [
            'email' => 'changed@example.com',
            'cart' => [['slug' => 'capture-gown', 'size' => null, 'quantity' => 1]],
        ]);

        $this->assertSame(1, CheckoutSession::where('token', $token)->count());
        $session->refresh();
        $this->assertSame('changed@example.com', $session->email);
        $this->assertEquals(1200, $session->subtotal);
    }

    #[Test]
    public function a_stale_slug_does_not_break_capture(): void
    {
        $token = (string) Str::uuid();

        app(CheckoutSessionService::class)->capture($token, [
            'email' => 'shopper@example.com',
            'cart' => [['slug' => 'ghost-item', 'size' => null, 'quantity' => 1]],
        ]);

        $session = CheckoutSession::where('token', $token)->firstOrFail();
        // Unpriceable bag: subtotal is left null, the row still captured.
        $this->assertNull($session->subtotal);
        $this->assertSame('shopper@example.com', $session->email);
    }

    #[Test]
    public function the_capture_endpoint_creates_a_session_and_sets_a_token_cookie(): void
    {
        $this->makeProduct('endpoint-gown', price: 500, stock: 5);

        $response = $this->postJson('/checkout/session', [
            'email' => 'guest@example.com',
            'items' => [['slug' => 'endpoint-gown', 'size' => null, 'quantity' => 1]],
        ]);

        $response->assertNoContent();
        $response->assertCookie('checkout_token');

        $session = CheckoutSession::firstOrFail();
        $this->assertSame('guest@example.com', $session->email);
        $this->assertSame(CheckoutSession::STATUS_OPEN, $session->status);
    }

    // ---- convert-on-place --------------------------------------------------

    #[Test]
    public function placing_an_order_marks_the_captured_session_converted(): void
    {
        $this->seed(CurrencySeeder::class);
        $this->seed(ShippingZoneSeeder::class);
        $this->makeProduct('convert-gown', price: 45000, stock: 5);

        $token = (string) Str::uuid();
        app(CheckoutSessionService::class)->capture($token, [
            'email' => 'rahim@example.com',
            'cart' => [['slug' => 'convert-gown', 'size' => null, 'quantity' => 1]],
        ]);

        $this->withUnencryptedCookie('checkout_token', $token)
            ->post('/checkout', $this->payload('convert-gown', 1, 'Dhaka'));

        $order = Order::firstOrFail();
        $session = CheckoutSession::where('token', $token)->firstOrFail();

        $this->assertSame(CheckoutSession::STATUS_CONVERTED, $session->status);
        $this->assertSame($order->id, $session->recovered_order_id);
    }

    // ---- sweep -------------------------------------------------------------

    #[Test]
    public function the_sweep_reminds_stale_sessions_with_an_email_and_respects_the_rules(): void
    {
        Notification::fake();
        config(['checkout.abandon_after_hours' => 4]);

        // Stale + email → reminded and abandoned.
        $stale = CheckoutSession::factory()->create([
            'email' => 'stale@example.com',
            'last_activity_at' => now()->subHours(6),
            'reminder_sent_at' => null,
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        // Stale but no email → skipped.
        $noEmail = CheckoutSession::factory()->create([
            'email' => null,
            'last_activity_at' => now()->subHours(6),
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        // Already reminded → skipped.
        $reminded = CheckoutSession::factory()->create([
            'email' => 'reminded@example.com',
            'last_activity_at' => now()->subHours(6),
            'reminder_sent_at' => now()->subHour(),
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        // Fresh (inside the window) → skipped.
        $fresh = CheckoutSession::factory()->create([
            'email' => 'fresh@example.com',
            'last_activity_at' => now()->subMinutes(30),
            'status' => CheckoutSession::STATUS_OPEN,
        ]);

        $this->artisan('checkouts:sweep')->assertSuccessful();

        Notification::assertSentOnDemandTimes(AbandonedCheckoutReminder::class, 1);
        Notification::assertSentOnDemand(
            AbandonedCheckoutReminder::class,
            fn ($notification, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === 'stale@example.com',
        );

        $this->assertSame(CheckoutSession::STATUS_ABANDONED, $stale->fresh()->status);
        $this->assertNotNull($stale->fresh()->reminder_sent_at);

        // The skipped ones are untouched.
        $this->assertSame(CheckoutSession::STATUS_OPEN, $noEmail->fresh()->status);
        $this->assertSame(CheckoutSession::STATUS_OPEN, $reminded->fresh()->status);
        $this->assertSame(CheckoutSession::STATUS_OPEN, $fresh->fresh()->status);
    }

    // ---- resume ------------------------------------------------------------

    #[Test]
    public function resume_returns_the_cart_repriced_from_the_database_ignoring_unavailable_slugs(): void
    {
        $this->makeProduct('resume-gown', price: 999, stock: 5);

        $token = (string) Str::uuid();
        CheckoutSession::factory()->create([
            'token' => $token,
            'status' => CheckoutSession::STATUS_ABANDONED,
            'cart' => [
                ['slug' => 'resume-gown', 'size' => null, 'quantity' => 2],
                ['slug' => 'ghost-gown', 'size' => null, 'quantity' => 1],
            ],
            // A deliberately wrong stored subtotal must never leak to the page.
            'subtotal' => 1,
        ]);

        $this->get('/checkout?resume='.$token)
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/checkout')
                ->has('resumeCart', 1)
                ->where('resumeCart.0.slug', 'resume-gown')
                ->where('resumeCart.0.quantity', 2)
                ->where('resumeCart.0.price', 999)
                ->where('resumeContact.email', fn ($email) => is_string($email)));
    }

    // ---- admin -------------------------------------------------------------

    #[Test]
    public function an_admin_can_list_abandoned_checkouts(): void
    {
        CheckoutSession::factory()->abandoned()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.abandoned.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/abandoned/index')
                ->has('sessions', 1)
                ->has('metrics'));
    }

    #[Test]
    public function a_non_admin_gets_a_404_on_the_abandoned_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.abandoned.index'))
            ->assertNotFound();
    }

    // ---- helpers -----------------------------------------------------------

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function makeProduct(string $handle, int $price, int $stock, ProductStatus $status = ProductStatus::Active): Product
    {
        $product = Product::create([
            'handle' => $handle,
            'title' => ucfirst(str_replace('-', ' ', $handle)),
            'status' => $status,
            'published_at' => $status === ProductStatus::Active ? now() : null,
        ]);

        $product->variants()->create([
            'option_key' => ProductVariant::makeOptionKey(null, 'Default Title', null, null),
            'option1' => 'Default Title',
            'price' => $price,
            'inventory_quantity' => $stock,
        ]);

        return $product->syncVariantAggregates()->load('variants');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $slug, int $quantity, string $district): array
    {
        return [
            'customer' => [
                'name' => 'Rahim Uddin',
                'phone' => '01712345678',
                'email' => 'rahim@example.com',
                'address' => 'House 1, Road 2, Gulshan',
                'country' => 'BD',
                'district' => $district,
                'note' => '',
            ],
            'payment_method' => 'cod',
            'items' => [
                ['slug' => $slug, 'size' => null, 'quantity' => $quantity],
            ],
        ];
    }
}
