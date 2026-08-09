import { Link, router } from '@inertiajs/react';
import { Boxes, PackageCheck, ShoppingCart, TrendingUp, Users, type LucideIcon } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';
import { formatMoney } from '@/lib/shop/catalog';

interface Pipeline {
    new: number;
    processing: number;
    shipped: number;
    delivered: number;
    cancelled: number;
}

interface Kpis {
    total_orders: number;
    realized_sales: number;
    active_products: number;
    customers: number;
}

interface RecentOrder {
    order_number: string;
    customer_name: string | null;
    status: string;
    payment_status: string;
    total: number;
    placed_at: string | null;
}

interface Props {
    range: string;
    ranges: string[];
    pipeline: Pipeline;
    kpis: Kpis;
    recentOrders: RecentOrder[];
    baseCurrency: string;
}

/** Pipeline stage → the /admin/orders?status= filter value it links to. */
const STAGE_STATUS: Record<keyof Pipeline, string> = {
    new: 'pending',
    processing: 'confirmed',
    shipped: 'shipped',
    delivered: 'delivered',
    cancelled: 'cancelled',
};

const STAGE_ORDER: (keyof Pipeline)[] = ['new', 'processing', 'shipped', 'delivered', 'cancelled'];

/** A subtle accent per pipeline stage, all drawn from the brand family. */
const STAGE_BAR: Record<keyof Pipeline, string> = {
    new: 'bg-zt-gold',
    processing: 'bg-zt-teal-soft',
    shipped: 'bg-zt-teal',
    delivered: 'bg-zt-teal-deep',
    cancelled: 'bg-zt-muted',
};

function formatWhen(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

function statusTone(status: string): string {
    switch (status) {
        case 'delivered':
            return 'bg-zt-teal/10 text-zt-teal';
        case 'cancelled':
        case 'returned':
            return 'bg-red-500/10 text-red-600';
        case 'pending':
            return 'bg-zt-gold/15 text-zt-gold';
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

export default function AdminDashboard({ range, ranges, pipeline, kpis, recentOrders, baseCurrency }: Props) {
    const { t } = useTranslation();

    const setRange = (next: string) => {
        router.get('/admin', { range: next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const pipelineTotal = STAGE_ORDER.reduce((sum, stage) => sum + pipeline[stage], 0);

    const kpiCards: { key: string; label: string; value: string; icon: LucideIcon }[] = [
        { key: 'orders', label: t('admin.dashboard.total_orders'), value: kpis.total_orders.toLocaleString(), icon: ShoppingCart },
        {
            key: 'sales',
            label: t('admin.dashboard.realized_sales'),
            value: formatMoney(kpis.realized_sales, baseCurrency),
            icon: TrendingUp,
        },
        { key: 'products', label: t('admin.dashboard.active_products'), value: kpis.active_products.toLocaleString(), icon: Boxes },
        { key: 'customers', label: t('admin.dashboard.customers'), value: kpis.customers.toLocaleString(), icon: Users },
    ];

    return (
        <AdminLayout
            title={t('admin.dashboard.title')}
            heading={t('admin.dashboard.title')}
            actions={
                <div className="border-zt-sand flex flex-wrap items-center rounded-lg border bg-white p-0.5 text-xs font-medium">
                    {ranges.map((option) => (
                        <button
                            key={option}
                            type="button"
                            onClick={() => setRange(option)}
                            className={
                                'rounded-md px-3 py-1.5 transition-colors ' +
                                (range === option ? 'bg-zt-teal text-white' : 'text-zt-muted hover:text-zt-ink')
                            }
                            aria-pressed={range === option}
                        >
                            {t(`admin.dashboard.range.${option}`)}
                        </button>
                    ))}
                </div>
            }
        >
            <div className="mt-4 flex flex-col gap-8">
                {/* Order pipeline */}
                <section>
                    <h2 className="text-zt-muted mb-3 text-xs font-semibold tracking-[0.18em] uppercase">
                        {t('admin.dashboard.pipeline')}
                    </h2>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {STAGE_ORDER.map((stage) => {
                            const count = pipeline[stage];
                            const width = pipelineTotal > 0 ? Math.round((count / pipelineTotal) * 100) : 0;

                            return (
                                <Link
                                    key={stage}
                                    href={`/admin/orders?status=${STAGE_STATUS[stage]}`}
                                    className="border-zt-sand hover:border-zt-gold group rounded-xl border bg-white p-4 transition-colors"
                                >
                                    <p className="text-zt-muted text-xs font-medium tracking-wide uppercase">
                                        {t(`admin.dashboard.stage.${stage}`)}
                                    </p>
                                    <p className="text-zt-ink mt-1 text-2xl font-semibold tabular-nums">{count}</p>
                                    <div className="bg-zt-sand mt-3 h-1.5 w-full overflow-hidden rounded-full">
                                        <div className={`h-full rounded-full ${STAGE_BAR[stage]}`} style={{ width: `${width}%` }} />
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                </section>

                {/* KPI cards */}
                <section>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {kpiCards.map((card) => {
                            const Icon = card.icon;
                            return (
                                <div key={card.key} className="border-zt-sand flex items-center gap-4 rounded-xl border bg-white p-5">
                                    <span className="bg-zt-teal-mist text-zt-teal flex size-11 shrink-0 items-center justify-center rounded-lg">
                                        <Icon className="size-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="text-zt-muted truncate text-xs font-medium tracking-wide uppercase">{card.label}</p>
                                        <p className="text-zt-ink mt-0.5 truncate text-xl font-semibold tabular-nums">{card.value}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* Recent orders */}
                <section>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-zt-muted text-xs font-semibold tracking-[0.18em] uppercase">
                            {t('admin.dashboard.recent_orders')}
                        </h2>
                        <Link href="/admin/orders" className="text-zt-teal hover:text-zt-teal-deep text-sm font-medium">
                            {t('admin.dashboard.view_all')} →
                        </Link>
                    </div>

                    <div className="border-zt-sand overflow-x-auto rounded-xl border bg-white">
                        <table className="w-full text-sm">
                            <thead className="border-zt-sand text-zt-muted border-b text-left text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('admin.dashboard.col.order')}</th>
                                    <th className="px-4 py-3 font-medium">{t('admin.dashboard.col.customer')}</th>
                                    <th className="px-4 py-3 font-medium">{t('admin.dashboard.col.status')}</th>
                                    <th className="px-4 py-3 font-medium">{t('admin.dashboard.col.payment')}</th>
                                    <th className="px-4 py-3 text-right font-medium">{t('admin.dashboard.col.total')}</th>
                                    <th className="px-4 py-3 font-medium">{t('admin.dashboard.col.placed')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders.map((order) => (
                                    <tr key={order.order_number} className="border-zt-sand hover:bg-zt-cream/60 border-t">
                                        <td className="text-zt-ink px-4 py-3 font-medium">{order.order_number}</td>
                                        <td className="text-zt-ink/80 px-4 py-3">{order.customer_name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${statusTone(order.status)}`}>
                                                {t(`admin.dashboard.stage_status.${order.status}`)}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${paymentTone(order.payment_status)}`}>
                                                {order.payment_status}
                                            </span>
                                        </td>
                                        <td className="text-zt-ink px-4 py-3 text-right tabular-nums">{formatMoney(order.total, baseCurrency)}</td>
                                        <td className="text-zt-muted px-4 py-3">{formatWhen(order.placed_at)}</td>
                                    </tr>
                                ))}

                                {recentOrders.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-zt-muted px-4 py-12 text-center">
                                            {t('admin.dashboard.no_orders')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
