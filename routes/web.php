<?php

use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShopController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Storefront, served from the products table.
Route::get('/', [ShopController::class, 'home'])->name('home');
Route::get('shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('shop/{handle}', [ShopController::class, 'show'])->name('shop.show');

Route::get('cart', function () {
    return Inertia::render('shop/cart');
})->name('cart');

// Presentment currency switcher. Display-only: stores the shopper's choice in a
// cookie SetCurrency reads; the server still charges in the base currency.
Route::post('currency', [CurrencyController::class, 'set'])->name('currency.set');

// UI language switcher. Stores the shopper's choice in a cookie SetLocale reads,
// flipping the storefront chrome between English (LTR) and Arabic (RTL).
Route::post('locale', [LocaleController::class, 'set'])->name('locale.set');

// Checkout. The cart is client-side, so `store` receives the lines with the order.
Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
// Embedded card: places the order and returns a client_secret so the card is
// confirmed on-page (Stripe Elements / Tap) instead of via a hosted redirect.
Route::post('checkout/card', [CheckoutController::class, 'card'])->name('checkout.card');
// Promo code: applied against the current bag (subtotal re-priced server-side)
// and held in the session for `store` to redeem, or cleared.
// Abandoned-checkout capture: a debounced, no-content ping that upserts the
// shopper's contact + bag against a browser cookie token before they submit.
Route::post('checkout/session', [CheckoutController::class, 'captureSession'])->name('checkout.session');
Route::post('checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon');
Route::delete('checkout/coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
Route::get('orders/{order}/received', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

// The original starter-kit landing page, kept for reference.
Route::get('welcome', function () {
    return Inertia::render('welcome');
})->name('welcome');

Route::middleware(['auth'])->group(function () {
    // The customer's account home: recent orders, a saved-address count, and
    // links into the account area. Admins see it too (their admin dashboard is
    // a separate phase).
    Route::get('dashboard', function (Request $request) {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'recentOrders' => $user->orders()->limit(5)->get()->map(fn ($order) => [
                'order_number' => $order->order_number,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'status' => $order->status instanceof BackedEnum ? $order->status->value : $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
            ])->all(),
            'ordersCount' => $user->orders()->count(),
            'addressesCount' => $user->addresses()->count(),
        ]);
    })->name('dashboard');

    // Customer order history and detail (own orders only).
    Route::get('account/orders', [AccountOrderController::class, 'index'])->name('account.orders.index');
    Route::get('account/orders/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');
});

require __DIR__.'/admin.php';
require __DIR__.'/payment.php';
require __DIR__.'/delivery.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
