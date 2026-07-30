import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { formatTaka } from '@/lib/shop/catalog';
import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';

interface OrderItem {
    name: string;
    variant_title: string | null;
    image: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

interface Shipment {
    courier: string | null;
    tracking_code: string | null;
    status: string | null;
    dispatched_at: string | null;
    delivered_at: string | null;
}

interface OrderShowProps {
    order: {
        order_number: string;
        status: string;
        payment_method: string;
        payment_status: string;
        placed_at: string | null;
        customer_name: string;
        customer_phone: string;
        customer_address: string;
        customer_district: string | null;
        customer_country: string | null;
        customer_postcode: string | null;
        subtotal: number;
        shipping_total: number;
        discount_total: number;
        coupon_code: string | null;
        total: number;
        cod_amount: number;
        items: OrderItem[];
        shipment: Shipment | null;
    };
}

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString();
}

export default function AccountOrderShow({ order }: OrderShowProps) {
    const { t } = useTranslation();
    const isCod = order.payment_method === 'cod';
    const paymentLabel =
        t(`payment.${order.payment_method}`) !== `payment.${order.payment_method}` ? t(`payment.${order.payment_method}`) : order.payment_method;

    return (
        <ShopLayout title={`Order ${order.order_number} — Zaartaj Elegance`}>
            <div className="mx-auto max-w-3xl px-5 py-14 lg:px-8">
                <Link href="/account/orders" className="text-zt-muted hover:text-zt-ink inline-flex items-center gap-1 text-sm">
                    <ChevronLeft className="size-4" />
                    {t('account.order.back')}
                </Link>

                <div className="mt-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="zt-eyebrow">{t('account.order.eyebrow')}</p>
                        <h1 className="font-display text-zt-ink mt-2 text-3xl sm:text-4xl">{order.order_number}</h1>
                        {order.placed_at && <p className="text-zt-muted mt-1 text-sm">{formatDate(order.placed_at)}</p>}
                    </div>
                    <div className="text-right text-sm">
                        <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('account.order.status')}</p>
                        <p className="text-zt-ink mt-1">{t(`order.status.${order.status}`)}</p>
                    </div>
                </div>

                <div className="border-zt-sand mt-8 border">
                    <div className="border-zt-sand flex flex-wrap justify-between gap-4 border-b px-6 py-5 text-sm">
                        <div>
                            <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('confirmation.deliver_to')}</p>
                            <p className="text-zt-ink mt-1">{order.customer_name}</p>
                            <p className="text-zt-muted mt-0.5">
                                {order.customer_address}
                                {order.customer_district ? `, ${order.customer_district}` : ''}
                                {order.customer_postcode ? `, ${order.customer_postcode}` : ''}
                                {order.customer_country ? `, ${order.customer_country}` : ''}
                            </p>
                            <p className="text-zt-muted mt-0.5">{order.customer_phone}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('confirmation.payment')}</p>
                            <p className="text-zt-ink mt-1">{paymentLabel}</p>
                            {order.payment_status === 'paid' && (
                                <p className="mt-1 text-xs tracking-[0.14em] text-emerald-600 uppercase">{t('confirmation.payment_received')}</p>
                            )}
                        </div>
                    </div>

                    {order.shipment && (order.shipment.tracking_code || order.shipment.courier) && (
                        <div className="border-zt-sand border-b px-6 py-5 text-sm">
                            <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('account.order.tracking')}</p>
                            <p className="text-zt-ink mt-1">
                                {order.shipment.courier}
                                {order.shipment.tracking_code ? ` · ${order.shipment.tracking_code}` : ''}
                            </p>
                            {order.shipment.status && <p className="text-zt-muted mt-0.5">{order.shipment.status}</p>}
                        </div>
                    )}

                    <ul className="divide-zt-sand divide-y px-6">
                        {order.items.map((item, index) => (
                            <li key={index} className="flex items-center justify-between gap-4 py-4 text-sm">
                                <div>
                                    <p className="text-zt-ink">{item.name}</p>
                                    <p className="text-zt-muted mt-0.5 text-xs">
                                        {item.variant_title ? `${item.variant_title} · ` : ''}
                                        {t('confirmation.qty', { count: item.quantity })}
                                    </p>
                                </div>
                                <p className="text-zt-ink shrink-0">{formatTaka(item.line_total)}</p>
                            </li>
                        ))}
                    </ul>

                    <dl className="border-zt-sand space-y-2 border-t px-6 py-5 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-zt-muted">{t('confirmation.subtotal')}</dt>
                            <dd className="text-zt-ink">{formatTaka(order.subtotal)}</dd>
                        </div>
                        {order.discount_total > 0 && (
                            <div className="flex justify-between">
                                <dt className="text-zt-muted">{t('checkout.discount')}</dt>
                                <dd className="text-zt-teal-deep">−{formatTaka(order.discount_total)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between">
                            <dt className="text-zt-muted">{t('confirmation.delivery')}</dt>
                            <dd className="text-zt-ink">
                                {order.shipping_total === 0 ? t('confirmation.complimentary') : formatTaka(order.shipping_total)}
                            </dd>
                        </div>
                        <div className="border-zt-sand flex justify-between border-t pt-3 text-base">
                            <dt className="text-zt-ink">{t('confirmation.total')}</dt>
                            <dd className="text-zt-ink font-medium">{formatTaka(order.total)}</dd>
                        </div>
                        {isCod && order.cod_amount > 0 && (
                            <p className="text-zt-muted pt-2 text-xs">{t('confirmation.keep_ready', { amount: formatTaka(order.cod_amount) })}</p>
                        )}
                    </dl>
                </div>
            </div>
        </ShopLayout>
    );
}
