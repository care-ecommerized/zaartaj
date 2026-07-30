<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentInitiator;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AedGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Base is now AED and these gateways settle in AED, so initiation is a
        // straight no-op conversion. The currencies table still backs the money
        // authority (even for the no-op rounding), so seed it.
        $this->seed(CurrencySeeder::class);
        config()->set('payment.currency', 'AED');

        config()->set('payment.gateways.stripe', [
            'driver' => 'stripe', 'currency' => 'AED', 'base_url' => 'https://stripe.test',
            'secret_key' => 'sk_test', 'publishable_key' => 'pk_test', 'webhook_secret' => 'whsec_test',
        ]);
        config()->set('payment.gateways.tap', [
            'driver' => 'tap', 'currency' => 'AED', 'base_url' => 'https://tap.test',
            'secret_key' => 'sk_tap', 'publishable_key' => 'pk_tap', 'webhook_secret' => '',
        ]);
        config()->set('payment.gateways.tabby', [
            'driver' => 'tabby', 'currency' => 'AED', 'requires_order' => true, 'base_url' => 'https://tabby.test',
            'public_key' => 'pk', 'secret_key' => 'sk_tabby', 'merchant_code' => 'mc',
        ]);
        config()->set('payment.gateways.tamara', [
            'driver' => 'tamara', 'currency' => 'AED', 'requires_order' => true, 'base_url' => 'https://tamara.test',
            'api_token' => 'tok', 'notification_token' => 'nt', 'public_key' => 'pk', 'country' => 'AE',
        ]);
    }

    private function initiator(): PaymentInitiator
    {
        return app(PaymentInitiator::class);
    }

    private function order(): Order
    {
        $order = Order::create([
            'order_number' => Order::generateNumber(),
            'status' => OrderStatus::Pending->value,
            'customer_name' => 'Aisha Khan',
            'customer_phone' => '01712345678',
            'customer_email' => 'aisha@example.com',
            'customer_address' => '12 Marina Walk',
            'customer_district' => 'Dhaka',
            'subtotal' => 100, 'shipping_total' => 0, 'discount_total' => 0,
            'total' => 100, 'cod_amount' => 0,
            'payment_method' => 'stripe', 'payment_status' => Order::PAYMENT_UNPAID,
        ]);

        $order->items()->create([
            'name' => 'Silk Abaya', 'sku' => 'AB-1', 'unit_price' => 50, 'quantity' => 2, 'line_total' => 100,
        ]);

        return $order;
    }

    public function test_stripe_converts_the_base_total_into_aed_and_redirects(): void
    {
        Http::fake([
            'stripe.test/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://stripe.test/pay/cs_1']),
        ]);

        $url = $this->initiator()->startStandalone(100.0, 'stripe', null);

        $this->assertSame('https://stripe.test/pay/cs_1', $url);

        $payment = Payment::firstOrFail();
        $this->assertSame('AED', $payment->currency);
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertSame('cs_1', $payment->gateway_payment_id);
        // Base is AED and Stripe settles in AED, so the charge equals the total.
        $this->assertEquals(100, $payment->meta['base_amount']);
        $this->assertEquals(1, $payment->meta['rate']);

        // Stripe charges the 2-decimal minor unit: 100.00 AED -> 10000 fils.
        Http::assertSent(fn ($request) => $request->url() === 'https://stripe.test/v1/checkout/sessions'
            && $request['line_items'][0]['price_data']['unit_amount'] === 10000
            && $request['line_items'][0]['price_data']['currency'] === 'aed');
    }

    public function test_stripe_callback_marks_the_payment_paid(): void
    {
        Http::fake([
            'stripe.test/v1/checkout/sessions/cs_1' => Http::response([
                'id' => 'cs_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1',
            ]),
        ]);

        $payment = $this->aedPayment('stripe', 'cs_1');

        $this->get(route('payments.callback', $payment).'?session_id=cs_1');

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertSame('pi_1', $payment->gateway_transaction_id);
    }

    public function test_stripe_webhook_rejects_a_bad_signature(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => 'X']]]);

        $this->call('POST', route('payments.webhook', ['gateway' => 'stripe']), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef', 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);
    }

    public function test_stripe_webhook_settles_with_a_valid_signature(): void
    {
        Http::fake([
            'stripe.test/v1/checkout/sessions/cs_9' => Http::response([
                'id' => 'cs_9', 'payment_status' => 'paid', 'payment_intent' => 'pi_9',
            ]),
        ]);

        $payment = $this->aedPayment('stripe', 'cs_9');

        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => ['client_reference_id' => $payment->reference]]];
        $payload = json_encode($event);
        $t = now()->timestamp;
        $sig = hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');

        $this->call('POST', route('payments.webhook', ['gateway' => 'stripe']), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertTrue($payment->refresh()->isPaid());
    }

    public function test_tap_initiates_and_a_webhook_settles(): void
    {
        Http::fake([
            'tap.test/v2/charges' => Http::response(['id' => 'chg_1', 'transaction' => ['url' => 'https://tap.test/pay/chg_1']]),
            'tap.test/v2/charges/chg_1' => Http::response(['id' => 'chg_1', 'status' => 'CAPTURED']),
        ]);

        $url = $this->initiator()->startStandalone(100.0, 'tap', null);
        $this->assertSame('https://tap.test/pay/chg_1', $url);

        $payment = Payment::firstOrFail();
        $this->assertSame('100.00', $payment->amount);

        // Tap POSTs the charge to our webhook (no hashstring header in this test).
        $this->postJson(route('payments.webhook', ['gateway' => 'tap']), [
            'id' => 'chg_1', 'reference' => ['transaction' => $payment->reference],
        ])->assertOk();

        $this->assertTrue($payment->refresh()->isPaid());
    }

    public function test_tabby_uses_order_context_authorizes_and_captures(): void
    {
        $order = $this->order();

        Http::fake([
            'tabby.test/api/v2/checkout' => Http::response([
                'status' => 'created',
                'payment' => ['id' => 'pay_1'],
                'configuration' => ['available_products' => ['installments' => [['web_url' => 'https://tabby.test/pay/pay_1']]]],
            ]),
            'tabby.test/api/v2/payments/pay_1' => Http::response(['id' => 'pay_1', 'status' => 'AUTHORIZED']),
            'tabby.test/api/v2/payments/pay_1/captures' => Http::response(['status' => 'CLOSED', 'captures' => [['id' => 'cap_1']]]),
        ]);

        $url = $this->initiator()->startForOrder($order, 'tabby', null);
        $this->assertSame('https://tabby.test/pay/pay_1', $url);

        $payment = Payment::firstOrFail();
        $this->assertSame('AED', $payment->currency);
        $this->assertSame('100.00', $payment->amount);

        $this->get(route('payments.callback', $payment).'?status=success');

        $this->assertTrue($payment->refresh()->isPaid());
        // Paid online order flips to paid + confirmed.
        $this->assertSame(Order::PAYMENT_PAID, $order->refresh()->payment_status);
    }

    public function test_tamara_uses_order_context_and_authorizes(): void
    {
        $order = $this->order();

        Http::fake([
            'tamara.test/checkout' => Http::response(['order_id' => 'tam_1', 'checkout_url' => 'https://tamara.test/pay/tam_1']),
            'tamara.test/orders/tam_1' => Http::response(['status' => 'approved']),
            'tamara.test/orders/tam_1/authorise' => Http::response(['status' => 'authorised']),
        ]);

        $url = $this->initiator()->startForOrder($order, 'tamara', null);
        $this->assertSame('https://tamara.test/pay/tam_1', $url);

        $payment = Payment::firstOrFail();
        $this->assertSame('100.00', $payment->amount);

        $this->get(route('payments.callback', $payment).'?status=success');

        $this->assertTrue($payment->refresh()->isPaid());
        $this->assertSame(Order::PAYMENT_PAID, $order->refresh()->payment_status);
    }

    private function aedPayment(string $gateway, string $gatewayPaymentId): Payment
    {
        return Payment::create([
            'reference' => 'ZT-'.strtoupper($gateway).'-1',
            'gateway' => $gateway,
            'amount' => '100.00',
            'currency' => 'AED',
            'status' => Payment::STATUS_INITIATED,
            'gateway_payment_id' => $gatewayPaymentId,
        ]);
    }
}
