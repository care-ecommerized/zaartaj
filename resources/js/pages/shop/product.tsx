import { Link, router } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import BnplWidgets from '@/components/shop/bnpl-widgets';
import { ProductCard } from '@/components/shop/product-card';
import { ProductFigure } from '@/components/shop/product-figure';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { useCart } from '@/lib/shop/cart';
import { type Product, type ProductCardData } from '@/lib/shop/catalog';
import { usePrice } from '@/lib/shop/use-price';

interface ProductPageProps {
    product: Product;
    related: ProductCardData[];
}

export default function ProductPage({ product, related }: ProductPageProps) {
    const { add } = useCart();
    const { t } = useTranslation();
    const price = usePrice();
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
                        {/* Sliding gallery: all images sit in one track that slides on
                            arrow / thumbnail / dot selection. */}
                        <div className="bg-zt-sand relative aspect-[3/4] overflow-hidden">
                            {gallery[0]?.url ? (
                                <>
                                    <div
                                        className="flex h-full transition-transform duration-500 ease-out"
                                        style={{ transform: `translateX(-${activeImage * 100}%)` }}
                                    >
                                        {gallery.map((image, index) => (
                                            <img key={index} src={image.url} alt={image.alt} className="h-full w-full shrink-0 object-contain" />
                                        ))}
                                    </div>

                                    {gallery.length > 1 && (
                                        <>
                                            <button
                                                type="button"
                                                onClick={() => setActiveImage((i) => (i - 1 + gallery.length) % gallery.length)}
                                                aria-label="Previous image"
                                                className="text-zt-ink ring-zt-sand hover:bg-zt-teal absolute top-1/2 left-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-md ring-1 transition-colors hover:text-white"
                                            >
                                                <ChevronLeft className="size-5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setActiveImage((i) => (i + 1) % gallery.length)}
                                                aria-label="Next image"
                                                className="text-zt-ink ring-zt-sand hover:bg-zt-teal absolute top-1/2 right-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow-md ring-1 transition-colors hover:text-white"
                                            >
                                                <ChevronRight className="size-5" />
                                            </button>

                                            <div className="absolute inset-x-0 bottom-3 flex justify-center gap-2">
                                                {gallery.map((_, index) => (
                                                    <span
                                                        key={index}
                                                        className={`h-1.5 rounded-full transition-all ${index === activeImage ? 'bg-zt-teal w-5' : 'w-1.5 bg-white/70'}`}
                                                    />
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </>
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
                            <span className="text-zt-ink text-2xl">{price(product.price)}</span>
                            {product.compareAtPrice ? <span className="text-zt-muted/70 text-base line-through">{price(product.compareAtPrice)}</span> : null}
                        </p>

                        {/* Tabby / Tamara pay-in-instalments messaging for this price. */}
                        <BnplWidgets amount={product.price} />


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
