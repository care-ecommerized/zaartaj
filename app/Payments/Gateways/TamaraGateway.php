<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\HandlesWebhooks;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tamara — Buy Now, Pay Later (hosted checkout).
 *
 * Flow: create a checkout session -> redirect to checkout_url -> customer
 * returns approved -> we authorise the order (funds guaranteed) and treat that
 * as paid; capture-on-shipment is left to fulfilment. Tamara validates that the
 * line items + shipping reconcile to the total, so the BDT order lines are
 * converted with the same rate the grand total used. Order-only (BNPL).
 */
class TamaraGateway implements HandlesWebhooks, PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'tamara';
    }

    public function initiate(Payment $payment): string
    {
        $order = $payment->order;

        if (! $order) {
            throw PaymentException::fromGateway($this->name(), 'Tamara requires an order.');
        }

        $order->loadMissing('items');
        $currency = $payment->currency;
        $callback = route('payments.callback', ['payment' => $payment->id]);

        // Scale each BDT line into the charged currency by the same factor the
        // grand total used, then let shipping absorb any rounding so the parts
        // sum back to exactly the amount we settle.
        $factor = $order->total > 0 ? ((float) $payment->amount) / (float) $order->total : 1.0;
        [$first, $last] = $this->splitName($order->customer_name);

        $items = $order->items->map(function ($item) use ($currency, $factor) {
            $line = round(((float) $item->line_total) * $factor, 2);
            $unit = round($line / max(1, $item->quantity), 2);

            return [
                'reference_id' => (string) $item->id,
                'type' => 'Physical',
                'name' => $item->name,
                'sku' => $item->sku ?: 'SKU-'.$item->id,
                'quantity' => $item->quantity,
                'unit_price' => $this->money($unit, $currency),
                'total_amount' => $this->money(round($unit * $item->quantity, 2), $currency),
            ];
        });

        $itemsTotal = $items->sum(fn ($i) => (float) $i['total_amount']['amount']);
        $shipping = max(0, round(((float) $payment->amount) - $itemsTotal, 2));

        $response = $this->request('post', '/checkout', [
            'order_reference_id' => $order->order_number,
            'total_amount' => $this->money($payment->amount, $currency),
            'description' => 'Order '.$order->order_number,
            'country_code' => $this->config['country'] ?? 'AE',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'locale' => 'en_US',
            'items' => $items->all(),
            'consumer' => [
                'first_name' => $first,
                'last_name' => $last,
                'phone_number' => $order->customer_phone,
                'email' => $order->customer_email ?: $this->fallbackEmail($order->customer_phone),
            ],
            'shipping_address' => [
                'first_name' => $first,
                'last_name' => $last,
                'line1' => $order->customer_address,
                'city' => $order->customer_district,
                'country_code' => $this->config['country'] ?? 'AE',
                'phone_number' => $order->customer_phone,
            ],
            'tax_amount' => $this->money(0, $currency),
            'shipping_amount' => $this->money($shipping, $currency),
            'merchant_url' => [
                'success' => $callback.'?status=success',
                'failure' => $callback.'?status=failure',
                'cancel' => $callback.'?status=cancel',
                'notification' => route('payments.webhook', ['gateway' => 'tamara']),
            ],
        ]);

        if (empty($response['order_id']) || empty($response['checkout_url'])) {
            throw PaymentException::fromGateway($this->name(), 'Tamara did not create a checkout.', [
                'message' => $response['message'] ?? null,
                'errors' => $response['errors'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $response['order_id'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $response['checkout_url'];
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
            return PaymentResult::failure('Payment was never registered with Tamara.');
        }

        $order = $this->request('get', "/orders/{$payment->gateway_payment_id}");
        $status = $order['status'] ?? null;

        // Approved by the customer but not yet authorised on our side.
        if ($status === 'approved') {
            $authorised = $this->request('post', "/orders/{$payment->gateway_payment_id}/authorise");
            $status = $authorised['status'] ?? null;
            $order = $authorised + $order;
        }

        if (in_array($status, ['authorised', 'fully_captured', 'partially_captured'], true)) {
            return PaymentResult::success($payment->gateway_payment_id, $order);
        }

        return PaymentResult::failure('Payment was not authorised.', $order);
    }

    public function handleWebhook(Request $request): ?Payment
    {
        $this->assertToken($request);

        $body = $request->json()->all();
        $orderId = $body['order_id'] ?? null;
        $reference = $body['order_reference_id'] ?? null;

        if ($orderId && $payment = Payment::where('gateway_payment_id', $orderId)->first()) {
            return $payment;
        }

        if ($reference && $order = Order::where('order_number', $reference)->first()) {
            return $order->payments()->latest()->first();
        }

        return null;
    }

    /**
     * Tamara signs notifications as an HS256 JWT in the `tamara-token` header,
     * keyed by the notification token.
     */
    private function assertToken(Request $request): void
    {
        $secret = $this->config['notification_token'] ?? null;
        $token = $request->header('tamara-token');

        if (! $secret || ! $token) {
            return;
        }

        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw PaymentException::fromGateway($this->name(), 'Malformed webhook token.');
        }

        $expected = $this->base64Url(hash_hmac('sha256', $segments[0].'.'.$segments[1], $secret, true));

        if (! hash_equals($expected, $segments[2])) {
            throw PaymentException::fromGateway($this->name(), 'Invalid webhook signature.');
        }
    }

    /**
     * @return array{amount: float, currency: string}
     */
    private function money(float|string $amount, string $currency): array
    {
        return ['amount' => round((float) $amount, 2), 'currency' => $currency];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        [$first, $last] = array_pad(explode(' ', trim($name), 2), 2, '');

        return [$first ?: 'Guest', $last ?: '-'];
    }

    private function fallbackEmail(?string $phone): string
    {
        return 'guest'.preg_replace('/\D/', '', (string) $phone).'@zaartaj.example';
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
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
            ->withToken($this->config['api_token']);

        $response = $method === 'get'
            ? $request->get($this->config['base_url'].$path)
            : $request->post($this->config['base_url'].$path, $payload);

        if ($response->failed()) {
            Log::error('Tamara request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
            ]);
        }

        return $response->json() ?? [];
    }
}
