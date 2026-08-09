<?php

namespace App\Payments;

use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\SupportsEmbeddedCard;
use App\Payments\Exceptions\PaymentException;
use Illuminate\Support\Str;

/**
 * The single place a Payment is created and handed to a gateway.
 *
 * It resolves the gateway's currency, converts the base-currency (AED) amount
 * into it, records the rate used, and returns the URL the customer must be sent
 * to. Both the storefront checkout and the standalone payment page go through
 * here so currency handling and reference generation never drift apart.
 */
class PaymentInitiator
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * Begin payment for a placed order.
     *
     * @return string The URL to redirect the customer to.
     *
     * @throws PaymentException
     */
    public function startForOrder(Order $order, string $gateway, ?int $userId): string
    {
        return $this->start($gateway, (float) $order->total, [
            'user_id' => $userId,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Begin a standalone payment not tied to an order (the test/checkout page).
     *
     * @return string The URL to redirect the customer to.
     *
     * @throws PaymentException
     */
    public function startStandalone(float $baseAmount, string $gateway, ?int $userId, ?string $payerReference = null): string
    {
        return $this->start($gateway, $baseAmount, [
            'user_id' => $userId,
            'payer_reference' => $payerReference,
        ]);
    }

    /**
     * Begin an embedded (on-page) card payment for a placed order.
     *
     * Returns the client-side config the browser SDK needs to collect the card,
     * plus our payment id so the frontend can settle it once the card confirms.
     *
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    public function startEmbeddedForOrder(Order $order, string $gateway, ?int $userId): array
    {
        $driver = $this->gateways->driver($gateway);

        if (! $driver instanceof SupportsEmbeddedCard) {
            throw new PaymentException("Gateway [{$gateway}] does not support embedded card payments.");
        }

        $payment = $this->createPayment($gateway, (float) $order->total, [
            'user_id' => $userId,
            'order_id' => $order->id,
        ]);

        try {
            $config = $driver->prepareEmbedded($payment);
        } catch (PaymentException $e) {
            $this->failPayment($payment, $e);
        }

        return [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            ...$config,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes  Extra columns for the payment row.
     *
     * @throws PaymentException
     */
    private function start(string $gateway, float $baseAmount, array $attributes): string
    {
        $payment = $this->createPayment($gateway, $baseAmount, $attributes);

        try {
            return $this->gateways->driver($gateway)->initiate($payment);
        } catch (PaymentException $e) {
            $this->failPayment($payment, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes  Extra columns for the payment row.
     */
    private function createPayment(string $gateway, float $baseAmount, array $attributes): Payment
    {
        $currency = $this->gateways->currencyFor($gateway);
        $amount = $this->gateways->convert($baseAmount, $currency);
        $base = config('payment.currency');

        return Payment::create([
            'reference' => 'ZT'.now()->format('ymdHis').Str::upper(Str::random(4)),
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $currency,
            'status' => Payment::STATUS_PENDING,
            // Keep an audit trail of what the base order total was and the rate
            // we charged at, since amount/currency now describe the foreign leg.
            'meta' => [
                'base_currency' => $base,
                'base_amount' => round($baseAmount, 2),
                'rate' => $currency === $base ? 1.0 : Currency::rateFor($currency),
            ],
            ...$attributes,
        ]);
    }

    /**
     * @return never
     *
     * @throws PaymentException
     */
    private function failPayment(Payment $payment, PaymentException $e)
    {
        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $e->getMessage(),
        ])->save();

        throw $e;
    }
}
