<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Checkout Sessions (hosted redirect).
 *
 * Flow: create a Checkout Session -> redirect to session.url -> customer returns
 * to our callback -> retrieve the session and confirm payment_status === 'paid'.
 * A checkout.session.completed webhook settles the payment even if the customer
 * never makes it back.
 */
class StripeGateway implements HandlesWebhooks, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function initiate(Payment $payment): string
    {
        $callback = route('payments.callback', ['payment' => $payment->id]);

        $response = $this->request('post', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            'success_url' => $callback.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $callback.'?status=cancel',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'unit_amount' => $this->minorUnits($payment),
                    'product_data' => ['name' => 'Order '.$payment->reference],
                ],
            ]],
            'payment_intent_data' => [
                'metadata' => ['payment_reference' => $payment->reference],
            ],
            'metadata' => ['payment_reference' => $payment->reference],
        ]);

        if (empty($response['id']) || empty($response['url'])) {
            throw PaymentException::fromGateway($this->name(), 'Unable to create a checkout session.', [
                'error' => $response['error']['message'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $response['id'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $response['url'];
    }

    public function finalize(Payment $payment, array $callback): PaymentResult
    {
        if (($callback['status'] ?? null) === 'cancel') {
            return PaymentResult::failure('Payment was cancelled.', $callback);
        }

        return $this->verify($payment);
    }

    public function verify(Payment $payment): PaymentResult
    {
        if (! $payment->gateway_payment_id) {
            return PaymentResult::failure('Payment was never registered with Stripe.');
        }

        $session = $this->request('get', "/v1/checkout/sessions/{$payment->gateway_payment_id}");

        if (($session['payment_status'] ?? null) === 'paid') {
            return PaymentResult::success(
                is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : ($session['id'] ?? null),
                $session
            );
        }

        return PaymentResult::failure('Payment was not completed.', $session);
    }

    public function handleWebhook(Request $request): ?Payment
    {
        $this->assertSignature($request);

        $event = $request->json()->all();

        if (($event['type'] ?? null) !== 'checkout.session.completed') {
            return null;
        }

        $reference = $event['data']['object']['client_reference_id']
            ?? $event['data']['object']['metadata']['payment_reference']
            ?? null;

        return $reference ? Payment::where('reference', $reference)->first() : null;
    }

    /**
     * Stripe signs the raw body as "{timestamp}.{payload}" with the webhook
     * secret; a mismatch (or a >5-minute-old timestamp) is rejected.
     */
    private function assertSignature(Request $request): void
    {
        $secret = $this->config['webhook_secret'] ?? null;
        $header = $request->header('Stripe-Signature');

        if (! $secret || ! $header) {
            throw PaymentException::fromGateway($this->name(), 'Missing webhook signature.');
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, null);
            $parts[$k][] = $v;
        }

        $timestamp = $parts['t'][0] ?? null;
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        $matched = collect($parts['v1'] ?? [])->contains(fn ($sig) => hash_equals($expected, (string) $sig));

        if (! $timestamp || ! $matched || abs(now()->timestamp - (int) $timestamp) > 300) {
            throw PaymentException::fromGateway($this->name(), 'Invalid webhook signature.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $request = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->withToken($this->config['secret_key']);

        $response = $method === 'get'
            ? $request->get($this->config['base_url'].$path, $payload)
            : $request->post($this->config['base_url'].$path, $payload);

        if ($response->failed()) {
            Log::error('Stripe request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * AED (and most currencies) are charged in the 2-decimal minor unit.
     */
    private function minorUnits(Payment $payment): int
    {
        return (int) round(((float) $payment->amount) * 100);
    }
}
