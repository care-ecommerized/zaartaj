import { Head, Link, router } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { formatTaka, type Paginated } from '@/lib/shop/catalog';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Products', href: '/admin/products' }];

interface AdminProduct {
    id: number;
    handle: string;
    title: string;
    brand: string | null;
    status: string;
    category: string | null;
    variants_count: number;
    min_price: string | null;
    max_price: string | null;
    total_inventory: number;
    image: string | null;
}

interface Props {
    products: Paginated<AdminProduct>;
    filters: { search?: string; status?: string; category?: number };
    statuses: string[];
    categories: { id: number; name: string; path: string | null }[];
}

export default function AdminProductsIndex({ products, filters, statuses, categories }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilter = (changes: Record<string, string | undefined>) => {
        router.get('/admin/products', { ...filters, ...changes }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Products</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{products.total} in the catalogue</p>
                    </div>

                    <Link
                        href="/admin/products/imports"
                        className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium"
                    >
                        <UploadCloud className="size-4" />
                        Import CSV
                    </Link>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ search: search || undefined });
                        }}
                    >
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search title, handle or brand"
                            className="border-sidebar-border/70 w-72 rounded-lg border px-3 py-2 text-sm"
                        />
                    </form>

                    <select
                        value={filters.status ?? ''}
                        onChange={(event) => applyFilter({ status: event.target.value || undefined })}
                        className="border-sidebar-border/70 bg-background rounded-lg border px-3 py-2 text-sm"
                    >
                        <option value="">All statuses</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {status}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.category ?? ''}
                        onChange={(event) => applyFilter({ category: event.target.value || undefined })}
                        className="border-sidebar-border/70 bg-background max-w-xs rounded-lg border px-3 py-2 text-sm"
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.path ?? category.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Product</th>
                                <th className="px-4 py-3 font-medium">Category</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Price</th>
                                <th className="px-4 py-3 text-right font-medium">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product) => (
                                <tr key={product.id} className="border-sidebar-border/70 hover:bg-muted/40 border-t">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="bg-muted size-12 shrink-0 overflow-hidden rounded">
                                                {product.image && <img src={product.image} alt="" className="size-full object-contain" />}
                                            </div>
                                            <div className="min-w-0">
                                                <Link href={`/admin/products/${product.handle}`} className="line-clamp-2 font-medium hover:underline">
                                                    {product.title}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {product.brand ?? '—'} · {product.variants_count} variant
                                                    {product.variants_count === 1 ? '' : 's'}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">{product.category ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                                product.status === 'active'
                                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {product.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {product.min_price ? formatTaka(Number(product.min_price)) : '—'}
                                    </td>
                                    <td
                                        className={`px-4 py-3 text-right tabular-nums ${product.total_inventory === 0 ? 'text-muted-foreground' : ''}`}
                                    >
                                        {product.total_inventory}
                                    </td>
                                </tr>
                            ))}

                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground px-4 py-12 text-center">
                                        No products match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {products.last_page > 1 && (
                    <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-2">
                        {products.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-lg border px-3 py-1.5 text-xs ${
                                    link.active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : link.url
                                          ? 'border-sidebar-border/70 hover:bg-muted'
                                          : 'border-sidebar-border/40 text-muted-foreground/50 pointer-events-none'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
