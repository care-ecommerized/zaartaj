import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ProductCard } from '@/components/shop/product-card';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import type { ProductCardData } from '@/lib/shop/catalog';
import type { SharedData } from '@/types';

const PROMISES = [
    { titleKey: 'home.promise.small_runs.title', bodyKey: 'home.promise.small_runs.body' },
    { titleKey: 'home.promise.fitted.title', bodyKey: 'home.promise.fitted.body' },
    { titleKey: 'home.promise.pay.title', bodyKey: 'home.promise.pay.body' },
];

/**
 * Background photo per category, keyed by slug. Files live in
 * public/images/categories/. To swap a photo, replace the file at that path —
 * a category with no entry here simply falls back to the teal gradient.
 */
const CATEGORY_IMAGES: Record<string, string> = {
    gowns: '/images/categories/gowns.jpg',
    'modest-clothes': '/images/categories/modest-clothes.jpg',
    jewellery: '/images/categories/jewellery.jpg',
    bags: '/images/categories/bags.jpg',
    shoes: '/images/categories/shoes.jpg',
};

interface HomeProps {
    hero: ProductCardData | null;
    featured: ProductCardData[];
    newest: ProductCardData[];
}

export default function Home({ hero, featured, newest }: HomeProps) {
    const { shopCategories: categories = [] } = usePage<SharedData>().props;
    const { t, translations } = useTranslation();
    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;
    // Falls back to the newest arrivals until anything is marked in stock.
    const highlights = (featured.length > 0 ? featured : newest).slice(0, 4);

    return (
        <ShopLayout
            title="Zaartaj Elegance — Gowns, Abaya & Occasion Wear"
            description="Gowns, abayas, bags, shoes and jewellery made in small runs in Dhaka. Complimentary delivery across Bangladesh over ৳15,000."
        >
            {/* Hero — full-screen banner */}
            <section className="bg-zt-teal-deep relative flex min-h-[100svh] w-full items-center overflow-hidden">
                {/* Background image */}
                {hero?.image ? (
                    <img
                        src={hero.image}
                        alt={hero.name}
                        className="absolute inset-0 h-full w-full object-cover object-[50%_22%]"
                    />
                ) : (
                    <div className="from-zt-teal-deep via-zt-teal to-zt-teal-deep absolute inset-0 bg-gradient-to-br" />
                )}

                {/* Legibility scrims: darker on the left for the text, a base wash + a foot fade. */}
                <div className="from-zt-teal-deep/95 via-zt-teal-deep/60 absolute inset-0 bg-gradient-to-r to-transparent" />
                <div className="from-zt-teal-deep/80 absolute inset-0 bg-gradient-to-t via-transparent to-transparent" />

                {/* Thin gold frame inside the banner. */}
                <span className="pointer-events-none absolute inset-4 hidden ring-1 ring-zt-gold-light/25 sm:block lg:inset-6" />

                <div className="relative mx-auto w-full max-w-7xl px-6 py-24 lg:px-10">
                    <div className="max-w-xl">
                        <p className="text-zt-gold-light text-xs font-medium tracking-[0.28em] uppercase">{t('home.hero.eyebrow')}</p>
                        <h1 className="font-display mt-6 text-5xl leading-[1.05] text-white drop-shadow-sm sm:text-6xl lg:text-7xl">
                            {t('home.hero.headline_1')}
                            <span className="text-zt-gold-light block italic">{t('home.hero.headline_2')}</span>
                        </h1>
                        <p className="mt-7 max-w-md text-base leading-relaxed text-white/80">{t('home.hero.body')}</p>

                        <div className="mt-10 flex flex-wrap items-center gap-4">
                            <Link
                                href="/shop"
                                className="bg-zt-gold hover:bg-zt-gold-light inline-flex items-center gap-2 px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors"
                            >
                                {t('home.hero.cta_shop')}
                                <ArrowRight className="size-4" />
                            </Link>
                            <Link
                                href={hero ? `/shop/${hero.slug}` : '/shop?category=gowns'}
                                className="inline-flex items-center px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase ring-1 ring-white/40 transition-colors hover:bg-white/10"
                            >
                                {t('home.hero.cta_view_gown')}
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Featured-piece caption, bottom-right. */}
                {hero?.name && (
                    <Link
                        href={`/shop/${hero.slug}`}
                        className="font-display absolute right-6 bottom-8 hidden text-lg text-white/85 italic drop-shadow transition-colors hover:text-white lg:block lg:right-10"
                    >
                        {hero.name}
                    </Link>
                )}

                {/* Scroll cue */}
                <div className="absolute inset-x-0 bottom-6 flex justify-center">
                    <span className="flex h-9 w-6 items-start justify-center rounded-full pt-2 ring-1 ring-white/40">
                        <span className="h-2 w-0.5 animate-pulse rounded-full bg-white/70" />
                    </span>
                </div>
            </section>

            {/* Promises */}
            <section className="border-zt-sand border-b">
                <div className="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:grid-cols-3 lg:px-8">
                    {PROMISES.map((promise) => (
                        <div key={promise.titleKey}>
                            <h2 className="font-display text-zt-ink text-lg">{t(promise.titleKey)}</h2>
                            <p className="text-zt-muted mt-1.5 text-sm leading-relaxed">{t(promise.bodyKey)}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Categories */}
            <section className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                <div className="text-center">
                    <p className="zt-eyebrow">{t('home.categories.eyebrow')}</p>
                    <h2 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('home.categories.heading')}</h2>
                    <div className="zt-rule mx-auto mt-6 max-w-xs" />
                </div>

                <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {categories.map((category) => {
                        const image = CATEGORY_IMAGES[category.slug];

                        return (
                            <Link
                                key={category.slug}
                                href={`/shop?category=${category.slug}`}
                                className="group relative flex min-h-[240px] flex-col justify-end overflow-hidden p-7 lg:min-h-[300px]"
                            >
                                {image ? (
                                    <img
                                        src={image}
                                        alt={categoryLabel(category.slug, category.name)}
                                        loading="lazy"
                                        className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                                    />
                                ) : (
                                    // No photo for this category yet — fall back to the house gradient.
                                    <span className="from-zt-teal-deep to-zt-teal absolute inset-0 bg-gradient-to-br" />
                                )}

                                {/* Teal-to-transparent wash keeps the label legible over any photo. */}
                                <span className="from-zt-teal-deep/90 via-zt-teal-deep/30 absolute inset-0 bg-gradient-to-t to-transparent" />

                                <span className="relative">
                                    <span className="font-display block text-3xl text-white drop-shadow-sm">{categoryLabel(category.slug, category.name)}</span>
                                    <span className="text-zt-gold-light mt-2 inline-flex items-center gap-2 text-[0.7rem] tracking-[0.2em] uppercase opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                                        {t('home.categories.explore')}
                                        <ArrowRight className="size-3.5" />
                                    </span>
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </section>

            {/* Featured */}
            <section className="bg-zt-sand/40 border-zt-sand border-y">
                <div className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="zt-eyebrow">{t('home.featured.eyebrow')}</p>
                            <h2 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('home.featured.heading')}</h2>
                        </div>
                        <Link href="/shop" className="text-zt-teal hover:text-zt-teal-deep inline-flex items-center gap-2 text-[0.72rem] tracking-[0.2em] uppercase">
                            {t('home.featured.view_all')}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>

                    <div className="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                        {highlights.map((product) => (
                            <ProductCard key={product.slug} product={product} />
                        ))}
                    </div>
                </div>
            </section>

            {/* Atelier note */}
            <section className="mx-auto max-w-3xl px-5 py-24 text-center lg:px-8">
                <p className="zt-eyebrow">{t('home.atelier.eyebrow')}</p>
                <h2 className="font-display text-zt-ink mt-4 text-4xl leading-tight sm:text-5xl">
                    {t('home.atelier.heading_1')} <span className="italic">{t('home.atelier.heading_2')}</span>
                </h2>
                <p className="text-zt-muted mt-7 text-base leading-relaxed">{t('home.atelier.body')}</p>
                <div className="zt-rule mx-auto mt-10 max-w-xs" />
            </section>
        </ShopLayout>
    );
}
