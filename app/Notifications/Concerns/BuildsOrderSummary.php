<?php

namespace App\Notifications\Concerns;

use App\Currency\CurrencyService;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Shared money + line-item formatting for order emails.
 *
 * Every amount is formatted through the single money authority (CurrencyService)
 * in the order's presentment currency, so an email never renders a raw float or
 * drifts from what the customer was charged.
 */
trait BuildsOrderSummary
{
    /**
     * The order's item lines as ready-to-print strings, e.g.
     * "Cinderella Gown (Red / M) × 2 — AED 900.00".
     *
     * @return list<string>
     */
    protected function itemLines(Order $order): array
    {
        return $order->items->map(function (OrderItem $item) use ($order): string {
            $variant = filled($item->variant_title) ? " ({$item->variant_title})" : '';

            return $item->name.$variant.' × '.$item->quantity.' — '.$this->money($order, (float) $item->line_total);
        })->all();
    }

    /**
     * The totals block (subtotal / delivery / discount / total, plus the COD
     * amount for cash orders) as "Label: Amount" strings.
     *
     * @return list<string>
     */
    protected function totalLines(Order $order): array
    {
        $lines = [
            __('email.order.subtotal').': '.$this->money($order, (float) $order->subtotal),
            __('email.order.delivery').': '.(
                (float) $order->shipping_total === 0.0
                    ? __('email.order.complimentary')
                    : $this->money($order, (float) $order->shipping_total)
            ),
        ];

        if ((float) $order->discount_total > 0) {
            $lines[] = __('email.order.discount').': −'.$this->money($order, (float) $order->discount_total);
        }

        $lines[] = __('email.order.total').': '.$this->money($order, (float) $order->total);

        if ((float) $order->cod_amount > 0) {
            $lines[] = __('email.order.cod', ['amount' => $this->money($order, (float) $order->cod_amount)]);
        }

        return $lines;
    }

    /**
     * Format a base-currency (AED) amount in the order's presentment currency.
     * Falls back to a bare number if the currency can't be resolved, so an email
     * never fails to render over a formatting error.
     */
    protected function money(Order $order, float $amount): string
    {
        try {
            return app(CurrencyService::class)->format($amount, $order->currency, $order->locale);
        } catch (\Throwable) {
            return $order->currency.' '.number_format($amount, 2, '.', ',');
        }
    }
}
