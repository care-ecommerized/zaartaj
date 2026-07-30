import { Link, router } from '@inertiajs/react';
import { Check, ChevronRight, Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import { ProductCard } from '@/components/shop/product-card';
import { ProductFigure } from '@/components/shop/product-figure';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { useCart } from '@/lib/shop/cart';
import { formatTaka, type Product, type ProductCardData } from '@/lib/shop/catalog';

interface ProductPageProps {
    product: Product;
    related: ProductCardData[];
}

export default function ProductPage({ product, related }: ProductPageProps) {
    const { add } = useCart();
    const { t } = useTranslation();
    // Only the first option drives a picker; multi-option products are rare here.
    const choices = product.options[0]?.values ?? [];
    const [choice, setChoice] = useState(choices[0] ?? 'Default');
    const [activeImage, setActiveImage] = useState(0);
    const [quantity, setQuantity] = useState(1);
    const [added, setAdded] = useState(false);

    const snapshot = { slug: product.slug, name: product.name, price: product.price, image: product.image };
    const gallery = product.images.length > 0 ? product.images : [{ url: product.image ?? '', alt: product.name }];

    const handleAdd = () => {
        add(snapshot, choice, quantity);
        setAdded(true);
        window.setTimeout(() => setAdded(false), 2200);
    };

    return (
        <ShopLayout title={`${product.name} — Zaartaj Elegance`} description={product.blurb ?? undefined}>
            <div className="mx-auto max-w-7xl px-5 py-8 lg:px-8">
                <nav aria-label="Breadcrumb" className="text-zt-muted flex flex-wrap items-center gap-2 text-xs tracking-[0.14em] uppercase">
                    <Link href="/shop" className="hover:text-zt-ink transition-colors">
                        {t('product.breadcrumb.shop')}
                    </Link>
                    {product.category && (
                        <>
                            <ChevronRight className="size-3" />
                            <Link href={`/shop?category=${product.category}`} className="hover:text-zt-ink transition-colors">
                                {product.categoryName}
                            </Link>
                        </>
                    )}
                    <ChevronRight className="size-3" />
                    <span className="text-zt-ink normal-case">{product.name}</span>
                </nav>

                <div className="mt-8 grid gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <div className="bg-zt-sand aspect-[3/4] overflow-hidden">
                            {gallery[activeImage]?.url ? (
                                <img
                                    src={gallery[activeImage].url}
                                    alt={gallery[activeImage].alt}
                                    className="h-full w-full object-contain"
                                />
                            ) : (
                                <ProductFigure product={product} size="feature" />
                            )}
                        </div>

                        {gallery.length > 1 && (
                            <div className="mt-4 grid grid-cols-5 gap-3">
                                {gallery.map((image, index) => (
                                    <button
                                        key={image.url}
                                        type="button"
                                        onClick={() => setActiveImage(index)}
                                        aria-label={t('product.view_image_aria', { number: index + 1 })}
                                        aria-pressed={index === activeImage}
                                        className={`bg-zt-sand aspect-square overflow-hidden border transition-colors ${
                                            index === activeImage ? 'border-zt-teal' : 'border-transparent hover:border-zt-sand'
                                        }`}
                                    >
                                        <img src={image.url} alt="" className="h-full w-full object-contain" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="lg:pt-6">
                        {product.categoryName && <p className="zt-eyebrow">{product.categoryName}</p>}
                        <h1 className="font-display text-zt-ink mt-3 text-3xl leading-tight sm:text-4xl">{product.name}</h1>

                        <p className="mt-5 flex items-baseline gap-3">
                            <span className="text-zt-ink text-2xl">{formatTaka(product.price)}</span>
                            {product.compareAtPrice ? <span className="text-zt-muted/70 text-base line-through">{formatTaka(product.compareAtPrice)}</span> : null}
                        </p>

                        <p className={`mt-2 text-xs tracking-[0.14em] uppercase ${product.inStock ? 'text-zt-teal' : 'text-zt-muted'}`}>
                            {product.inStock ? t('product.in_stock', { count: product.totalInventory }) : t('product.sold_out')}
                        </p>

                        <div className="zt-rule my-8" />

                        {choices.length > 0 && (
                            <div className="mb-8">
                                <p className="text-zt-ink text-[0.72rem] tracking-[0.2em] uppercase">{product.options[0].name}</p>
                                <div className="mt-4 flex flex-wrap gap-3">
                                    {choices.map((option) => (
                                        <button
                                            key={option}
                                            type="button"
                                            onClick={() => setChoice(option)}
                                            aria-pressed={choice === option}
                                            className={`min-w-16 px-4 py-3 text-xs tracking-[0.12em] uppercase transition-colors ${
                                                choice === option ? 'bg-zt-teal-deep text-white' : 'border-zt-sand text-zt-ink hover:border-zt-teal border'
                                            }`}
                                        >
                                            {option}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="flex flex-wrap items-stretch gap-4">
                            <div className="border-zt-sand flex items-center border">
                                <button
                                    type="button"
                                    onClick={() => setQuantity((current) => Math.max(1, current - 1))}
                                    aria-label={t('product.decrease_aria')}
                                    className="text-zt-ink hover:text-zt-teal px-4 py-4 transition-colors"
                                >
                                    <Minus className="size-4" />
                                </button>
                                <span className="w-10 text-center text-sm">{quantity}</span>
                                <button
                                    type="button"
                                    onClick={() => setQuantity((current) => Math.min(10, current + 1))}
                                    aria-label={t('product.increase_aria')}
                                    className="text-zt-ink hover:text-zt-teal px-4 py-4 transition-colors"
                                >
                                    <Plus className="size-4" />
                                </button>
                            </div>

                            <button
                                type="button"
                                onClick={handleAdd}
                                disabled={!product.inStock}
                                className="bg-zt-teal-deep hover:bg-zt-teal flex flex-1 items-center justify-center gap-2 px-10 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {added ? (
                                    <>
                                        <Check className="size-4" /> {t('product.added')}
                                    </>
                                ) : (
                                    t('product.add_to_bag')
                                )}
                            </button>
                        </div>

                        <button
                            type="button"
                            disabled={!product.inStock}
                            onClick={() => {
                                add(snapshot, choice, quantity);
                                router.visit('/checkout');
                            }}
                            className="border-zt-gold text-zt-gold hover:bg-zt-gold mt-4 w-full border px-10 py-4 text-[0.72rem] font-medium tracking-[0.2em] uppercase transition-colors hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {t('product.buy_now')}
                        </button>

                        {product.description && (
                            /* Sanitised by HtmlSanitizer during import, so this is safe to render. */
                            <div
                                className="zt-prose text-zt-muted mt-12 space-y-4 text-[0.95rem] leading-relaxed"
                                dangerouslySetInnerHTML={{ __html: product.description }}
                            />
                        )}

                        {product.details.length > 0 && (
                            <dl className="border-zt-sand mt-12 border-t">
                                {product.details.map((detail) => (
                                    <div key={detail.label} className="border-zt-sand flex justify-between gap-6 border-b py-4 text-sm">
                                        <dt className="text-zt-muted">{detail.label}</dt>
                                        <dd className="text-zt-ink text-right">{detail.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </div>
                </div>
            </div>

            {related.length > 0 && (
                <section className="bg-zt-sand/40 border-zt-sand mt-12 border-t">
                    <div className="mx-auto max-w-7xl px-5 py-20 lg:px-8">
                        <div className="text-center">
                            <p className="zt-eyebrow">{t('product.related.eyebrow')}</p>
                            <h2 className="font-display text-zt-ink mt-3 text-4xl">{t('product.related.heading')}</h2>
                        </div>

                        <div className="mt-12 grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                            {related.map((item) => (
                                <ProductCard key={item.slug} product={item} />
                            ))}
                        </div>
                    </div>
                </section>
            )}
        </ShopLayout>
    );
}
