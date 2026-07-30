<?php

namespace App\Delivery;

use App\Delivery\Contracts\Courier;
use App\Delivery\Couriers\AramexCourier;
use App\Delivery\Couriers\SteadfastCourier;
use App\Delivery\Exceptions\DeliveryException;

class DeliveryManager
{
    /** @var array<string, Courier> */
    private array $resolved = [];

    public function driver(?string $name = null): Courier
    {
        $name ??= config('delivery.default');

        return $this->resolved[$name] ??= $this->resolve($name);
    }

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        return array_keys(config('delivery.couriers', []));
    }

    private function resolve(string $name): Courier
    {
        $config = config("delivery.couriers.{$name}");

        if (! $config) {
            throw new DeliveryException("Courier [{$name}] is not configured.");
        }

        return match ($config['driver']) {
            'steadfast' => new SteadfastCourier($config),
            'aramex' => new AramexCourier($config),
            default => throw new DeliveryException("Unsupported delivery driver [{$config['driver']}]."),
        };
    }
}
