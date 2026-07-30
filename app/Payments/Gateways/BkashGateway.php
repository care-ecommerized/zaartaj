<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * bKash Tokenized Checkout (v1.2.0).
 *
 * Flow: grant token -> create payment -> redirect to bkashURL -> customer
 * returns to our callback -> execute payment.
 */
class BkashGateway implements PaymentGateway
{
    private const SUCCESS_CODE = '0000';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'bkash';
    }

    public function initiate(Payment $payment): string
    {
        $response = $this->request('/tokenized/checkout/create', [
            'mode' => '0011',
            'intent' => 'sale',
            'currency' => $payment->currency,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'merchantInvoiceNumber' => $payment->reference,
            'payerReference' => $payment->payer_reference ?: $payment->reference,
            'callbackURL' => route('payments.callback', ['payment' => $payment->id]),
        ]);

        if (($response['statusCode'] ?? null) !== self::SUCCESS_CODE || empty($response['bkashURL'])) {
            throw PaymentException::fromGateway($this->name(), 'Unable to create payment.', [
                'statusCode' => $response['statusCode'] ?? null,
                'statusMessage' => $response['statusMessage'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $response['paymentID'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $response['bkashURL'];
    }

    public function finalize(Payment $payment, array $callback): PaymentResult
    {
        $status = $callback['status'] ?? null;

        if ($status !== 'success') {
            return PaymentResult::failure(
                $status === 'cancel' ? 'Payment was cancelled.' : 'Payment failed at bKash.',
                $callback
            );
        }

        // The browser can be tampered with, so the execute call is what actually
        // decides the outcome — not the status parameter above.
        $response = $this->request('/tokenized/checkout/execute', [
            'paymentID' => $callback['paymentID'] ?? $payment->gateway_payment_id,
        ]);

        // An already-executed payment errors out; fall back to the status query
        // so a refreshed callback still resolves correctly.
        if (($response['statusCode'] ?? null) !== self::SUCCESS_CODE) {
            return $this->verify($payment);
        }

        return $this->interpret($response);
    }

    public function verify(Payment $payment): PaymentResult
    {
        if (! $payment->gateway_payment_id) {
            return PaymentResult::failure('Payment was never registered with bKash.');
        }

        return $this->interpret($this->request('/tokenized/checkout/payment/status', [
            'paymentID' => $payment->gateway_payment_id,
        ]));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function interpret(array $response): PaymentResult
    {
        if (($response['transactionStatus'] ?? null) === 'Completed') {
            return PaymentResult::success($response['trxID'] ?? null, $response);
        }

        return PaymentResult::failure(
            $response['statusMessage'] ?? $response['errorMessage'] ?? 'Payment was not completed.',
            $response
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $path, array $payload): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->timeout(30)
            ->withHeaders([
                'Authorization' => $this->token(),
                'X-APP-Key' => $this->config['app_key'],
            ])
            ->post($this->config['base_url'].$path, $payload);

        if ($response->failed()) {
            Log::error('bKash request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * bKash id_tokens live for an hour; cache just under that.
     */
    private function token(): string
    {
        return Cache::remember('bkash:id_token', now()->addMinutes(50), function (): string {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout(30)
                ->withHeaders([
                    'username' => $this->config['username'],
                    'password' => $this->config['password'],
                ])
                ->post($this->config['base_url'].'/tokenized/checkout/token/grant', [
                    'app_key' => $this->config['app_key'],
                    'app_secret' => $this->config['app_secret'],
                ]);

            $token = $response->json('id_token');

            if (! $response->successful() || ! $token) {
                throw PaymentException::fromGateway($this->name(), 'Could not grant a token.', [
                    'http_status' => $response->status(),
                    'statusMessage' => $response->json('statusMessage'),
                ]);
            }

            return $token;
        });
    }
}
