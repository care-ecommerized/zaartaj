import { Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CreditCard,
    MessageSquare,
    Package,
    RefreshCw,
    Truck,
    User as UserIcon,
    type LucideIcon,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';
import { formatMoney } from '@/lib/shop/catalog';

interface OrderItem {
    name: string;
    variant_title: string | null;
    image: string | null;
    unit_price: number;
    quantity: number;
    line_total: number;
}

interface OrderPayment {
    gateway: string;
    reference: string | null;
    amount: number;
    currency: string;
    status: string;
    gateway_transaction_id: string | null;
    paid_at: string | null;
}

interface OrderEvent {
    id: number;
    type: string;
    title: string;
    body: string | null;
    from_status: string | null;
    to_status: string | null;
    actor_type: string | null;
    actor_name: string | null;
    created_at: string | null;
}

interface AdminShipment {
    courier: string | null;
    consignment_id: string | null;
    tracking_code: string | null;
    status: string | null;
    dispatched_at: string | null;
    delivered_at: string | null;
}

interface AdminOrder {
    order_number: string;
    status: string;
    payment_method: string | null;
    payment_status: string;
    placed_at: string | null;
    customer_name: string | null;
    customer_phone: string | null;
    customer_email: string | null;
    customer_address: string | null;
    customer_district: string | null;
    customer_country: string | null;
    customer_postcode: string | null;
    subtotal: number;
    shipping_total: number;
    discount_total: number;
    coupon_code: string | null;
    total: number;
    note: string | null;
    items: OrderItem[];
    payments: OrderPayment[];
    admin_shipment: AdminShipment | null;
    events: OrderEvent[];
    allowed_transitions: string[];
    can_confirm: boolean;
    is_dispatchable: boolean;
}

interface Props {
    order: AdminOrder;
    baseCurrency: string;
}

const EVENT_ICON: Record<string, LucideIcon> = {
    placed: Package,
    payment: CreditCard,
    status_changed: RefreshCw,
    shipment: Truck,
    comment: MessageSquare,
    note: MessageSquare,
};

function formatWhen(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '—';
}

function statusTone(status: string): string {
    switch (status) {
        case 'delivered':
            return 'bg-zt-teal/10 text-zt-teal';
        case 'shipped':
            return 'bg-blue-500/10 text-blue-600';
        case 'cancelled':
        case 'returned':
            return 'bg-red-500/10 text-red-600';
        case 'pending':
            return 'bg-amber-500/10 text-amber-600';
        default:
            return 'bg-zt-teal-mist text-zt-teal-deep';
    }
}

function paymentTone(status: string): string {
    switch (status) {
        case 'paid':
            return 'bg-emerald-500/10 text-emerald-600';
        case 'failed':
            return 'bg-red-500/10 text-red-600';
        case 'refunded':
            return 'bg-zt-muted/15 text-zt-muted';
        default:
            return 'bg-amber-500/10 text-amber-600';
    }
}

export default function AdminOrderShow({ order, baseCurrency }: Props) {
    const { t } = useTranslation();

    const commentForm = useForm({ body: '' });

    const postComment = (event: React.FormEvent) => {
        event.preventDefault();
        commentForm.post(`/admin/orders/${order.order_number}/comment`, {
            preserveScroll: true,
            onSuccess: () => commentForm.reset('body'),
        });
    };

    const changeStatus = (to: string) => {
        if (!to) return;
        router.patch(`/admin/orders/${order.order_number}/status`, { to }, { preserveScroll: true });
    };

    const confirmOrder = () => {
        router.post(`/admin/orders/${order.order_number}/confirm`, {}, { preserveScroll: true });
    };

    const sendToCourier = () => {
        router.post(`/admin/orders/${order.order_number}/dispatch`, {}, { preserveScroll: true });
    };

    const money = (amount: number) => formatMoney(amount, baseCurrency);

    return (
        <AdminLayout title={t('admin.order.title', { number: order.order_number })}>
            <div className="mt-4 flex flex-col gap-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link href="/admin/orders" className="text-zt-teal hover:text-zt-teal-deep text-sm font-medium">
                            ← {t('admin.orders.title')}
                        </Link>
                        <h1 className="font-display text-zt-ink text-2xl font-semibold sm:text-3xl">
                            {t('admin.order.title', { number: order.order_number })}
                        </h1>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusTone(order.status)}`}>
                            {t(`admin.orderstatus.${order.status}`)}
                        </span>
                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${paymentTone(order.payment_status)}`}>
                            {t(`admin.paystatus.${order.payment_status}`)}
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {order.can_confirm && (
                            <button
                                type="button"
                                onClick={confirmOrder}
                                className="bg-zt-teal hover:bg-zt-teal-deep inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-white"
                            >
                                <CheckCircle2 className="size-4" />
                                {t('admin.order.confirm')}
                            </button>
                        )}
                        {order.is_dispatchable && (
                            <button
                                type="button"
                                onClick={sendToCourier}
                                className="border-zt-sand text-zt-teal hover:bg-zt-teal-mist inline-flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium"
                            >
                                <Truck className="size-4" />
                                {t('admin.order.send_to_courier')}
                            </button>
                        )}
                        {order.allowed_transitions.length > 0 && (
                            <select
                                value=""
                                onChange={(event) => changeStatus(event.target.value)}
                                className="border-zt-sand rounded-lg border bg-white px-3 py-2 text-sm"
                                aria-label={t('admin.order.change_status')}
                            >
                                <option value="">{t('admin.order.change_status')}</option>
                                {order.allowed_transitions.map((to) => (
                                    <option key={to} value={to}>
                                        {t(`admin.orderstatus.${to}`)}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left column: items, totals, timeline */}
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        {/* Items */}
                        <section className="border-zt-sand rounded-xl border bg-white">
                            <h2 className="border-zt-sand text-zt-ink border-b px-5 py-3 text-sm font-semibold">{t('admin.order.items')}</h2>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-zt-muted text-left text-xs uppercase">
                                        <tr>
                                            <th className="px-5 py-2 font-medium">{t('admin.order.product')}</th>
                                            <th className="px-5 py-2 text-right font-medium">{t('admin.order.price')}</th>
                                            <th className="px-5 py-2 text-right font-medium">{t('admin.order.qty')}</th>
                                            <th className="px-5 py-2 text-right font-medium">{t('admin.order.line_total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {order.items.map((item, index) => (
                                            <tr key={index} className="border-zt-sand border-t">
                                                <td className="px-5 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="bg-zt-cream size-11 shrink-0 overflow-hidden rounded">
                                                            {item.image && <img src={item.image} alt="" className="size-full object-contain" />}
                                                        </div>
                                                        <div className="min-w-0">
                                                            <p className="text-zt-ink truncate font-medium">{item.name}</p>
                                                            {item.variant_title && <p className="text-zt-muted truncate text-xs">{item.variant_title}</p>}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="text-zt-ink px-5 py-3 text-right tabular-nums">{money(item.unit_price)}</td>
                                                <td className="text-zt-ink px-5 py-3 text-right tabular-nums">{item.quantity}</td>
                                                <td className="text-zt-ink px-5 py-3 text-right tabular-nums">{money(item.line_total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Totals */}
                            <dl className="border-zt-sand space-y-1.5 border-t px-5 py-4 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-zt-muted">{t('admin.order.subtotal')}</dt>
                                    <dd className="text-zt-ink tabular-nums">{money(order.subtotal)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-zt-muted">{t('admin.order.shipping')}</dt>
                                    <dd className="text-zt-ink tabular-nums">{money(order.shipping_total)}</dd>
                                </div>
                                {order.discount_total > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-zt-muted">
                                            {t('admin.order.discount')}
                                            {order.coupon_code && <span className="text-zt-muted ms-1 text-xs">({order.coupon_code})</span>}
                                        </dt>
                                        <dd className="tabular-nums text-emerald-600">−{money(order.discount_total)}</dd>
                                    </div>
                                )}
                                <div className="border-zt-sand flex justify-between border-t pt-2 text-base font-semibold">
                                    <dt className="text-zt-ink">{t('admin.order.total')}</dt>
                                    <dd className="text-zt-ink tabular-nums">{money(order.total)}</dd>
                                </div>
                            </dl>
                        </section>

                        {/* Timeline */}
                        <section className="border-zt-sand rounded-xl border bg-white">
                            <h2 className="border-zt-sand text-zt-ink border-b px-5 py-3 text-sm font-semibold">{t('admin.order.timeline')}</h2>

                            <form onSubmit={postComment} className="border-zt-sand border-b px-5 py-4">
                                <textarea
                                    value={commentForm.data.body}
                                    onChange={(event) => commentForm.setData('body', event.target.value)}
                                    placeholder={t('admin.order.comment_placeholder')}
                                    rows={2}
                                    className="border-zt-sand focus:border-zt-gold text-zt-ink w-full rounded-lg border bg-white px-3 py-2 text-sm outline-none"
                                />
                                {commentForm.errors.body && <p className="mt-1 text-xs text-red-600">{commentForm.errors.body}</p>}
                                <div className="mt-2 flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={commentForm.processing || commentForm.data.body.trim() === ''}
                                        className="bg-zt-teal hover:bg-zt-teal-deep rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                                    >
                                        {t('admin.order.post')}
                                    </button>
                                </div>
                            </form>

                            <ol className="px-5 py-4">
                                {order.events.length === 0 && <li className="text-zt-muted py-6 text-center text-sm">{t('admin.order.no_events')}</li>}
                                {order.events.map((event) => {
                                    const Icon = EVENT_ICON[event.type] ?? MessageSquare;
                                    return (
                                        <li key={event.id} className="flex gap-3 pb-5 last:pb-0">
                                            <span className="bg-zt-teal-mist text-zt-teal flex size-8 shrink-0 items-center justify-center rounded-full">
                                                <Icon className="size-4" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                    <p className="text-zt-ink text-sm font-medium">
                                                        {t(`admin.order.event.${event.type}`) === `admin.order.event.${event.type}`
                                                            ? event.title
                                                            : t(`admin.order.event.${event.type}`)}
                                                        {event.type === 'status_changed' && event.to_status && (
                                                            <span className="text-zt-muted ms-1 font-normal">
                                                                → {t(`admin.orderstatus.${event.to_status}`)}
                                                            </span>
                                                        )}
                                                    </p>
                                                    <time className="text-zt-muted text-xs">{formatWhen(event.created_at)}</time>
                                                </div>
                                                {event.body && <p className="text-zt-ink/80 mt-0.5 text-sm whitespace-pre-line">{event.body}</p>}
                                                {(event.actor_name || event.actor_type) && (
                                                    <p className="text-zt-muted mt-0.5 text-xs">
                                                        {event.actor_name ?? t(`admin.order.actor.${event.actor_type}`)}
                                                    </p>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                        </section>
                    </div>

                    {/* Right column: customer, address, payment, shipment */}
                    <div className="flex flex-col gap-6">
                        {/* Customer */}
                        <section className="border-zt-sand rounded-xl border bg-white p-5">
                            <h2 className="text-zt-muted mb-3 flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                <UserIcon className="size-4" /> {t('admin.order.customer')}
                            </h2>
                            <p className="text-zt-ink font-medium">{order.customer_name ?? '—'}</p>
                            {order.customer_email && <p className="text-zt-muted text-sm">{order.customer_email}</p>}
                            {order.customer_phone && <p className="text-zt-muted text-sm">{order.customer_phone}</p>}
                        </section>

                        {/* Shipping address */}
                        <section className="border-zt-sand rounded-xl border bg-white p-5">
                            <h2 className="text-zt-muted mb-3 text-xs font-semibold tracking-wide uppercase">{t('admin.order.shipping_address')}</h2>
                            <address className="text-zt-ink text-sm not-italic">
                                {order.customer_address && <p>{order.customer_address}</p>}
                                {order.customer_district && <p>{order.customer_district}</p>}
                                <p>
                                    {[order.customer_postcode, order.customer_country].filter(Boolean).join(', ') || '—'}
                                </p>
                            </address>
                            {order.note && (
                                <p className="text-zt-muted mt-3 border-t border-zt-sand pt-3 text-sm whitespace-pre-line">{order.note}</p>
                            )}
                        </section>

                        {/* Payment */}
                        <section className="border-zt-sand rounded-xl border bg-white p-5">
                            <h2 className="text-zt-muted mb-3 flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                <CreditCard className="size-4" /> {t('admin.order.payment')}
                            </h2>
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between gap-2">
                                    <dt className="text-zt-muted">{t('admin.order.payment_method')}</dt>
                                    <dd className="text-zt-ink text-end">{order.payment_method ?? '—'}</dd>
                                </div>
                                <div className="flex justify-between gap-2">
                                    <dt className="text-zt-muted">{t('admin.order.payment_status')}</dt>
                                    <dd>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${paymentTone(order.payment_status)}`}>
                                            {t(`admin.paystatus.${order.payment_status}`)}
                                        </span>
                                    </dd>
                                </div>
                                {order.payments.map((payment, index) => (
                                    <div key={index} className="flex justify-between gap-2">
                                        <dt className="text-zt-muted">{payment.gateway}</dt>
                                        <dd className="text-zt-ink text-end break-all">
                                            {payment.gateway_transaction_id ?? payment.reference ?? money(payment.amount)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        {/* Shipment */}
                        <section className="border-zt-sand rounded-xl border bg-white p-5">
                            <h2 className="text-zt-muted mb-3 flex items-center gap-2 text-xs font-semibold tracking-wide uppercase">
                                <Truck className="size-4" /> {t('admin.order.shipment')}
                            </h2>
                            {order.admin_shipment ? (
                                <dl className="space-y-1.5 text-sm">
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-zt-muted">{t('admin.order.courier')}</dt>
                                        <dd className="text-zt-ink text-end">{order.admin_shipment.courier ?? '—'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-zt-muted">{t('admin.order.consignment')}</dt>
                                        <dd className="text-zt-ink text-end break-all">{order.admin_shipment.consignment_id ?? '—'}</dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt className="text-zt-muted">{t('admin.order.tracking')}</dt>
                                        <dd className="text-zt-ink text-end break-all">{order.admin_shipment.tracking_code ?? '—'}</dd>
                                    </div>
                                    {order.admin_shipment.status && (
                                        <div className="flex justify-between gap-2">
                                            <dt className="text-zt-muted">{t('admin.order.status')}</dt>
                                            <dd className="text-zt-ink text-end">{order.admin_shipment.status}</dd>
                                        </div>
                                    )}
                                </dl>
                            ) : (
                                <p className="text-zt-muted text-sm">—</p>
                            )}
                            {order.is_dispatchable && (
                                <button
                                    type="button"
                                    onClick={sendToCourier}
                                    className="border-zt-sand text-zt-teal hover:bg-zt-teal-mist mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
                                >
                                    <Truck className="size-4" />
                                    {t('admin.order.send_to_courier')}
                                </button>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
