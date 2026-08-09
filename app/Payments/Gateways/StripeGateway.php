<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\SupportsEmbeddedCard;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe — hosted Checkout Sessions (redirect) and embedded card via a
 * PaymentIntent (on-page, using Stripe Elements).
 *
 * Redirect flow: create a Checkout Session -> session.url -> callback ->
 * retrieve and confirm payment_status === 'paid'.
 * Embedded flow: create a PaymentIntent -> the browser confirms the card with
 * the client_secret -> we settle when it reads 'succeeded'.
 * Either way a webhook settles even if the customer never returns.
 */
class StripeGateway implements HandlesWebhooks, PaymentGateway, SupportsEmbeddedCard
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

    public function prepareEmbedded(Payment $payment): array
    {
        $intent = $this->request('post', '/v1/payment_intents', [
            'amount' => $this->minorUnits($payment),
            'currency' => strtolower($payment->currency),
            'description' => 'Order '.$payment->reference,
            'automatic_payment_methods' => ['enabled' => 'true', 'allow_redirects' => 'never'],
            'metadata' => ['payment_reference' => $payment->reference],
        ]);

        if (empty($intent['id']) || empty($intent['client_secret'])) {
            throw PaymentException::fromGateway($this->name(), 'Unable to create a payment intent.', [
                'error' => $intent['error']['message'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $intent['id'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return [
            'provider' => 'stripe',
            'client_secret' => $intent['client_secret'],
            'publishable_key' => $this->config['publishable_key'],
        ];
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

        // Embedded card payments are PaymentIntents (pi_…); hosted checkouts are
        // Sessions (cs_…). Confirm each against its own endpoint.
        if (str_starts_with($payment->gateway_payment_id, 'pi_')) {
            $intent = $this->request('get', "/v1/payment_intents/{$payment->gateway_payment_id}");

            if (($intent['status'] ?? null) === 'succeeded') {
                return PaymentResult::success($intent['id'], $intent);
            }

            return PaymentResult::failure('Card payment was not completed.', $intent);
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

        // Hosted checkout completes as a session; embedded card as a PaymentIntent.
        if (! in_array($event['type'] ?? null, ['checkout.session.completed', 'payment_intent.succeeded'], true)) {
            return null;
        }

        $object = $event['data']['object'] ?? [];
        $reference = $object['client_reference_id'] ?? $object['metadata']['payment_reference'] ?? null;

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
