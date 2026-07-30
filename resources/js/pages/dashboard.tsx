import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/lib/i18n';
import { formatTaka } from '@/lib/shop/catalog';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MapPin, Package } from 'lucide-react';

interface RecentOrder {
    order_number: string;
    placed_at: string | null;
    status: string;
    payment_status: string;
    total: number;
}

interface DashboardProps {
    recentOrders: RecentOrder[];
    ordersCount: number;
    addressesCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString();
}

export default function Dashboard({ recentOrders, ordersCount, addressesCount }: DashboardProps) {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('account.home.title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-2">
                    <Link
                        href="/account/orders"
                        className="border-sidebar-border/70 dark:border-sidebar-border flex items-center gap-4 rounded-xl border p-6 transition-colors hover:bg-muted"
                    >
                        <Package className="size-8 opacity-70" />
                        <div>
                            <p className="text-2xl font-semibold">{ordersCount}</p>
                            <p className="text-sm text-muted-foreground">{t('account.orders.title')}</p>
                        </div>
                    </Link>
                    <Link
                        href="/settings/addresses"
                        className="border-sidebar-border/70 dark:border-sidebar-border flex items-center gap-4 rounded-xl border p-6 transition-colors hover:bg-muted"
                    >
                        <MapPin className="size-8 opacity-70" />
                        <div>
                            <p className="text-2xl font-semibold">{addressesCount}</p>
                            <p className="text-sm text-muted-foreground">{t('account.addresses.title')}</p>
                        </div>
                    </Link>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border flex-1 rounded-xl border p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-medium">{t('account.home.recent_orders')}</h2>
                        <Link href="/account/orders" className="text-sm text-muted-foreground underline">
                            {t('account.home.view_all')}
                        </Link>
                    </div>

                    {recentOrders.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('account.orders.empty')}</p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {recentOrders.map((order) => (
                                <li key={order.order_number}>
                                    <Link
                                        href={`/account/orders/${order.order_number}`}
                                        className="flex items-center justify-between gap-4 py-3 text-sm transition-colors hover:text-primary"
                                    >
                                        <div>
                                            <p className="font-medium">{order.order_number}</p>
                                            <p className="text-xs text-muted-foreground">{formatDate(order.placed_at)}</p>
                                        </div>
                                        <div className="text-right">
                                            <p>{formatTaka(order.total)}</p>
                                            <p className="text-xs text-muted-foreground">{t(`order.status.${order.status}`)}</p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
