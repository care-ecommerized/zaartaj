import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { type FormEvent, useRef } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ImportStatusBadge, type ImportStatus } from './status-badge';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Products', href: '/admin/products' },
    { title: 'Imports', href: '/admin/products/imports' },
];

interface ImportRow {
    id: number;
    filename: string;
    status: ImportStatus;
    rows_read: number;
    products_created: number;
    products_updated: number;
    images_queued: number;
    failures: number;
    error: string | null;
    uploaded_by: string | null;
    created_at: string;
    finished_at: string | null;
}

interface Props {
    imports: { data: ImportRow[]; links: { url: string | null; label: string; active: boolean }[]; last_page: number };
}

export default function ImportsIndex({ imports }: Props) {
    const { props } = usePage<{ status?: string }>();
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post('/admin/products/imports', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Product imports" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Import products</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Upload a Shopify product export (CSV). Existing products are matched on their handle and updated, so re-uploading the same file
                        is safe.
                    </p>
                </div>

                {props.status && (
                    <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                        {props.status}
                    </div>
                )}

                <form onSubmit={submit} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-6">
                    <label htmlFor="file" className="text-sm font-medium">
                        Shopify export
                    </label>

                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <input
                            id="file"
                            ref={fileInput}
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            className="border-sidebar-border/70 file:bg-muted flex-1 rounded-lg border px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:px-3 file:py-1.5 file:text-sm"
                        />

                        <button
                            type="submit"
                            disabled={!form.data.file || form.processing}
                            className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50"
                        >
                            <UploadCloud className="size-4" />
                            {form.processing ? `Uploading… ${form.progress?.percentage ?? 0}%` : 'Upload'}
                        </button>
                    </div>

                    {form.errors.file && <p className="mt-2 text-sm text-red-600">{form.errors.file}</p>}

                    <p className="text-muted-foreground mt-3 text-xs">
                        Parsing runs on the queue, so make sure a worker is running (<code>php artisan queue:work</code>). Up to 50&nbsp;MB.
                    </p>
                </form>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">File</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Created</th>
                                <th className="px-4 py-3 text-right font-medium">Updated</th>
                                <th className="px-4 py-3 text-right font-medium">Images</th>
                                <th className="px-4 py-3 text-right font-medium">Failures</th>
                                <th className="px-4 py-3 font-medium">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            {imports.data.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={() => router.visit(`/admin/products/imports/${row.id}`)}
                                    className="border-sidebar-border/70 hover:bg-muted/40 cursor-pointer border-t"
                                >
                                    <td className="px-4 py-3">
                                        <Link href={`/admin/products/imports/${row.id}`} className="font-medium hover:underline">
                                            {row.filename}
                                        </Link>
                                        <p className="text-muted-foreground text-xs">{new Date(row.created_at).toLocaleString()}</p>
                                    </td>
                                    <td className="px-4 py-3">
                                        <ImportStatusBadge status={row.status} />
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{row.products_created}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{row.products_updated}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{row.images_queued}</td>
                                    <td className={`px-4 py-3 text-right tabular-nums ${row.failures > 0 ? 'text-red-600' : ''}`}>{row.failures}</td>
                                    <td className="text-muted-foreground px-4 py-3">{row.uploaded_by ?? '—'}</td>
                                </tr>
                            ))}

                            {imports.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-12 text-center">
                                        Nothing imported yet.
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
