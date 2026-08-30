<?php

use App\Http\Controllers\Admin\AbandonedCheckoutController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PlaceholderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImportController;
use App\Http\Controllers\Admin\SettingsController;
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
        // Admin landing: the sidebar Dashboard links here.
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');

        // Abandoned / open checkout sessions and the conversion metric.
        Route::get('abandoned', [AbandonedCheckoutController::class, 'index'])->name('abandoned.index');

        // Declared ahead of the {product} route so "imports" is not read as a handle.
        Route::get('products/imports', [ProductImportController::class, 'index'])->name('products.imports.index');
        Route::post('products/imports', [ProductImportController::class, 'store'])->name('products.imports.store');
        Route::get('products/imports/{import}', [ProductImportController::class, 'show'])->name('products.imports.show');

        // Manual product management. `create` is declared ahead of the {product}
        // routes so it is not read as a handle; store/update/destroy and the
        // nested inventory/image endpoints funnel through ProductWriteService.
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        Route::patch('products/{product}/variants/{variant}', [ProductController::class, 'updateInventory'])
            ->scopeBindings()
            ->name('products.inventory');

        Route::post('products/{product}/images/reorder', [ProductController::class, 'reorderImages'])->name('products.images.reorder');
        Route::post('products/{product}/images', [ProductController::class, 'uploadImage'])->name('products.images.store');
        Route::delete('products/{product}/images/{image}', [ProductController::class, 'deleteImage'])
            ->scopeBindings()
            ->name('products.images.destroy');

        // One clip per product: POST replaces whatever is there, DELETE clears it.
        Route::post('products/{product}/video', [ProductController::class, 'uploadVideo'])->name('products.video.store');
        Route::delete('products/{product}/video', [ProductController::class, 'deleteVideo'])->name('products.video.destroy');

        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');

        // Orders console. Static segments are declared ahead of the {order}
        // member routes so "export" is never read as an order number.
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/comment', [OrderController::class, 'addComment'])->name('orders.comment');

        // Order fulfilment. Staff confirm a vetted order (books the courier when
        // auto-dispatch is on) or book/retry a shipment by hand.
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/dispatch', [OrderController::class, 'dispatchShipment'])->name('orders.dispatch');
        // Mark a paid order refunded (records it + emails the customer; no gateway call).
        Route::patch('orders/{order}/refund', [OrderController::class, 'markRefunded'])->name('orders.refund');

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

        /*
         * Placeholder sections. These are wired into the admin sidebar but not
         * yet built; each renders the shared "coming soon" page so the nav never
         * 404s. They are replaced by real screens in later phases.
         */
        Route::get('draft-orders', fn () => app(PlaceholderController::class)->show('Draft Orders', 'admin.nav.draft_orders'))
            ->name('draftOrders.index');

        // Customers console: an email-keyed, order-derived list (guests included).
        // `export` is declared ahead of any member route so it is never read as a
        // customer id.
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        // Categories console: a flat, reorderable list with visibility + Arabic
        // names. `reorder` is declared ahead of the {category} routes so it is
        // never read as a category id.
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::put('categories/{category:id}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category:id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::get('contact', fn () => app(PlaceholderController::class)->show('Contact', 'admin.nav.contact'))
            ->name('contact.index');
        Route::get('theme', fn () => app(PlaceholderController::class)->show('Theme', 'admin.nav.theme'))
            ->name('theme.index');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
