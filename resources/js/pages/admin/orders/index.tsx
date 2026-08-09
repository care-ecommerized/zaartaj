import { Link, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';
import { formatMoney, type Paginated } from '@/lib/shop/catalog';

interface OrderRow {
    order_number: string;
    customer_name: string | null;
    customer_email: string | null;
    status: string;
    payment_status: string;
    total: number;
    country: string | null;
    placed_at: string | null;
    shipment: { status: string; tracking_code: string | null } | null;
}

interface Filters {
    status?: string | null;
    payment_status?: string | null;
    search?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    region?: string | null;
}

interface Props {
    orders: Paginated<OrderRow>;
    filters: Filters;
    statuses: string[];
    paymentStatuses: string[];
    statusSummary: Record<string, number>;
    salesCards: { realized: number; upcoming: number; total: number };
    baseCurrency: string;
}

/** The five-bucket summary strip, drawn from the raw per-status counts. */
const SUMMARY_STRIP: { key: string; statuses: string[] }[] = [
    { key: 'pending', statuses: ['pending'] },
    { key: 'confirmed', statuses: ['confirmed'] },
    { key: 'processing', statuses: ['packed'] },
    { key: 'shipped', statuses: ['shipped'] },
    { key: 'delivered', statuses: ['delivered'] },
    { key: 'cancelled', statuses: ['cancelled', 'returned'] },
];

const REGIONS = ['local', 'all', 'international'] as const;

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
            // confirmed / packed
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

function formatWhen(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

export default function AdminOrdersIndex({ orders, filters, statuses, paymentStatuses, statusSummary, salesCards, baseCurrency }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (changes: Record<string, string | undefined>) => {
        const next: Record<string, string | undefined> = {
            status: filters.status ?? undefined,
            payment_status: filters.payment_status ?? undefined,
            search: filters.search ?? undefined,
            date_from: filters.date_from ?? undefined,
            date_to: filters.date_to ?? undefined,
            region: filters.region ?? undefined,
            ...changes,
        };

        router.get('/admin/orders', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const region = filters.region ?? 'all';

    const exportQuery = new URLSearchParams(
        Object.entries({
            status: filters.status,
            payment_status: filters.payment_status,
            search: filters.search,
            date_from: filters.date_from,
            date_to: filters.date_to,
            region: filters.region,
        }).filter(([, value]) => value != null && value !== '') as [string, string][],
    ).toString();

    const cards: { key: keyof typeof salesCards; label: string }[] = [
        { key: 'realized', label: t('admin.orders.cards.realized') },
        { key: 'upcoming', label: t('admin.orders.cards.upcoming') },
        { key: 'total', label: t('admin.orders.cards.total') },
    ];

    return (
        <AdminLayout
            title={t('admin.orders.title')}
            heading={t('admin.orders.title')}
            actions={
                <a
                    href={`/admin/orders/export${exportQuery ? `?${exportQuery}` : ''}`}
                    className="border-zt-sand text-zt-teal hover:bg-zt-teal-mist inline-flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium"
                >
                    <Download className="size-4" />
                    {t('admin.orders.export')}
                </a>
            }
        >
            <div className="mt-4 flex flex-col gap-6">
                {/* Sales cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {cards.map((card) => (
                        <div key={card.key} className="border-zt-sand rounded-xl border bg-white p-5">
                            <p className="text-zt-muted text-xs font-medium tracking-wide uppercase">{card.label}</p>
                            <p className="text-zt-ink mt-1 text-2xl font-semibold tabular-nums">
                                {formatMoney(salesCards[card.key], baseCurrency)}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Status summary strip */}
                <div className="border-zt-sand grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-white sm:grid-cols-3 lg:grid-cols-6">
                    {SUMMARY_STRIP.map((bucket) => {
                        const count = bucket.statuses.reduce((sum, s) => sum + (statusSummary[s] ?? 0), 0);
                        const active = filters.status === bucket.statuses[0];

                        return (
                            <button
                                key={bucket.key}
                                type="button"
                                onClick={() => applyFilter({ status: active ? undefined : bucket.statuses[0] })}
                                className={
                                    'flex flex-col items-start gap-1 px-4 py-3 text-start transition-colors ' +
                                    (active ? 'bg-zt-teal-mist' : 'hover:bg-zt-cream/60')
                                }
                                aria-pressed={active}
                            >
                                <span className="text-zt-muted text-xs font-medium tracking-wide uppercase">
                                    {t(`admin.orders.summary.${bucket.key}`)}
                                </span>
                                <span className="text-zt-ink text-xl font-semibold tabular-nums">{count}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ search: search || undefined });
                        }}
                        className="border-zt-sand focus-within:border-zt-gold flex items-center gap-2 rounded-lg border bg-white px-3 py-2"
                    >
                        <Search className="text-zt-muted size-4 shrink-0" />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('admin.orders.search_placeholder')}
                            className="text-zt-ink placeholder:text-zt-muted w-64 bg-transparent text-sm outline-none"
                        />
                    </form>

                    <select
                        value={filters.status ?? ''}
                        onChange={(event) => applyFilter({ status: event.target.value || undefined })}
                        className="border-zt-sand rounded-lg border bg-white px-3 py-2 text-sm"
                    >
                        <option value="">{t('admin.orders.filter.all_statuses')}</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {t(`admin.orderstatus.${status}`)}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.payment_status ?? ''}
                        onChange={(event) => applyFilter({ payment_status: event.target.value || undefined })}
                        className="border-zt-sand rounded-lg border bg-white px-3 py-2 text-sm"
                    >
                        <option value="">{t('admin.orders.filter.all_payments')}</option>
                        {paymentStatuses.map((status) => (
                            <option key={status} value={status}>
                                {t(`admin.paystatus.${status}`)}
                            </option>
                        ))}
                    </select>

                    <label className="text-zt-muted flex items-center gap-1.5 text-sm">
                        {t('admin.orders.filter.date_from')}
                        <input
                            type="date"
                            value={filters.date_from ?? ''}
                            onChange={(event) => applyFilter({ date_from: event.target.value || undefined })}
                            className="border-zt-sand rounded-lg border bg-white px-2 py-1.5 text-sm"
                        />
                    </label>
                    <label className="text-zt-muted flex items-center gap-1.5 text-sm">
                        {t('admin.orders.filter.date_to')}
                        <input
                            type="date"
                            value={filters.date_to ?? ''}
                            onChange={(event) => applyFilter({ date_to: event.target.value || undefined })}
                            className="border-zt-sand rounded-lg border bg-white px-2 py-1.5 text-sm"
                        />
                    </label>

                    {/* Region toggle */}
                    <div className="border-zt-sand ms-auto flex items-center rounded-lg border bg-white p-0.5 text-xs font-medium">
                        {REGIONS.map((code) => (
                            <button
                                key={code}
                                type="button"
                                onClick={() => applyFilter({ region: code === 'all' ? undefined : code })}
                                className={
                                    'rounded-md px-3 py-1.5 transition-colors ' +
                                    (region === code ? 'bg-zt-teal text-white' : 'text-zt-muted hover:text-zt-ink')
                                }
                                aria-pressed={region === code}
                            >
                                {t(`admin.orders.region.${code}`)}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Orders table */}
                <div className="border-zt-sand overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-zt-sand text-zt-muted border-b text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">{t('admin.orders.col.order')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.orders.col.customer')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.orders.col.status')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.orders.col.payment')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('admin.orders.col.total')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.orders.col.date')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('admin.orders.col.actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
                                <tr key={order.order_number} className="border-zt-sand hover:bg-zt-cream/60 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/orders/${order.order_number}`} className="text-zt-teal font-medium hover:underline">
                                            {order.order_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="text-zt-ink truncate">{order.customer_name ?? '—'}</p>
                                            <p className="text-zt-muted truncate text-xs">{order.customer_email ?? '—'}</p>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusTone(order.status)}`}>
                                            {t(`admin.orderstatus.${order.status}`)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${paymentTone(order.payment_status)}`}>
                                            {t(`admin.paystatus.${order.payment_status}`)}
                                        </span>
                                    </td>
                                    <td className="text-zt-ink px-4 py-3 text-right tabular-nums">{formatMoney(order.total, baseCurrency)}</td>
                                    <td className="text-zt-muted px-4 py-3">{formatWhen(order.placed_at)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <Link href={`/admin/orders/${order.order_number}`} className="text-zt-teal hover:text-zt-teal-deep text-sm font-medium">
                                            {t('admin.orders.view')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}

                            {orders.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-zt-muted px-4 py-12 text-center">
                                        {t('admin.orders.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {orders.last_page > 1 && (
                    <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-2">
                        {orders.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={
                                    'rounded-lg border px-3 py-1.5 text-xs ' +
                                    (link.active
                                        ? 'border-zt-teal bg-zt-teal text-white'
                                        : link.url
                                          ? 'border-zt-sand hover:bg-zt-cream'
                                          : 'border-zt-sand/50 text-zt-muted/50 pointer-events-none')
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AdminLayout>
    );
}
