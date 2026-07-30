<?php

namespace App\Payments;

use App\Currency\CurrencyException;
use App\Currency\CurrencyService;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Gateways\BkashGateway;
use App\Payments\Gateways\NagadGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\Gateways\TabbyGateway;
use App\Payments\Gateways\TamaraGateway;
use App\Payments\Gateways\TapGateway;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function __construct(private readonly CurrencyService $currencies = new CurrencyService) {}

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= config('payment.default');

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * Every configured gateway key.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys(config('payment.gateways', []));
    }

    /**
     * Gateways usable without an order — i.e. not BNPL providers that need buyer
     * and line-item context. Used by the standalone payment page.
     *
     * @return array<int, string>
     */
    public function standaloneCapable(): array
    {
        return array_values(array_filter(
            $this->available(),
            fn (string $name) => ! config("payment.gateways.{$name}.requires_order", false),
        ));
    }

    /**
     * The currency a gateway charges in, falling back to the base currency.
     */
    public function currencyFor(string $gateway): string
    {
        return config("payment.gateways.{$gateway}.currency", config('payment.currency'));
    }

    /**
     * Convert an amount from the base currency (AED) into the target currency.
     *
     * Same-currency conversions are a no-op; everything else is delegated to the
     * CurrencyService, which reads live rates from the `currencies` table. A
     * missing/invalid currency there surfaces here as a PaymentException so the
     * gateway contract (the only exception callers catch) is preserved.
     */
    public function convert(float $baseAmount, string $target): float
    {
        $base = config('payment.currency');

        try {
            if ($target === $base) {
                return round($baseAmount, $this->currencies->decimalsFor($base));
            }

            return $this->currencies->convert($baseAmount, $target);
        } catch (CurrencyException $e) {
            throw new PaymentException("Cannot convert [{$base} -> {$target}]: {$e->getMessage()}", previous: $e);
        }
    }

    private function resolve(string $name): PaymentGateway
    {
        $config = config("payment.gateways.{$name}");

        if (! $config) {
            throw new PaymentException("Payment gateway [{$name}] is not configured.");
        }

        return match ($config['driver']) {
            'bkash' => new BkashGateway($config),
            'nagad' => new NagadGateway($config),
            'stripe' => new StripeGateway($config),
            'tap' => new TapGateway($config),
            'tabby' => new TabbyGateway($config),
            'tamara' => new TamaraGateway($config),
            default => throw new PaymentException("Unsupported payment driver [{$config['driver']}]."),
        };
    }
}
