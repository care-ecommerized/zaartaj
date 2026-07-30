<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('checkout', [PaymentController::class, 'create'])->name('create');
    Route::post('checkout', [PaymentController::class, 'store'])->name('store');

    // The gateway decides the verb: bKash returns via GET, Nagad may POST.
    Route::match(['get', 'post'], '{payment}/callback', [PaymentController::class, 'callback'])
        ->name('callback');

    Route::get('{payment}', [PaymentController::class, 'show'])->name('show');
    Route::post('{payment}/reconcile', [PaymentController::class, 'reconcile'])->name('reconcile');
});

// Server-to-server settlement notifications from the online gateways. One
// endpoint per gateway keyed by name; each driver verifies its own signature.
// Constrained to payment gateways so it never shadows other webhooks (steadfast).
Route::post('webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->whereIn('gateway', array_keys(config('payment.gateways', [])))
    ->name('payments.webhook');
