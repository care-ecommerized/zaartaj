<?php

namespace App\Checkout;

use App\Checkout\Exceptions\CheckoutException;
use App\Discounts\DiscountService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(private readonly ShippingCalculator $shipping = new ShippingCalculator) {}

    /**
     * Turn a browser cart into a placed order.
     *
     * The cart is client-side and therefore untrusted: prices, names and stock are
     * all re-read from the database here, never taken from the request. The whole
     * thing runs in one transaction with the variant rows locked, so two shoppers
     * racing for the last item cannot both succeed.
     *
     * @param  array<int, array{slug: string, size: ?string, quantity: int}>  $lines
     * @param  array<string, mixed>  $customer  name, phone, email, address, district, note
     */
    public function place(array $lines, array $customer, PaymentMethod $method, ?int $userId = null, ?string $couponCode = null): Order
    {
        if ($lines === []) {
            throw new CheckoutException('Your bag is empty.');
        }

        return DB::transaction(function () use ($lines, $customer, $method, $userId, $couponCode) {
            $resolved = $this->resolveLines($lines);

            // line_total sits under each entry's 'item'; sum those, not the top level.
            $subtotal = array_sum(array_map(fn (array $line) => $line['item']['line_total'], $resolved));

            // Parcel weight in kilograms, summed from the resolved variants. When
            // nothing carries a weight we pass null so weight-method rates simply
            // do not match and the calculator falls through to its other rates.
            $grams = array_sum(array_map(
                fn (array $line) => (int) ($line['variant']->grams ?? 0) * $line['item']['quantity'],
                $resolved,
            ));
            $weight = $grams > 0 ? $grams / 1000 : null;

            // Shipping is priced by the destination country's zone, in AED. The
            // storefront always supplies a validated country; the fallback only
            // matters for direct service calls that omit it.
            $country = strtoupper((string) ($customer['country'] ?? 'AE'));
            $shipping = $this->shipping->charge($subtotal, $country, $weight);

            // A coupon (if one is applied) is re-resolved here from the DB, never
            // trusting the client. An invalid code throws, rolling the whole order
            // back. The discount is in the AED base and comes off the subtotal only.
            $discount = 0.0;
            $coupon = null;

            if ($couponCode !== null && trim($couponCode) !== '') {
                $result = app(DiscountService::class)->resolve(
                    $couponCode,
                    (float) $subtotal,
                    $userId,
                    $customer['email'] ?? null,
                );

                if (! $result->valid) {
                    throw new CheckoutException($result->message ?? 'This promo code cannot be applied.');
                }

                $discount = $result->amount;
                $coupon = $result->coupon;
            }

            // Floor at zero: a discount never turns the shipping charge negative.
            $total = max(0.0, $subtotal + $shipping - $discount);

            if ($total > (float) config('checkout.max_order_total')) {
                throw new CheckoutException('This order exceeds the maximum we can accept online. Please contact us.');
            }

            $order = Order::create([
                'order_number' => Order::generateNumber(),
                'user_id' => $userId,
                'status' => OrderStatus::Pending,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'] ?? null,
                'customer_address' => $customer['address'],
                'customer_district' => $customer['district'] ?? null,
                'customer_country' => $country,
                'customer_postcode' => $customer['postcode'] ?? null,
                'subtotal' => $subtotal,
                'shipping_total' => $shipping,
                'discount_total' => $discount,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'total' => $total,
                // Money is stored in the base currency (AED). Presentment currency
                // equals the base for now; a storefront currency selector lands in a
                // later chunk, at which point currency/fx_rate diverge from the base.
                'base_currency' => config('payment.currency'),
                'currency' => config('payment.currency'),
                'fx_rate' => 1,
                // Cash-on-delivery collects the whole total; a paid gateway collects nothing on delivery.
                'cod_amount' => $method === PaymentMethod::CashOnDelivery ? $total : 0,
                'payment_method' => $method,
                'payment_status' => Order::PAYMENT_UNPAID,
                'note' => $customer['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($resolved as $line) {
                $order->items()->create($line['item']);
                $this->decrementStock($line['variant'], $line['item']['quantity']);
            }

            if ($coupon !== null) {
                // Lock the coupon row and re-check the global limit under the lock,
                // so two orders racing for the last redemption cannot both win. The
                // redemption row and the counter move together inside this same
                // transaction, keeping the ledger and times_used consistent.
                $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();

                if ($locked === null || ! $locked->hasGlobalCapacity()) {
                    throw new CheckoutException('This promo code has reached its usage limit.');
                }

                $locked->redemptions()->create([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'email' => $customer['email'] ?? null,
                    'amount' => $discount,
                ]);

                $locked->increment('times_used');
            }

            return $order;
        });
    }

    /**
     * The AED-base subtotal for a set of cart lines, priced from the database.
     *
     * Reuses the exact line resolution place() runs, so a coupon preview and the
     * final order price a bag identically. Throws CheckoutException (with per-line
     * errors) when a line no longer resolves.
     *
     * @param  array<int, array{slug: string, size: ?string, quantity: int}>  $lines
     */
    public function subtotalFor(array $lines): float
    {
        $resolved = $this->resolveLines($lines);

        return (float) array_sum(array_map(fn (array $line) => $line['item']['line_total'], $resolved));
    }

    /**
     * Re-price a stored/abandoned cart from the catalogue, for display only.
     *
     * Unlike resolveLines() this never locks and never throws: a line whose slug,
     * option or stock no longer resolves is silently dropped, so a resumed bag
     * shows only what can still be bought — priced fresh from the database, never
     * from whatever was captured. Returns cart-line snapshots shaped for the
     * frontend cart provider.
     *
     * @param  array<int, array{slug?: string, size?: ?string, quantity?: mixed}>  $lines
     * @return array<int, array{slug: string, size: ?string, quantity: int, name: string, price: float, image: ?string}>
     */
    public function priceLinesForDisplay(array $lines): array
    {
        $priced = [];

        foreach ($lines as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            $slug = (string) ($line['slug'] ?? '');

            if ($quantity < 1 || $slug === '') {
                continue;
            }

            $product = Product::query()->published()->where('handle', $slug)->first();

            if (! $product) {
                continue;
            }

            $variant = $this->resolveVariantForDisplay($product, $line['size'] ?? null);

            if (! $variant || ! $variant->isPurchasable()) {
                continue;
            }

            $priced[] = [
                'slug' => $slug,
                'size' => $line['size'] ?? null,
                'quantity' => $quantity,
                'name' => $product->title,
                'price' => (float) $variant->price,
                'image' => $product->featuredImage?->url(),
            ];
        }

        return $priced;
    }

    /**
     * Restore the stock an order reserved.
     *
     * Called when an online payment fails or is abandoned, so the items a customer
     * never paid for go back on sale rather than staying locked away.
     */
    public function releaseStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items()->whereNotNull('product_variant_id')->get() as $item) {
                $variant = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

                if ($variant) {
                    $variant->increment('inventory_quantity', $item->quantity);
                    $variant->product?->syncVariantAggregates();
                }
            }
        });
    }

    /**
     * Resolve every cart line against the database, locking variant rows.
     *
     * @param  array<int, array{slug: string, size: ?string, quantity: int}>  $lines
     * @return array<int, array{item: array<string, mixed>, variant: ProductVariant}>
     */
    private function resolveLines(array $lines): array
    {
        $resolved = [];
        $lineErrors = [];

        foreach ($lines as $index => $line) {
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($quantity < 1) {
                $lineErrors[$index] = 'Invalid quantity.';

                continue;
            }

            $product = Product::query()
                ->published()
                ->where('handle', $line['slug'] ?? '')
                ->first();

            if (! $product) {
                $lineErrors[$index] = 'This item is no longer available.';

                continue;
            }

            $variant = $this->resolveVariant($product, $line['size'] ?? null);

            if (! $variant) {
                $lineErrors[$index] = 'The selected option is no longer available.';

                continue;
            }

            if (! $variant->isPurchasable() || $variant->inventory_quantity < $quantity) {
                $available = max(0, $variant->inventory_quantity);
                $lineErrors[$index] = $available === 0
                    ? "{$product->title} is sold out."
                    : "Only {$available} of {$product->title} left.";

                continue;
            }

            $unitPrice = (float) $variant->price;

            $resolved[$index] = [
                'variant' => $variant,
                'item' => [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'name' => $product->title,
                    'variant_title' => $this->variantTitle($variant),
                    'sku' => $variant->sku,
                    'image' => $product->featuredImage?->url(),
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => round($unitPrice * $quantity, 2),
                ],
            ];
        }

        if ($lineErrors !== []) {
            throw CheckoutException::withLineErrors('Some items in your bag need attention.', $lineErrors);
        }

        return array_values($resolved);
    }

    /**
     * Find the variant a cart line refers to.
     *
     * The cart records the chosen option value ("Blue"), not a variant id, so we
     * match on the option. A product with one real variant ignores the value.
     */
    private function resolveVariant(Product $product, ?string $optionValue): ?ProductVariant
    {
        $variants = $product->variants()->lockForUpdate()->get();

        if ($variants->count() <= 1) {
            return $variants->first();
        }

        if (filled($optionValue)) {
            $match = $variants->first(fn (ProductVariant $variant) => $variant->option1 === $optionValue);

            if ($match) {
                return $match;
            }
        }

        return $variants->first();
    }

    /**
     * The display-only twin of resolveVariant(): matches the chosen option value
     * against a product's variants without taking a row lock, since no stock is
     * being reserved here.
     */
    private function resolveVariantForDisplay(Product $product, ?string $optionValue): ?ProductVariant
    {
        $variants = $product->variants()->get();

        if ($variants->count() <= 1) {
            return $variants->first();
        }

        if (filled($optionValue)) {
            $match = $variants->first(fn (ProductVariant $variant) => $variant->option1 === $optionValue);

            if ($match) {
                return $match;
            }
        }

        return $variants->first();
    }

    private function decrementStock(ProductVariant $variant, int $quantity): void
    {
        // The row is already locked from resolveVariant within this transaction.
        $variant->decrement('inventory_quantity', $quantity);
        $variant->product?->syncVariantAggregates();
    }

    private function variantTitle(ProductVariant $variant): ?string
    {
        $parts = array_filter([$variant->option1, $variant->option2, $variant->option3]);
        $title = implode(' / ', $parts);

        return $title === '' || $title === 'Default Title' ? null : $title;
    }
}
