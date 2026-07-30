<?php

namespace App\Payments;

use App\Models\Currency;
use App\Models\Order;
use App\Models\Payment;
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
     * @param  array<string, mixed>  $attributes  Extra columns for the payment row.
     *
     * @throws PaymentException
     */
    private function start(string $gateway, float $baseAmount, array $attributes): string
    {
        $currency = $this->gateways->currencyFor($gateway);
        $amount = $this->gateways->convert($baseAmount, $currency);
        $base = config('payment.currency');

        $payment = Payment::create([
            'reference' => 'ZT'.now()->format('ymdHis').Str::upper(Str::random(4)),
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $currency,
            'status' => Payment::STATUS_PENDING,
            // Keep an audit trail of what the base order total was and the rate
            // we charged at, since amount/currency now describe the foreign leg.
            // The rate comes from the currencies table (the money authority), not
            // the legacy config array.
            'meta' => [
                'base_currency' => $base,
                'base_amount' => round($baseAmount, 2),
                'rate' => $currency === $base ? 1.0 : Currency::rateFor($currency),
            ],
            ...$attributes,
        ]);

        try {
            return $this->gateways->driver($gateway)->initiate($payment);
        } catch (PaymentException $e) {
            $payment->forceFill([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }
}
