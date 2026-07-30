export type ImportStatus = 'pending' | 'processing' | 'completed' | 'completed_with_errors' | 'failed';

const STYLES: Record<ImportStatus, { label: string; className: string }> = {
    pending: { label: 'Queued', className: 'bg-muted text-muted-foreground' },
    processing: { label: 'Processing', className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400' },
    completed: { label: 'Completed', className: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
    completed_with_errors: { label: 'Completed with errors', className: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
    failed: { label: 'Failed', className: 'bg-red-500/10 text-red-600 dark:text-red-400' },
};

export function ImportStatusBadge({ status }: { status: ImportStatus }) {
    const style = STYLES[status] ?? STYLES.pending;

    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${style.className}`}>{style.label}</span>;
}
