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
 * Tap Payments — Charges API.
 *
 * Redirect flow: create a charge with source 'src_all' -> redirect to
 * transaction.url -> customer returns to our callback (?tap_id=…) -> GET the
 * charge and confirm status === 'CAPTURED'. Tap also POSTs to our webhook.
 *
 * Embedded flow: the Tap Card SDK collects and tokenises the card on-page; the
 * resulting token is charged server-side, which may still require a 3-D Secure
 * redirect.
 */
class TapGateway implements HandlesWebhooks, PaymentGateway, SupportsEmbeddedCard
{
    /** Currencies Tap settles in three decimal places rather than two. */
    private const THREE_DECIMAL = ['KWD', 'BHD', 'OMR'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'tap';
    }

    public function initiate(Payment $payment): string
    {
        $response = $this->request('post', '/v2/charges', [
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'threeDSecure' => true,
            'customer' => $this->customer($payment),
            'source' => ['id' => 'src_all'],
            'reference' => [
                'transaction' => $payment->reference,
                'order' => $payment->order?->order_number ?? $payment->reference,
            ],
            'redirect' => ['url' => route('payments.callback', ['payment' => $payment->id])],
            'post' => ['url' => route('payments.webhook', ['gateway' => 'tap'])],
            'metadata' => ['payment_reference' => $payment->reference],
        ]);

        $url = $response['transaction']['url'] ?? null;

        if (empty($response['id']) || ! $url) {
            throw PaymentException::fromGateway($this->name(), 'Unable to create a charge.', [
                'errors' => $response['errors'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $response['id'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $url;
    }

    public function prepareEmbedded(Payment $payment): array
    {
        $payment->forceFill(['status' => Payment::STATUS_INITIATED])->save();

        return array_filter([
            'provider' => 'tap',
            'publishable_key' => $this->config['publishable_key'],
            'merchant_id' => $this->config['merchant_id'] ?? null,
        ]);
    }

    public function finalize(Payment $payment, array $callback): PaymentResult
    {
        return $this->verify($payment);
    }

    public function verify(Payment $payment): PaymentResult
    {
        if (! $payment->gateway_payment_id) {
            return PaymentResult::failure('Payment was never registered with Tap.');
        }

        return $this->interpret($this->request('get', "/v2/charges/{$payment->gateway_payment_id}"));
    }

    public function handleWebhook(Request $request): ?Payment
    {
        $charge = $request->json()->all();

        $reference = $charge['reference']['transaction'] ?? $charge['metadata']['payment_reference'] ?? null;
        $payment = $reference ? Payment::where('reference', $reference)->first() : null;

        if ($payment) {
            $this->assertHashstring($request, $charge);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function interpret(array $charge): PaymentResult
    {
        if (($charge['status'] ?? null) === 'CAPTURED') {
            return PaymentResult::success($charge['id'] ?? null, $charge);
        }

        return PaymentResult::failure(
            $charge['response']['message'] ?? 'Payment was not completed.',
            $charge
        );
    }

    /**
     * Tap sends a `hashstring` header computed over fixed charge fields with the
     * secret key. We verify it when present; the authoritative GET in verify()
     * is what ultimately decides the outcome.
     *
     * @param  array<string, mixed>  $charge
     */
    private function assertHashstring(Request $request, array $charge): void
    {
        $header = $request->header('hashstring');
        $secret = $this->config['webhook_secret'] ?: $this->config['secret_key'];

        if (! $header || ! $secret) {
            return;
        }

        $decimals = in_array(strtoupper($charge['currency'] ?? ''), self::THREE_DECIMAL, true) ? 3 : 2;

        $toHash = 'x_id'.($charge['id'] ?? '')
            .'x_amount'.number_format((float) ($charge['amount'] ?? 0), $decimals, '.', '')
            .'x_currency'.($charge['currency'] ?? '')
            .'x_gateway_reference'.($charge['reference']['gateway'] ?? '')
            .'x_payment_reference'.($charge['reference']['payment'] ?? '')
            .'x_status'.($charge['status'] ?? '')
            .'x_created'.($charge['transaction']['created'] ?? '');

        if (! hash_equals(hash_hmac('sha256', $toHash, $secret), (string) $header)) {
            throw PaymentException::fromGateway($this->name(), 'Invalid webhook signature.');
        }
    }

    /**
     * Tap requires a customer with a name and a contact. Prefer the order's
     * buyer; fall back to what little a standalone payment carries.
     *
     * @return array<string, mixed>
     */
    private function customer(Payment $payment): array
    {
        $order = $payment->order;
        $name = $order?->customer_name ?? 'Guest Customer';
        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, '');

        return array_filter([
            'first_name' => $first ?: 'Guest',
            'last_name' => $last,
            'email' => $order?->customer_email,
            'phone' => ($phone = $order?->customer_phone ?? $payment->payer_reference)
                ? ['country_code' => '971', 'number' => preg_replace('/\D/', '', $phone)]
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $request = Http::asJson()
            ->acceptJson()
            ->timeout(30)
            ->withToken($this->config['secret_key']);

        $response = $method === 'get'
            ? $request->get($this->config['base_url'].$path)
            : $request->post($this->config['base_url'].$path, $payload);

        if ($response->failed()) {
            Log::error('Tap request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
            ]);
        }

        return $response->json() ?? [];
    }
}
