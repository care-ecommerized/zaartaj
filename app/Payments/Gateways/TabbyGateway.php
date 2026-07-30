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
 * Tabby — Buy Now, Pay Later (hosted checkout).
 *
 * Flow: create a checkout session with buyer + order + shipping -> if Tabby
 * approves it returns an installments web_url -> redirect -> customer returns to
 * our callback -> the payment is AUTHORIZED, which we capture to collect. Tabby
 * needs full order context, so this gateway is offered only in the storefront
 * checkout (config 'requires_order' => true).
 */
class TabbyGateway implements HandlesWebhooks, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'tabby';
    }

    public function initiate(Payment $payment): string
    {
        $order = $payment->order;

        if (! $order) {
            throw PaymentException::fromGateway($this->name(), 'Tabby requires an order.');
        }

        $order->loadMissing('items');
        $callback = route('payments.callback', ['payment' => $payment->id]);

        // Line prices are stored in the base currency; scale them into the
        // charged currency by the same factor the grand total used.
        $factor = $order->total > 0 ? ((float) $payment->amount) / (float) $order->total : 1.0;

        $response = $this->request('post', '/api/v2/checkout', [
            'lang' => 'en',
            'merchant_code' => $this->config['merchant_code'],
            'merchant_urls' => [
                'success' => $callback.'?status=success',
                'cancel' => $callback.'?status=cancel',
                'failure' => $callback.'?status=failure',
            ],
            'payment' => [
                'amount' => $this->money($payment->amount),
                'currency' => $payment->currency,
                'description' => 'Order '.$order->order_number,
                'buyer' => [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email ?: $this->fallbackEmail($order->customer_phone),
                    'phone' => $order->customer_phone,
                ],
                'shipping_address' => [
                    'city' => $order->customer_district,
                    'address' => $order->customer_address,
                    'zip' => '00000',
                ],
                'order' => [
                    'reference_id' => $order->order_number,
                    'items' => $order->items->map(fn ($item) => [
                        'title' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $this->money(((float) $item->unit_price) * $factor),
                        'reference_id' => $item->sku ?? (string) $item->id,
                        'category' => 'Fashion',
                    ])->all(),
                ],
            ],
        ]);

        $webUrl = $response['configuration']['available_products']['installments'][0]['web_url'] ?? null;

        if (($response['status'] ?? null) !== 'created' || empty($response['payment']['id']) || ! $webUrl) {
            throw PaymentException::fromGateway($this->name(), 'Tabby did not approve this checkout.', [
                'status' => $response['status'] ?? null,
                'rejection' => $response['configuration']['products']['installments']['rejection_reason'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $response['payment']['id'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $webUrl;
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
            return PaymentResult::failure('Payment was never registered with Tabby.');
        }

        $data = $this->request('get', "/api/v2/payments/{$payment->gateway_payment_id}");
        $status = $data['status'] ?? null;

        // Already captured on a previous pass.
        if ($status === 'CLOSED') {
            return PaymentResult::success($this->capturedId($data) ?? $payment->gateway_payment_id, $data);
        }

        // Approved but not yet collected — capture the full amount now.
        if ($status === 'AUTHORIZED') {
            $captured = $this->request('post', "/api/v2/payments/{$payment->gateway_payment_id}/captures", [
                'amount' => $this->money($payment->amount),
            ]);

            if (($captured['status'] ?? null) === 'CLOSED') {
                return PaymentResult::success($this->capturedId($captured) ?? $payment->gateway_payment_id, $captured);
            }

            return PaymentResult::failure('Tabby capture did not complete.', $captured);
        }

        return PaymentResult::failure('Payment was not authorized.', $data);
    }

    public function handleWebhook(Request $request): ?Payment
    {
        $body = $request->json()->all();

        // Tabby lets you set a secret header when registering the webhook.
        $expected = $this->config['secret_key'];
        $given = $request->header('X-Webhook-Secret');

        if ($expected && $given && ! hash_equals($expected, $given)) {
            throw PaymentException::fromGateway($this->name(), 'Invalid webhook signature.');
        }

        $id = $body['id'] ?? null;

        return $id ? Payment::where('gateway_payment_id', $id)->first() : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function capturedId(array $data): ?string
    {
        return $data['captures'][0]['id'] ?? $data['id'] ?? null;
    }

    private function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Tabby requires a buyer email; synthesize a deterministic one when the
     * customer did not supply theirs.
     */
    private function fallbackEmail(?string $phone): string
    {
        return 'guest'.preg_replace('/\D/', '', (string) $phone).'@zaartaj.example';
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
            Log::error('Tabby request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
            ]);
        }

        return $response->json() ?? [];
    }
}
