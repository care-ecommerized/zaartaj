<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Nagad Payment Gateway (remote-payment-gateway-1.0, API v-0.2.0).
 *
 * Flow: initialize (gateway returns a challenge) -> complete (echo the
 * challenge back, get callBackUrl) -> redirect -> verify on return.
 *
 * Every request body is RSA-encrypted with Nagad's public key and signed with
 * the merchant private key; responses come back the same way.
 */
class NagadGateway implements PaymentGateway
{
    private const API_VERSION = 'v-0.2.0';

    /** ISO 4217 numeric code for BDT — Nagad rejects the alpha code. */
    private const CURRENCY_CODE = '050';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'nagad';
    }

    public function initiate(Payment $payment): string
    {
        $dateTime = now()->format('YmdHis');

        // The signature must cover exactly the bytes we encrypted, so build once.
        $sensitive = [
            'merchantId' => $this->config['merchant_id'],
            'datetime' => $dateTime,
            'orderId' => $payment->reference,
            'challenge' => Str::random(20),
        ];

        $initialize = $this->post(
            "/api/dfs/check-out/initialize/{$this->config['merchant_id']}/{$payment->reference}",
            [
                'dateTime' => $dateTime,
                'sensitiveData' => $this->encrypt($sensitive),
                'signature' => $this->sign($sensitive),
            ]
        );

        if (empty($initialize['sensitiveData'])) {
            throw PaymentException::fromGateway($this->name(), 'Initialization was rejected.', [
                'reason' => $initialize['reason'] ?? $initialize['message'] ?? null,
            ]);
        }

        $decoded = $this->decrypt($initialize['sensitiveData']);

        $complete = $this->post(
            "/api/dfs/check-out/complete/{$decoded['paymentReferenceId']}",
            [
                'sensitiveData' => $this->encrypt($order = [
                    'merchantId' => $this->config['merchant_id'],
                    'orderId' => $payment->reference,
                    'currencyCode' => self::CURRENCY_CODE,
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'challenge' => $decoded['challenge'],
                ]),
                'signature' => $this->sign($order),
                'merchantCallbackURL' => route('payments.callback', ['payment' => $payment->id]),
            ]
        );

        if (($complete['status'] ?? null) !== 'Success' || empty($complete['callBackUrl'])) {
            throw PaymentException::fromGateway($this->name(), 'Checkout could not be completed.', [
                'status' => $complete['status'] ?? null,
                'message' => $complete['message'] ?? null,
            ]);
        }

        $payment->forceFill([
            'gateway_payment_id' => $decoded['paymentReferenceId'],
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $complete['callBackUrl'];
    }

    public function finalize(Payment $payment, array $callback): PaymentResult
    {
        if (($callback['status'] ?? null) !== 'Success') {
            return PaymentResult::failure(
                $callback['message'] ?? 'Payment was not approved at Nagad.',
                $callback
            );
        }

        return $this->verify($payment);
    }

    public function verify(Payment $payment): PaymentResult
    {
        if (! $payment->gateway_payment_id) {
            return PaymentResult::failure('Payment was never registered with Nagad.');
        }

        $response = Http::acceptJson()
            ->timeout(30)
            ->withHeaders($this->headers())
            ->get($this->config['base_url']."/api/dfs/verify/payment/{$payment->gateway_payment_id}");

        $body = $response->json() ?? [];

        if (($body['status'] ?? null) !== 'Success') {
            return PaymentResult::failure($body['statusCode'] ?? 'Payment was not completed.', $body);
        }

        // A verified payment must match what we asked for; Nagad echoes both back.
        if ((string) ($body['orderId'] ?? '') !== (string) $payment->reference
            || round((float) ($body['amount'] ?? 0), 2) !== round((float) $payment->amount, 2)) {
            Log::warning('Nagad verification mismatch', ['payment_id' => $payment->id, 'body' => $body]);

            return PaymentResult::failure('Verified payment did not match the order.', $body);
        }

        return PaymentResult::success($body['issuerPaymentRefNo'] ?? $body['paymentRefId'] ?? null, $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->timeout(30)
            ->withHeaders($this->headers())
            ->post($this->config['base_url'].$path, $payload);

        if ($response->failed()) {
            Log::error('Nagad request failed', ['path' => $path, 'body' => $response->body()]);

            throw PaymentException::fromGateway($this->name(), "Request to {$path} failed.", [
                'http_status' => $response->status(),
            ]);
        }

        return $response->json() ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-KM-Api-Version' => self::API_VERSION,
            'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
            'X-KM-Client-Type' => 'PC_WEB',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encrypt(array $data): string
    {
        $key = $this->pem($this->config['nagad_public_key'], 'PUBLIC KEY');

        if (! openssl_public_encrypt(json_encode($data), $encrypted, $key)) {
            throw PaymentException::fromGateway($this->name(), 'Could not encrypt the request payload.');
        }

        return base64_encode($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    private function decrypt(string $payload): array
    {
        $key = $this->pem($this->config['merchant_private_key'], 'RSA PRIVATE KEY');

        if (! openssl_private_decrypt(base64_decode($payload), $decrypted, $key)) {
            throw PaymentException::fromGateway($this->name(), 'Could not decrypt the gateway response.');
        }

        return json_decode($decrypted, true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sign(array $data): string
    {
        $key = $this->pem($this->config['merchant_private_key'], 'RSA PRIVATE KEY');

        if (! openssl_sign(json_encode($data), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw PaymentException::fromGateway($this->name(), 'Could not sign the request payload.');
        }

        return base64_encode($signature);
    }

    /**
     * Nagad hands out bare base64 key bodies; wrap them so OpenSSL accepts them.
     */
    private function pem(?string $key, string $label): string
    {
        if (! $key) {
            throw PaymentException::fromGateway($this->name(), "Missing {$label} in configuration.");
        }

        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN {$label}-----\n".chunk_split(trim($key), 64, "\n")."-----END {$label}-----\n";
    }
}
