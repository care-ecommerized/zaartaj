<?php

namespace App\Providers;

use App\Delivery\DeliveryManager;
use App\Events\OrderConfirmed;
use App\Listeners\DispatchOrderShipment;
use App\Listeners\LinkGuestOrders;
use App\Payments\PaymentGatewayManager;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(DeliveryManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(OrderConfirmed::class, DispatchOrderShipment::class);

        // Claim guest orders onto an account once its email is verified.
        Event::listen(Verified::class, LinkGuestOrders::class);
    }
}
