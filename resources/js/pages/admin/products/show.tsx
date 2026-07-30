import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { formatTaka } from '@/lib/shop/catalog';
import { type BreadcrumbItem } from '@/types';

interface Variant {
    id: number;
    sku: string | null;
    option1: string | null;
    price: string;
    compare_at_price: string | null;
    inventory_quantity: number;
    grams: number;
}

interface AdminProductDetail {
    id: number;
    handle: string;
    title: string;
    brand: string | null;
    vendor: string | null;
    product_type: string | null;
    status: string;
    shopify_category: string | null;
    body_html: string | null;
    total_inventory: number;
    variants: Variant[];
    metafields: { id: number; key: string; value: string }[];
    tags: { id: number; name: string }[];
}

interface Props {
    product: AdminProductDetail;
    images: { id: number; url: string; position: number; alt: string | null; mirrored: boolean; mirror_error: string | null }[];
}

export default function AdminProductShow({ product, images }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Products', href: '/admin/products' },
        { title: product.title, href: `/admin/products/${product.handle}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={product.title} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold">{product.title}</h1>
                        <p className="text-muted-foreground mt-1 font-mono text-xs">{product.handle}</p>
                    </div>

                    <Link
                        href={`/shop/${product.handle}`}
                        className="border-sidebar-border/70 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm"
                    >
                        View on storefront
                        <ExternalLink className="size-3.5" />
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Status', value: product.status },
                        { label: 'Brand', value: product.brand ?? '—' },
                        { label: 'Type', value: product.product_type || '—' },
                        { label: 'Stock', value: product.total_inventory },
                    ].map((stat) => (
                        <div key={stat.label} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs uppercase">{stat.label}</p>
                            <p className="mt-1.5 truncate text-lg font-medium">{stat.value}</p>
                        </div>
                    ))}
                </div>

                {product.shopify_category && (
                    <p className="text-muted-foreground text-sm">
                        Category: <span className="text-foreground">{product.shopify_category}</span>
                    </p>
                )}

                {images.length > 0 && (
                    <section>
                        <h2 className="text-lg font-medium">Images ({images.length})</h2>
                        <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8">
                            {images.map((image) => (
                                <figure key={image.id} className="space-y-1">
                                    <div className="bg-muted aspect-square overflow-hidden rounded-lg">
                                        <img src={image.url} alt={image.alt ?? ''} className="size-full object-contain" />
                                    </div>
                                    {/* Until mirrored, the storefront is still loading this from Shopify's CDN. */}
                                    <figcaption className={`text-[0.65rem] ${image.mirrored ? 'text-muted-foreground' : 'text-amber-600'}`}>
                                        {image.mirrored ? 'Local' : 'Remote'}
                                    </figcaption>
                                </figure>
                            ))}
                        </div>
                    </section>
                )}

                <section>
                    <h2 className="text-lg font-medium">Variants ({product.variants.length})</h2>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border mt-3 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Option</th>
                                    <th className="px-4 py-3 font-medium">SKU</th>
                                    <th className="px-4 py-3 text-right font-medium">Price</th>
                                    <th className="px-4 py-3 text-right font-medium">Compare at</th>
                                    <th className="px-4 py-3 text-right font-medium">Stock</th>
                                    <th className="px-4 py-3 text-right font-medium">Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                                {product.variants.map((variant) => (
                                    <tr key={variant.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3">{variant.option1 ?? 'Default'}</td>
                                        <td className="text-muted-foreground px-4 py-3 font-mono text-xs">{variant.sku ?? '—'}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{formatTaka(Number(variant.price))}</td>
                                        <td className="text-muted-foreground px-4 py-3 text-right tabular-nums">
                                            {variant.compare_at_price ? formatTaka(Number(variant.compare_at_price)) : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">{variant.inventory_quantity}</td>
                                        {/* Zero weight means the courier cannot price this parcel. */}
                                        <td className={`px-4 py-3 text-right tabular-nums ${variant.grams === 0 ? 'text-amber-600' : ''}`}>
                                            {variant.grams} g
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {product.metafields.length > 0 && (
                    <section>
                        <h2 className="text-lg font-medium">Attributes</h2>
                        <dl className="border-sidebar-border/70 dark:border-sidebar-border mt-3 divide-y rounded-xl border">
                            {product.metafields.map((metafield) => (
                                <div key={metafield.id} className="flex flex-wrap justify-between gap-4 px-4 py-3 text-sm">
                                    <dt className="text-muted-foreground capitalize">{metafield.key.replace(/-/g, ' ')}</dt>
                                    <dd className="text-right">{metafield.value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                )}

                {product.body_html && (
                    <section>
                        <h2 className="text-lg font-medium">Description</h2>
                        {/* Sanitised on import, so rendering the stored markup is safe. */}
                        <div
                            className="prose prose-sm dark:prose-invert border-sidebar-border/70 mt-3 max-w-none rounded-xl border p-4"
                            dangerouslySetInnerHTML={{ __html: product.body_html }}
                        />
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
