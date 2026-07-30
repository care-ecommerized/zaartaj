import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { formatTaka } from '@/lib/shop/catalog';

interface OrderItem {
    name: string;
    variant_title: string | null;
    image: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

interface ConfirmationProps {
    order: {
        order_number: string;
        status: string;
        payment_method: string;
        payment_status: string;
        customer_name: string;
        customer_phone: string;
        customer_address: string;
        customer_district: string | null;
        subtotal: number;
        shipping_total: number;
        total: number;
        cod_amount: number;
        items: OrderItem[];
    };
}

export default function OrderConfirmation({ order }: ConfirmationProps) {
    const { t } = useTranslation();
    const isCod = order.payment_method === 'cod';
    // Known methods get a translated label; anything else falls back to its raw value.
    const paymentLabel = t(`payment.${order.payment_method}`) !== `payment.${order.payment_method}` ? t(`payment.${order.payment_method}`) : order.payment_method;

    return (
        <ShopLayout title={`Order ${order.order_number} — Zaartaj Elegance`}>
            <div className="mx-auto max-w-3xl px-5 py-16 lg:px-8">
                <div className="text-center">
                    <span className="bg-zt-teal-deep mx-auto flex size-14 items-center justify-center rounded-full text-white">
                        <Check className="size-7" />
                    </span>
                    <h1 className="font-display text-zt-ink mt-6 text-4xl sm:text-5xl">{t('confirmation.thank_you')}</h1>
                    <p className="text-zt-muted mt-3 text-sm">
                        {t('confirmation.order_placed_before')} <span className="text-zt-ink font-medium">{order.order_number}</span>{' '}
                        {t('confirmation.order_placed_after', { phone: order.customer_phone })}
                    </p>
                    {order.payment_status === 'paid' && (
                        <p className="mt-2 text-xs tracking-[0.14em] text-emerald-600 uppercase">{t('confirmation.payment_received')}</p>
                    )}
                </div>

                <div className="border-zt-sand mt-12 border">
                    <div className="border-zt-sand flex flex-wrap justify-between gap-4 border-b px-6 py-5 text-sm">
                        <div>
                            <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('confirmation.deliver_to')}</p>
                            <p className="text-zt-ink mt-1">{order.customer_name}</p>
                            <p className="text-zt-muted mt-0.5">
                                {order.customer_address}
                                {order.customer_district ? `, ${order.customer_district}` : ''}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-zt-muted text-xs tracking-[0.14em] uppercase">{t('confirmation.payment')}</p>
                            <p className="text-zt-ink mt-1">{paymentLabel}</p>
                        </div>
                    </div>

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
                        <div className="flex justify-between">
                            <dt className="text-zt-muted">{t('confirmation.delivery')}</dt>
                            <dd className="text-zt-ink">{order.shipping_total === 0 ? t('confirmation.complimentary') : formatTaka(order.shipping_total)}</dd>
                        </div>
                        <div className="border-zt-sand flex justify-between border-t pt-3 text-base">
                            <dt className="text-zt-ink">{t('confirmation.total')}</dt>
                            <dd className="text-zt-ink font-medium">{formatTaka(order.total)}</dd>
                        </div>
                        {isCod && (
                            <p className="text-zt-muted pt-2 text-xs">{t('confirmation.keep_ready', { amount: formatTaka(order.cod_amount) })}</p>
                        )}
                    </dl>
                </div>

                <div className="mt-10 text-center">
                    <Link href="/shop" className="bg-zt-teal-deep hover:bg-zt-teal inline-block px-10 py-4 text-[0.72rem] tracking-[0.2em] text-white uppercase transition-colors">
                        {t('confirmation.continue')}
                    </Link>
                </div>
            </div>
        </ShopLayout>
    );
}
