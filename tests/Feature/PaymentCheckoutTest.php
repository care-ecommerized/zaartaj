<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // bKash settles in BDT, so initiating a payment converts out of the AED
        // base — which needs the currencies table populated.
        $this->seed(CurrencySeeder::class);

        config()->set('payment.gateways.bkash', [
            'driver' => 'bkash',
            'base_url' => 'https://bkash.test',
            'app_key' => 'key',
            'app_secret' => 'secret',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function test_checkout_registers_the_payment_and_redirects_to_the_gateway(): void
    {
        Http::fake([
            'bkash.test/*/token/grant' => Http::response(['id_token' => 'tok', 'expires_in' => 3600]),
            'bkash.test/*/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'TR001',
                'bkashURL' => 'https://bkash.test/checkout/TR001',
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withHeader('X-Inertia', 'true')
            ->post(route('payments.store'), ['gateway' => 'bkash', 'amount' => '250.00']);

        // Inertia signals an external redirect with 409 + X-Inertia-Location.
        $response->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://bkash.test/checkout/TR001');

        $this->assertDatabaseHas('payments', [
            'gateway' => 'bkash',
            'gateway_payment_id' => 'TR001',
            'status' => Payment::STATUS_INITIATED,
        ]);
    }

    public function test_successful_callback_executes_the_payment_and_marks_it_paid(): void
    {
        Http::fake([
            'bkash.test/*/token/grant' => Http::response(['id_token' => 'tok']),
            'bkash.test/*/execute' => Http::response([
                'statusCode' => '0000',
                'transactionStatus' => 'Completed',
                'trxID' => 'ABC123',
            ]),
        ]);

        $payment = $this->pendingPayment();

        $this->get(route('payments.callback', $payment).'?status=success&paymentID=TR001')
            ->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertSame('ABC123', $payment->gateway_transaction_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_cancelled_callback_fails_the_payment_without_calling_execute(): void
    {
        Http::fake();

        $payment = $this->pendingPayment();

        $this->get(route('payments.callback', $payment).'?status=cancel&paymentID=TR001');

        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_settled_payment_is_not_settled_twice(): void
    {
        Http::fake();

        $payment = $this->pendingPayment();
        $payment->forceFill([
            'status' => Payment::STATUS_PAID,
            'gateway_transaction_id' => 'ORIGINAL',
        ])->save();

        $this->get(route('payments.callback', $payment).'?status=success&paymentID=TR001');

        $this->assertSame('ORIGINAL', $payment->refresh()->gateway_transaction_id);
        Http::assertNothingSent();
    }

    public function test_an_unknown_gateway_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('payments.store'), ['gateway' => 'paypal', 'amount' => '10'])
            ->assertSessionHasErrors('gateway');
    }

    public function test_bnpl_gateways_are_not_offered_on_the_standalone_page(): void
    {
        // Tabby/Tamara need order context, so the standalone page must reject them.
        $this->actingAs(User::factory()->create())
            ->post(route('payments.store'), ['gateway' => 'tabby', 'amount' => '100'])
            ->assertSessionHasErrors('gateway');
    }

    private function pendingPayment(): Payment
    {
        return Payment::create([
            'reference' => 'ZT-TEST-1',
            'gateway' => 'bkash',
            'amount' => '250.00',
            'currency' => 'BDT',
            'status' => Payment::STATUS_INITIATED,
            'gateway_payment_id' => 'TR001',
        ]);
    }
}
