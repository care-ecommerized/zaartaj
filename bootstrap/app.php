<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrency;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Aramex has no status-push webhook, so polling is the only way its
        // shipments settle. This also backstops any Steadfast webhook we miss.
        $schedule->command('shipments:reconcile')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // Refresh presentment FX rates from the live provider once a day. Manual
        // overrides and the base row are skipped by the updater itself.
        $schedule->command('fx:update')
            ->daily()
            ->withoutOverlapping();

        // Turn checkouts idle past the abandon window into one-time recovery
        // nudges, and prune the very old abandoned traces.
        $schedule->command('checkouts:sweep')
            ->hourly()
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Both run before HandleInertiaRequests so the resolved locale and
            // presentment currency are on the container when share() reads them.
            SetLocale::class,
            SetCurrency::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // The abandoned-checkout token is an opaque UUID, not a secret — it is
        // handed out in plaintext in the recovery link (/checkout?resume={token}),
        // so its cookie is left unencrypted for a stable round-trip.
        $middleware->encryptCookies(except: [
            'checkout_token',
        ]);

        // Gateways and couriers post back without our session token.
        $middleware->validateCsrfTokens(except: [
            'payments/*/callback',
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
