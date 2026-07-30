<?php

use App\Http\Controllers\Admin\AbandonedCheckoutController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\ShippingZoneController;
use Illuminate\Support\Facades\Route;

/*
 * Staff area. `admin` sits behind `auth` and 404s for everyone else, so these
 * routes are invisible to signed-in customers.
 */
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');

        // Abandoned / open checkout sessions and the conversion metric.
        Route::get('abandoned', [AbandonedCheckoutController::class, 'index'])->name('abandoned.index');

        // Declared ahead of the {product} route so "imports" is not read as a handle.
        Route::get('products/imports', [ProductImportController::class, 'index'])->name('products.imports.index');
        Route::post('products/imports', [ProductImportController::class, 'store'])->name('products.imports.store');
        Route::get('products/imports/{import}', [ProductImportController::class, 'show'])->name('products.imports.show');

        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

        // Order fulfilment. Staff confirm a vetted order (books the courier when
        // auto-dispatch is on) or book/retry a shipment by hand.
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/dispatch', [OrderController::class, 'dispatchShipment'])->name('orders.dispatch');

        // FX management. Staff edit rates by hand and pin them from the live job.
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::put('currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');

        // Worldwide shipping zones & rates. `create` is declared ahead of the
        // {shipping} routes so it is not read as a zone id.
        Route::get('shipping', [ShippingZoneController::class, 'index'])->name('shipping.index');
        Route::get('shipping/create', [ShippingZoneController::class, 'create'])->name('shipping.create');
        Route::post('shipping', [ShippingZoneController::class, 'store'])->name('shipping.store');
        Route::get('shipping/{shipping}/edit', [ShippingZoneController::class, 'edit'])->name('shipping.edit');
        Route::put('shipping/{shipping}', [ShippingZoneController::class, 'update'])->name('shipping.update');
        Route::delete('shipping/{shipping}', [ShippingZoneController::class, 'destroy'])->name('shipping.destroy');

        // Discount coupons. `create` is declared ahead of the {coupon} routes so
        // it is not read as a coupon id.
        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    });
