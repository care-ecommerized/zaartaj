import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';
import { formatShopPrice } from './catalog';

/**
 * Returns a formatter that renders a Taka-priced catalogue amount in the
 * shopper's active presentment currency (resolved server-side by SetCurrency and
 * shared through Inertia). Use everywhere the storefront shows a price so a
 * currency switch is reflected consistently.
 */
export function usePrice(): (taka: number) => string {
    const { currency, currencies = [] } = usePage<SharedData>().props;

    return (taka: number) => formatShopPrice(taka, currency, currencies);
}
