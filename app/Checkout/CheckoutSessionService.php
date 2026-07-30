<?php

namespace App\Checkout;

use App\Models\CheckoutSession;
use App\Models\Order;
use Throwable;

/**
 * Captures and settles the lightweight checkout-in-progress trace.
 *
 * The storefront pings capture() on a debounce as the shopper fills in contact
 * details, so an abandonment before submit still leaves a recoverable row keyed
 * by the browser's checkout token. place() converting the bag into an Order then
 * calls markConverted() so the sweep never reminds someone who actually bought.
 */
class CheckoutSessionService
{
    public function __construct(private readonly CheckoutService $checkout = new CheckoutService) {}

    /**
     * Upsert the open session for $token from a debounced capture.
     *
     * A row already marked converted is left untouched — a late-firing debounce
     * must never reopen a completed checkout. The subtotal is a best-effort AED
     * figure for the admin list only; a stale slug that cannot be priced simply
     * leaves it null rather than failing the capture.
     *
     * @param  array{email?: ?string, phone?: ?string, currency?: ?string, locale?: ?string, cart?: array<int, array{slug?: string, size?: ?string, quantity?: mixed}>}  $data
     */
    public function capture(string $token, array $data, ?int $userId = null): CheckoutSession
    {
        $session = CheckoutSession::firstOrNew(['token' => $token]);

        if ($session->status === CheckoutSession::STATUS_CONVERTED) {
            return $session;
        }

        $cart = array_values(array_map(
            fn (array $line) => [
                'slug' => (string) ($line['slug'] ?? ''),
                'size' => isset($line['size']) && $line['size'] !== '' ? (string) $line['size'] : null,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            ],
            array_filter($data['cart'] ?? [], fn ($line) => is_array($line) && ! empty($line['slug'])),
        ));

        $session->fill([
            'user_id' => $userId,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'currency' => $data['currency'] ?? null,
            'locale' => $data['locale'] ?? null,
            'cart' => $cart,
            'subtotal' => $this->bestEffortSubtotal($cart),
            'status' => CheckoutSession::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $session->save();

        return $session;
    }

    /**
     * Mark the session behind $token as converted onto a placed order.
     *
     * A no-op when no session exists for the token (e.g. the shopper never
     * entered contact details before submitting).
     */
    public function markConverted(string $token, Order $order): void
    {
        $session = CheckoutSession::where('token', $token)->first();

        if ($session === null) {
            return;
        }

        $session->forceFill([
            'status' => CheckoutSession::STATUS_CONVERTED,
            'recovered_order_id' => $order->id,
        ])->save();
    }

    /**
     * @param  array<int, array{slug: string, size: ?string, quantity: int}>  $cart
     */
    private function bestEffortSubtotal(array $cart): ?float
    {
        if ($cart === []) {
            return null;
        }

        try {
            return $this->checkout->subtotalFor($cart);
        } catch (Throwable) {
            // A line that no longer prices must not break capture.
            return null;
        }
    }
}
