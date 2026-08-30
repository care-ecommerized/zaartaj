<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\BuildsOrderSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The customer-facing order email, one class parameterized by lifecycle event.
 *
 * Queued and localized per the order's stored locale (set by the notifier via
 * ->locale()). The "placed" variant carries the full itemized invoice; the
 * status variants (cancelled/returned/refunded/shipped/delivered) are short
 * notes that still link back to the order. Copy comes from `email.order.*` keys.
 */
class OrderNotification extends Notification implements ShouldQueue
{
    use BuildsOrderSummary;
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly OrderMailEvent $event,
    ) {}

    /**
     * The lifecycle event this email represents. Exposed for assertions.
     */
    public function mailEvent(): OrderMailEvent
    {
        return $this->event;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $group = 'email.order.'.$this->event->value;
        $url = route('checkout.confirmation', $this->order);

        $mail = (new MailMessage)
            ->subject(__($group.'.subject', ['number' => $this->order->order_number]))
            ->greeting(__($group.'.greeting', ['name' => $this->order->customer_name]))
            ->line(__($group.'.intro', ['number' => $this->order->order_number]));

        if ($this->event->showsInvoice()) {
            $this->order->loadMissing('items');

            $mail->line('**'.__('email.order.items_heading').'**');

            foreach ($this->itemLines($this->order) as $line) {
                $mail->line('• '.$line);
            }

            foreach ($this->totalLines($this->order) as $line) {
                $mail->line($line);
            }
        }

        if (filled($tracking = $this->trackingLine())) {
            $mail->line($tracking);
        }

        return $mail
            ->action(__('email.order.cta'), $url)
            ->line(__($group.'.outro'));
    }

    /**
     * The tracking line for a shipped order, when a code is on file.
     */
    private function trackingLine(): ?string
    {
        if ($this->event !== OrderMailEvent::Shipped) {
            return null;
        }

        $code = $this->order->loadMissing('shipment')->shipment?->tracking_code;

        return filled($code) ? __('email.order.tracking', ['code' => $code]) : null;
    }

    /**
     * A stub for the future SMS channel (the transport lands with the shared
     * lifecycle layer). Kept here so the copy travels with the notification.
     */
    public function toSms(object $notifiable): string
    {
        return __('email.order.'.$this->event->value.'.sms', [
            'number' => $this->order->order_number,
        ]);
    }
}
