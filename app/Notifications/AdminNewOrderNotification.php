<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\BuildsOrderSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "you have a new order" alert sent to the shop.
 *
 * Queued and always in English (staff-facing), it summarizes the order and links
 * straight to its admin detail page. Recipient(s) are resolved by OrderNotifier.
 */
class AdminNewOrderNotification extends Notification implements ShouldQueue
{
    use BuildsOrderSummary;
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('items');

        $mail = (new MailMessage)
            ->subject(__('email.admin.new_order.subject', ['number' => $this->order->order_number]))
            ->greeting(__('email.admin.new_order.greeting'))
            ->line(__('email.admin.new_order.intro', [
                'number' => $this->order->order_number,
                'customer' => $this->order->customer_name,
            ]))
            ->line(__('email.admin.new_order.contact', [
                'phone' => $this->order->customer_phone,
                'country' => $this->order->customer_country,
            ]))
            ->line('**'.__('email.order.items_heading').'**');

        foreach ($this->itemLines($this->order) as $line) {
            $mail->line('• '.$line);
        }

        foreach ($this->totalLines($this->order) as $line) {
            $mail->line($line);
        }

        return $mail
            ->line(__('email.admin.new_order.payment', [
                'method' => $this->order->payment_method->value,
                'status' => $this->order->payment_status,
            ]))
            ->action(__('email.admin.new_order.cta'), route('admin.orders.show', $this->order));
    }
}
