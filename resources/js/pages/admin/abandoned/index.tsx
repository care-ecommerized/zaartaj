import { Head } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Abandoned', href: '/admin/abandoned' }];

interface Session {
    id: number;
    email: string | null;
    phone: string | null;
    items_count: number;
    subtotal: number | null;
    subtotal_presentment: number | null;
    status: string;
    recovered: boolean;
    last_activity_at: string | null;
    reminder_sent_at: string | null;
}

interface Metrics {
    open: number;
    converted: number;
    abandoned: number;
    conversion_rate: number | null;
}

interface Props {
    sessions: Session[];
    baseCurrency: string;
    presentmentCurrency: string;
    metrics: Metrics;
}

function formatMoney(amount: number | null, code: string): string {
    if (amount === null) return '—';
    return `${code} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatWhen(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '—';
}

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
            <p className="text-muted-foreground text-xs uppercase">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export default function AdminAbandonedIndex({ sessions, baseCurrency, presentmentCurrency, metrics }: Props) {
    const showPresentment = presentmentCurrency !== baseCurrency;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Abandoned checkouts" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Abandoned checkouts</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Checkouts captured before submission. Recovered ones followed a reminder back.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Open" value={metrics.open} />
                    <StatCard label="Converted" value={metrics.converted} />
                    <StatCard label="Abandoned" value={metrics.abandoned} />
                    <StatCard label="Conversion" value={metrics.conversion_rate === null ? '—' : `${metrics.conversion_rate}%`} />
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Contact</th>
                                <th className="px-4 py-3 text-right font-medium">Items</th>
                                <th className="px-4 py-3 text-right font-medium">Subtotal ({baseCurrency})</th>
                                {showPresentment && <th className="px-4 py-3 text-right font-medium">Subtotal ({presentmentCurrency})</th>}
                                <th className="px-4 py-3 font-medium">Last activity</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sessions.map((session) => (
                                <tr key={session.id} className="border-sidebar-border/70 hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <ShoppingCart className="text-muted-foreground size-4" />
                                            <div>
                                                <p className="font-medium">{session.email ?? '—'}</p>
                                                {session.phone && <p className="text-muted-foreground text-xs">{session.phone}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{session.items_count}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatMoney(session.subtotal, baseCurrency)}</td>
                                    {showPresentment && (
                                        <td className="text-muted-foreground px-4 py-3 text-right tabular-nums">
                                            {formatMoney(session.subtotal_presentment, presentmentCurrency)}
                                        </td>
                                    )}
                                    <td className="text-muted-foreground px-4 py-3">{formatWhen(session.last_activity_at)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                    session.status === 'abandoned'
                                                        ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                        : 'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {session.status}
                                            </span>
                                            {session.recovered && (
                                                <span className="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    recovered
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {sessions.length === 0 && (
                                <tr>
                                    <td colSpan={showPresentment ? 6 : 5} className="text-muted-foreground px-4 py-12 text-center">
                                        No abandoned checkouts yet.
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
