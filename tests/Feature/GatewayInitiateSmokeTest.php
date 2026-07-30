<?php

namespace Tests\Feature;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentGatewayManager;
use Tests\TestCase;

class GatewayInitiateSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Minimal, offline config for the AED-settling gateways — enough for the
        // manager to build them; no credentials are exercised here.
        config()->set('payment.gateways.stripe', [
            'driver' => 'stripe', 'currency' => 'AED', 'base_url' => 'https://stripe.test',
            'secret_key' => 'sk', 'publishable_key' => 'pk', 'webhook_secret' => 'wh',
        ]);
        config()->set('payment.gateways.tap', [
            'driver' => 'tap', 'currency' => 'AED', 'base_url' => 'https://tap.test',
            'secret_key' => 'sk', 'publishable_key' => 'pk', 'webhook_secret' => '',
        ]);
        config()->set('payment.gateways.tabby', [
            'driver' => 'tabby', 'currency' => 'AED', 'requires_order' => true, 'base_url' => 'https://tabby.test',
            'public_key' => 'pk', 'secret_key' => 'sk', 'merchant_code' => 'mc',
        ]);
        config()->set('payment.gateways.tamara', [
            'driver' => 'tamara', 'currency' => 'AED', 'requires_order' => true, 'base_url' => 'https://tamara.test',
            'api_token' => 'tok', 'notification_token' => 'nt', 'public_key' => 'pk', 'country' => 'AE',
        ]);
    }

    public function test_the_aed_gateways_all_build_without_throwing(): void
    {
        $manager = app(PaymentGatewayManager::class);

        foreach (['stripe', 'tap', 'tabby', 'tamara'] as $gateway) {
            $this->assertInstanceOf(PaymentGateway::class, $manager->driver($gateway));
        }
    }

    public function test_the_gateway_webhook_route_resolves(): void
    {
        $this->assertStringContainsString(
            'tap',
            route('payments.webhook', ['gateway' => 'tap']),
        );
    }
}
