import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { formatTaka, type Paginated } from '@/lib/shop/catalog';
import { Link } from '@inertiajs/react';

interface OrderRow {
    order_number: string;
    placed_at: string | null;
    status: string;
    payment_status: string;
    total: number;
    presentment_total: number;
    currency: string;
}

interface OrdersIndexProps {
    orders: Paginated<OrderRow>;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString();
}

export default function AccountOrdersIndex({ orders }: OrdersIndexProps) {
    const { t } = useTranslation();

    return (
        <ShopLayout title="Orders — Zaartaj Elegance">
            <div className="mx-auto max-w-4xl px-5 py-14 lg:px-8">
                <p className="zt-eyebrow">{t('account.eyebrow')}</p>
                <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('account.orders.title')}</h1>

                {orders.data.length === 0 ? (
                    <div className="border-zt-sand mt-12 border px-6 py-16 text-center">
                        <p className="text-zt-muted text-sm">{t('account.orders.empty')}</p>
                        <Link
                            href="/shop"
                            className="bg-zt-teal-deep hover:bg-zt-teal mt-8 inline-block px-8 py-4 text-[0.72rem] tracking-[0.2em] text-white uppercase transition-colors"
                        >
                            {t('account.orders.browse')}
                        </Link>
                    </div>
                ) : (
                    <div className="border-zt-sand mt-10 border">
                        <div className="border-zt-sand text-zt-muted hidden border-b px-6 py-4 text-xs tracking-[0.14em] uppercase sm:grid sm:grid-cols-[1.4fr_1fr_1fr_1fr]">
                            <span>{t('account.order.number')}</span>
                            <span>{t('account.order.date')}</span>
                            <span>{t('account.order.status')}</span>
                            <span className="text-right">{t('account.order.total')}</span>
                        </div>
                        <ul className="divide-zt-sand divide-y">
                            {orders.data.map((order) => (
                                <li key={order.order_number}>
                                    <Link
                                        href={`/account/orders/${order.order_number}`}
                                        className="hover:bg-zt-sand/30 grid gap-1 px-6 py-4 text-sm transition-colors sm:grid-cols-[1.4fr_1fr_1fr_1fr] sm:items-center sm:gap-0"
                                    >
                                        <span className="text-zt-ink font-medium">{order.order_number}</span>
                                        <span className="text-zt-muted">{formatDate(order.placed_at)}</span>
                                        <span className="text-zt-muted">{t(`order.status.${order.status}`)}</span>
                                        <span className="text-zt-ink sm:text-right">{formatTaka(order.total)}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {orders.last_page > 1 && (
                    <nav className="mt-8 flex flex-wrap justify-center gap-2" aria-label={t('account.orders.pagination')}>
                        {orders.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                className={`border px-3 py-2 text-xs ${
                                    link.active ? 'border-zt-teal bg-zt-teal-deep text-white' : 'border-zt-sand text-zt-muted'
                                } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </ShopLayout>
    );
}
