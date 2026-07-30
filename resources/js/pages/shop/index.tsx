import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ProductCard } from '@/components/shop/product-card';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import type { Paginated, ProductCardData } from '@/lib/shop/catalog';
import type { SharedData } from '@/types';

type SortKey = 'featured' | 'price-asc' | 'price-desc' | 'newest';

const SORTS: { key: SortKey; labelKey: string }[] = [
    { key: 'featured', labelKey: 'shop.sort.featured' },
    { key: 'newest', labelKey: 'shop.sort.newest' },
    { key: 'price-asc', labelKey: 'shop.sort.price_asc' },
    { key: 'price-desc', labelKey: 'shop.sort.price_desc' },
];

interface ShopIndexProps {
    products: Paginated<ProductCardData>;
    filters: { category?: string; search?: string; sort?: SortKey };
    activeCategory: { slug: string; name: string; path: string | null } | null;
}

export default function ShopIndex({ products, filters, activeCategory }: ShopIndexProps) {
    const { shopCategories: categories = [] } = usePage<SharedData>().props;
    const { t, translations } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;

    // Category name/path come from the DB; use a per-slug key for the heading when present.
    const heading = activeCategory ? categoryLabel(activeCategory.slug, activeCategory.name) : t('shop.heading_default');
    const tagline = activeCategory?.path ?? t('shop.tagline_default');

    /** Sorting and filtering are server-side, so every change is a visit. */
    const applyFilter = (changes: Record<string, string | undefined>) => {
        router.get('/shop', { ...filters, ...changes }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <ShopLayout title={`${heading} — Zaartaj Elegance`} description={tagline}>
            <section className="border-zt-sand bg-zt-teal-mist/40 border-b">
                <div className="mx-auto max-w-7xl px-5 py-16 text-center lg:px-8">
                    <p className="zt-eyebrow">{t('shop.eyebrow')}</p>
                    <h1 className="font-display text-zt-ink mt-3 text-5xl sm:text-6xl">{heading}</h1>
                    <p className="text-zt-muted mx-auto mt-4 max-w-lg text-sm leading-relaxed">{tagline}</p>
                </div>
            </section>

            <div className="mx-auto max-w-7xl px-5 py-12 lg:px-8">
                <div className="border-zt-sand flex flex-wrap items-center justify-between gap-6 border-b pb-6">
                    <nav className="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <Link
                            href="/shop"
                            className={`text-[0.72rem] tracking-[0.18em] uppercase transition-colors ${
                                !activeCategory ? 'text-zt-teal underline underline-offset-8' : 'text-zt-muted hover:text-zt-ink'
                            }`}
                        >
                            {t('shop.filter.all')}
                        </Link>
                        {categories.map((entry) => (
                            <Link
                                key={entry.slug}
                                href={`/shop?category=${entry.slug}`}
                                className={`text-[0.72rem] tracking-[0.18em] uppercase transition-colors ${
                                    activeCategory?.slug === entry.slug ? 'text-zt-teal underline underline-offset-8' : 'text-zt-muted hover:text-zt-ink'
                                }`}
                            >
                                {categoryLabel(entry.slug, entry.name)}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex flex-wrap items-center gap-4">
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
                                placeholder={t('shop.search_placeholder')}
                                aria-label={t('shop.search_aria')}
                                className="border-zt-sand text-zt-ink focus:border-zt-teal border bg-transparent px-3 py-2 text-xs focus:outline-none"
                            />
                        </form>

                        <label className="text-zt-muted flex items-center gap-3 text-[0.72rem] tracking-[0.18em] uppercase">
                            {t('shop.sort')}
                            <select
                                value={filters.sort ?? 'featured'}
                                onChange={(event) => applyFilter({ sort: event.target.value })}
                                className="border-zt-sand text-zt-ink focus:border-zt-teal border bg-transparent px-3 py-2 text-xs tracking-normal normal-case focus:outline-none"
                            >
                                {SORTS.map((option) => (
                                    <option key={option.key} value={option.key}>
                                        {t(option.labelKey)}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                </div>

                <p className="text-zt-muted mt-6 text-xs tracking-[0.14em] uppercase">
                    {t(products.total === 1 ? 'shop.count_one' : 'shop.count_other', { count: products.total })}
                </p>

                <div className="mt-8 grid gap-x-6 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                    {products.data.map((product) => (
                        <ProductCard key={product.slug} product={product} />
                    ))}
                </div>

                {products.data.length === 0 && <p className="text-zt-muted py-24 text-center text-sm">{t('shop.empty')}</p>}

                {products.last_page > 1 && (
                    <nav aria-label={t('shop.pagination')} className="mt-16 flex flex-wrap justify-center gap-2">
                        {products.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                className={`border px-4 py-2 text-xs transition-colors ${
                                    link.active
                                        ? 'border-zt-teal bg-zt-teal text-white'
                                        : link.url
                                          ? 'border-zt-sand text-zt-ink hover:border-zt-teal'
                                          : 'border-zt-sand/50 text-zt-muted/50 pointer-events-none'
                                }`}
                                // Laravel renders "&laquo; Previous" as an HTML entity.
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </ShopLayout>
    );
}
