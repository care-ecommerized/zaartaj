import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ImportStatusBadge, type ImportStatus } from './status-badge';

interface FailedRow {
    id: number;
    line_number: number;
    handle: string | null;
    message: string;
}

interface Props {
    import: {
        id: number;
        filename: string;
        status: ImportStatus;
        rows_read: number;
        products_created: number;
        products_updated: number;
        images_queued: number;
        failures: number;
        error: string | null;
        started_at: string | null;
        finished_at: string | null;
    };
    failedRows: { data: FailedRow[]; total: number };
}

export default function ImportShow({ import: record, failedRows }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Products', href: '/admin/products' },
        { title: 'Imports', href: '/admin/products/imports' },
        { title: record.filename, href: `/admin/products/imports/${record.id}` },
    ];

    const running = record.status === 'pending' || record.status === 'processing';

    /*
     * Parsing happens on the queue, so a freshly uploaded import has nothing to show
     * yet. Poll while it is in flight and stop as soon as it settles.
     */
    useEffect(() => {
        if (!running) {
            return;
        }

        const timer = window.setInterval(() => router.reload({ only: ['import', 'failedRows'] }), 2000);

        return () => window.clearInterval(timer);
    }, [running]);

    const stats = [
        { label: 'Rows read', value: record.rows_read },
        { label: 'Products created', value: record.products_created },
        { label: 'Products updated', value: record.products_updated },
        { label: 'Images queued', value: record.images_queued },
        { label: 'Failures', value: record.failures, alert: record.failures > 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Import — ${record.filename}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">{record.filename}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {running ? 'Processing — this page refreshes itself.' : `Finished ${record.finished_at ? new Date(record.finished_at).toLocaleString() : ''}`}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <ImportStatusBadge status={record.status} />
                        <Link href="/admin/products" className="border-sidebar-border/70 rounded-lg border px-4 py-2 text-sm hover:underline">
                            View products
                        </Link>
                    </div>
                </div>

                {record.error && (
                    <div className="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                        <p className="font-medium">The file could not be processed</p>
                        <p className="mt-1">{record.error}</p>
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    {stats.map((stat) => (
                        <div key={stat.label} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs uppercase">{stat.label}</p>
                            <p className={`mt-2 text-2xl font-semibold tabular-nums ${stat.alert ? 'text-red-600' : ''}`}>{stat.value}</p>
                        </div>
                    ))}
                </div>

                {failedRows.data.length > 0 && (
                    <div>
                        <h2 className="text-lg font-medium">Rows that did not import ({failedRows.total})</h2>

                        <div className="border-sidebar-border/70 dark:border-sidebar-border mt-3 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Line</th>
                                        <th className="px-4 py-3 font-medium">Handle</th>
                                        <th className="px-4 py-3 font-medium">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {failedRows.data.map((row) => (
                                        <tr key={row.id} className="border-sidebar-border/70 border-t align-top">
                                            <td className="px-4 py-3 tabular-nums">{row.line_number}</td>
                                            <td className="px-4 py-3 font-mono text-xs">{row.handle ?? '—'}</td>
                                            <td className="px-4 py-3 text-red-600">{row.message}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
