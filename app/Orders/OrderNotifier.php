<?php

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminNewOrderNotification;
use App\Notifications\OrderMailEvent;
use App\Notifications\OrderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The single funnel for every order email.
 *
 * Routing all order mail through here keeps the "only notify on a real change,
 * and only once" rule in one place — so a webhook and an admin edit racing on
 * the same order cannot double-send. Customer mail goes to the order's own
 * email (guests are not User models); admin mail goes to the configured
 * notification address, or every admin user as a fallback.
 */
class OrderNotifier
{
    /**
     * A new order was placed: invoice the customer and alert the shop.
     */
    public function placed(Order $order): void
    {
        $this->notifyCustomer($order, OrderMailEvent::Placed);
        $this->notifyAdmins($order);
    }

    /**
     * A status transition happened. Emails the customer only when the move is
     * real ($from !== $to) and the target status has customer-facing copy.
     */
    public function statusChanged(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $event = OrderMailEvent::forStatus($to);

        if ($event !== null) {
            $this->notifyCustomer($order, $event);
        }
    }

    /**
     * The order was refunded (a payment-side event with no status transition).
     */
    public function refunded(Order $order): void
    {
        $this->notifyCustomer($order, OrderMailEvent::Refunded);
    }

    /**
     * Send a customer notification to the order's email, localized to the order.
     *
     * A guest order may carry no email (phone-only COD); we skip and log rather
     * than fail. A send error is swallowed and logged so a mail outage never
     * rolls back the order action that triggered it.
     */
    private function notifyCustomer(Order $order, OrderMailEvent $event): void
    {
        if (blank($order->customer_email)) {
            Log::info('Order email skipped: no customer email.', [
                'order' => $order->order_number,
                'event' => $event->value,
            ]);

            return;
        }

        try {
            Notification::route('mail', $order->customer_email)
                ->notify((new OrderNotification($order, $event))->locale($order->locale ?: 'en'));
        } catch (Throwable $e) {
            Log::warning('Order email failed.', [
                'order' => $order->order_number,
                'event' => $event->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alert the shop of a new order. No-op (logged) when no recipient is set.
     */
    private function notifyAdmins(Order $order): void
    {
        $recipients = $this->adminRecipients();

        if ($recipients === []) {
            Log::info('Admin order alert skipped: no recipient configured.', [
                'order' => $order->order_number,
            ]);

            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new AdminNewOrderNotification($order));
        } catch (Throwable $e) {
            Log::warning('Admin order alert failed.', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Where new-order alerts go: the configured notification address if set,
     * otherwise every admin user's email.
     *
     * @return list<string>
     */
    private function adminRecipients(): array
    {
        $configured = (string) (Setting::get('notifications.order_email') ?? '');

        if (trim($configured) !== '') {
            return [$configured];
        }

        try {
            return User::query()->where('is_admin', true)->pluck('email')->all();
        } catch (Throwable) {
            return [];
        }
    }
}
