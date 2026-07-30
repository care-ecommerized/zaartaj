<?php

namespace Tests\Feature;

use App\Payments\PaymentGatewayManager;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentConvertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
        config()->set('payment.currency', 'AED');
    }

    public function test_converting_the_aed_base_out_to_bdt_multiplies(): void
    {
        // The new AED-base semantics: 100 AED -> 3,300 BDT (multiply, not divide).
        $this->assertSame(3300.0, app(PaymentGatewayManager::class)->convert(100, 'BDT'));
    }

    public function test_converting_the_aed_base_into_aed_is_a_no_op(): void
    {
        $this->assertSame(250.0, app(PaymentGatewayManager::class)->convert(250, 'AED'));
    }
}
