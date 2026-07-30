<?php

namespace App\Http\Controllers\Admin;

use App\Currency\CurrencyService;
use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Staff visibility into checkouts-in-progress and recovery outcomes.
 *
 * Lists the open and abandoned sessions (the ones worth chasing) and a simple
 * conversion metric — how many captured checkouts went on to become orders
 * versus how many were abandoned.
 */
class AbandonedCheckoutController extends Controller
{
    public function __construct(private readonly CurrencyService $currencies) {}

    public function index(Request $request): Response
    {
        $presentment = app()->has('presentment_currency')
            ? (string) app('presentment_currency')
            : (string) config('payment.currency');

        $base = (string) config('payment.currency');

        $sessions = CheckoutSession::query()
            ->whereIn('status', [CheckoutSession::STATUS_OPEN, CheckoutSession::STATUS_ABANDONED])
            ->latest('last_activity_at')
            ->limit(200)
            ->get()
            ->map(fn (CheckoutSession $session) => [
                'id' => $session->id,
                'email' => $session->email,
                'phone' => $session->phone,
                'items_count' => is_array($session->cart) ? count($session->cart) : 0,
                'subtotal' => $session->subtotal !== null ? (float) $session->subtotal : null,
                'subtotal_presentment' => $this->toPresentment($session->subtotal, $presentment),
                'status' => $session->status,
                'recovered' => $session->recovered_order_id !== null,
                'last_activity_at' => $session->last_activity_at?->toIso8601String(),
                'reminder_sent_at' => $session->reminder_sent_at?->toIso8601String(),
            ]);

        $converted = CheckoutSession::where('status', CheckoutSession::STATUS_CONVERTED)->count();
        $abandoned = CheckoutSession::where('status', CheckoutSession::STATUS_ABANDONED)->count();
        $open = CheckoutSession::where('status', CheckoutSession::STATUS_OPEN)->count();
        $decided = $converted + $abandoned;

        return Inertia::render('admin/abandoned/index', [
            'sessions' => $sessions,
            'baseCurrency' => $base,
            'presentmentCurrency' => $presentment,
            'metrics' => [
                'open' => $open,
                'converted' => $converted,
                'abandoned' => $abandoned,
                'conversion_rate' => $decided > 0 ? round($converted / $decided * 100, 1) : null,
            ],
        ]);
    }

    /**
     * Best-effort presentment conversion; a missing/inactive currency just returns
     * null so the list still renders the AED base figure.
     */
    private function toPresentment(mixed $subtotal, string $presentment): ?float
    {
        if ($subtotal === null) {
            return null;
        }

        try {
            return $this->currencies->convert((float) $subtotal, $presentment);
        } catch (Throwable) {
            return null;
        }
    }
}
