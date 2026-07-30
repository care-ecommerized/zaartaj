<?php

namespace App\Console\Commands;

use App\Models\CheckoutSession;
use App\Notifications\AbandonedCheckoutReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Turns stale checkouts-in-progress into recovery nudges.
 *
 * An open session with a contactable email that has sat untouched past the
 * abandon window (config: checkout.abandon_after_hours) is marked abandoned and
 * sent a one-time reminder. Very old abandoned rows are pruned so the trace table
 * does not grow without bound.
 */
class SweepAbandonedCheckouts extends Command
{
    protected $signature = 'checkouts:sweep {--limit=200 : Maximum sessions to remind in one run}';

    protected $description = 'Flag stale checkout sessions as abandoned and send a recovery reminder';

    public function handle(): int
    {
        $threshold = now()->subHours((int) config('checkout.abandon_after_hours', 4));

        $sessions = CheckoutSession::query()
            ->open()
            ->stale($threshold)
            ->whereNotNull('email')
            ->whereNull('reminder_sent_at')
            ->orderBy('last_activity_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($sessions as $session) {
            $session->forceFill([
                'status' => CheckoutSession::STATUS_ABANDONED,
                'reminder_sent_at' => now(),
            ])->save();

            Notification::route('mail', $session->email)
                ->notify((new AbandonedCheckoutReminder($session))->locale($session->locale ?: 'en'));
        }

        $pruned = CheckoutSession::query()
            ->where('status', CheckoutSession::STATUS_ABANDONED)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Reminded {$sessions->count()} abandoned checkout(s), pruned {$pruned} old row(s).");

        return self::SUCCESS;
    }
}
