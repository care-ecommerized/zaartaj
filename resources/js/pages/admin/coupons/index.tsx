import { Head, Link, router } from '@inertiajs/react';
import { Plus, Ticket } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Coupons', href: '/admin/coupons' }];

interface Coupon {
    id: number;
    code: string;
    type: string;
    value: number;
    currency: string | null;
    min_subtotal: number | null;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | null;
    per_user_limit: number | null;
    times_used: number;
    is_active: boolean;
}

interface Props {
    coupons: Coupon[];
}

function formatValue(coupon: Coupon): string {
    if (coupon.type === 'percent') {
        return `${coupon.value}% off`;
    }

    return `${coupon.currency ?? 'AED'} ${coupon.value.toFixed(2)}`;
}

function formatWindow(coupon: Coupon): string {
    const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString() : null);
    const from = fmt(coupon.starts_at);
    const to = fmt(coupon.ends_at);

    if (!from && !to) return 'Always';
    return `${from ?? '—'} → ${to ?? '—'}`;
}

export default function AdminCouponIndex({ coupons }: Props) {
    const remove = (coupon: Coupon) => {
        if (confirm(`Delete the "${coupon.code}" coupon?`)) {
            router.delete(`/admin/coupons/${coupon.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Coupons" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Coupons</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{coupons.length} codes. Fixed amounts convert to the AED base at checkout.</p>
                    </div>

                    <Link
                        href="/admin/coupons/create"
                        className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                    >
                        <Plus className="size-4" />
                        New coupon
                    </Link>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Value</th>
                                <th className="px-4 py-3 font-medium">Min (AED)</th>
                                <th className="px-4 py-3 font-medium">Window</th>
                                <th className="px-4 py-3 text-right font-medium">Usage</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {coupons.map((coupon) => (
                                <tr key={coupon.id} className="border-sidebar-border/70 hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/coupons/${coupon.id}/edit`} className="inline-flex items-center gap-2 font-medium hover:underline">
                                            <Ticket className="size-4" />
                                            {coupon.code}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{formatValue(coupon)}</td>
                                    <td className="text-muted-foreground px-4 py-3 tabular-nums">{coupon.min_subtotal ?? '—'}</td>
                                    <td className="text-muted-foreground px-4 py-3">{formatWindow(coupon)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {coupon.times_used}
                                        {coupon.usage_limit !== null ? ` / ${coupon.usage_limit}` : ''}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                coupon.is_active
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {coupon.is_active ? 'active' : 'inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={`/admin/coupons/${coupon.id}/edit`} className="text-primary text-xs hover:underline">
                                                Edit
                                            </Link>
                                            <button type="button" onClick={() => remove(coupon)} className="text-xs text-red-600 hover:underline">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {coupons.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-12 text-center">
                                        No coupons yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
