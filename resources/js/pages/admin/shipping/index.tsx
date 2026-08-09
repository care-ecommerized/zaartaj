import { Head, Link, router } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Shipping', href: '/admin/shipping' }];

interface Zone {
    id: number;
    name: string;
    countries: string[];
    is_catch_all: boolean;
    priority: number;
    is_active: boolean;
    rates_count: number;
}

interface Props {
    zones: Zone[];
}

export default function AdminShippingIndex({ zones }: Props) {
    const remove = (zone: Zone) => {
        if (confirm(`Delete the "${zone.name}" zone and its rates?`)) {
            router.delete(`/admin/shipping/${zone.id}`, { preserveScroll: true });
        }
    };

    return (
        <AdminLayout>
            <Head title="Shipping zones" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Shipping zones</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{zones.length} zones. Amounts are in the AED base currency.</p>
                    </div>

                    <Link
                        href="/admin/shipping/create"
                        className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                    >
                        <Plus className="size-4" />
                        New zone
                    </Link>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Zone</th>
                                <th className="px-4 py-3 font-medium">Countries</th>
                                <th className="px-4 py-3 text-right font-medium">Priority</th>
                                <th className="px-4 py-3 text-right font-medium">Rates</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {zones.map((zone) => (
                                <tr key={zone.id} className="border-sidebar-border/70 hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/shipping/${zone.id}/edit`} className="inline-flex items-center gap-2 font-medium hover:underline">
                                            <Truck className="size-4" />
                                            {zone.name}
                                        </Link>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {zone.is_catch_all ? <span className="italic">Rest of world (catch-all)</span> : zone.countries.join(', ')}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{zone.priority}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{zone.rates_count}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                zone.is_active
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {zone.is_active ? 'active' : 'inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-3">
                                            <Link href={`/admin/shipping/${zone.id}/edit`} className="text-primary text-xs hover:underline">
                                                Edit
                                            </Link>
                                            <button type="button" onClick={() => remove(zone)} className="text-xs text-red-600 hover:underline">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {zones.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground px-4 py-12 text-center">
                                        No shipping zones yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
