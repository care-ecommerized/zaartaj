<?php

namespace App\Notifications;

use App\Checkout\CheckoutService;
use App\Models\CheckoutSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "you left something behind" nudge.
 *
 * Self-contained for now — the shared lifecycle-email layer (Phase F) is not
 * built yet, so this queued notification is sent on-demand to a bare email
 * route by the sweep. It links back to /checkout?resume={token}, which rehydrates
 * the bag (re-priced from the catalogue) so the shopper can pick up where they left.
 */
class AbandonedCheckoutReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly CheckoutSession $session) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/checkout?resume='.$this->session->token);

        $mail = (new MailMessage)
            ->subject(__('checkout.recovery.subject'))
            ->greeting(__('checkout.recovery.greeting'))
            ->line(__('checkout.recovery.intro'));

        foreach ($this->items() as $item) {
            $mail->line('• '.$item['name'].' × '.$item['quantity']);
        }

        return $mail
            ->action(__('checkout.recovery.cta'), $url)
            ->line(__('checkout.recovery.outro'));
    }

    /**
     * A stub for the future SMS channel (Phase F wires the transport). Kept here
     * so the message content lives with the notification when the channel lands.
     */
    public function toSms(object $notifiable): string
    {
        return __('checkout.recovery.sms', ['url' => url('/checkout?resume='.$this->session->token)]);
    }

    /**
     * The bag's lines, re-priced from the catalogue for the email. Best-effort:
     * lines that no longer resolve are dropped rather than shown with stale names.
     *
     * @return array<int, array{name: string, quantity: int}>
     */
    private function items(): array
    {
        $lines = is_array($this->session->cart) ? $this->session->cart : [];

        return array_map(
            fn (array $line) => ['name' => $line['name'], 'quantity' => $line['quantity']],
            app(CheckoutService::class)->priceLinesForDisplay($lines),
        );
    }
}
