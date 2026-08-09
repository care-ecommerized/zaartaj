import { Link } from '@inertiajs/react';
import { Minus, Plus, X } from 'lucide-react';
import { ProductFigure } from '@/components/shop/product-figure';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { useCart } from '@/lib/shop/cart';
import { formatTaka } from '@/lib/shop/catalog';

export default function CartPage() {
    const { resolvedLines, subtotal, itemCount, setQuantity, remove } = useCart();
    const { t } = useTranslation();

    // Delivery depends on the destination zone, which we only know once the
    // customer enters an address at checkout — so the bag shows the subtotal as
    // an estimate and defers the delivery figure rather than guessing a flat fee.

    if (resolvedLines.length === 0) {
        return (
            <ShopLayout title="Your bag — Zaartaj Elegance">
                <div className="mx-auto max-w-xl px-5 py-32 text-center">
                    <p className="zt-eyebrow">{t('cart.eyebrow')}</p>
                    <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('cart.empty.heading')}</h1>
                    <p className="text-zt-muted mt-4 text-sm leading-relaxed">{t('cart.empty.body')}</p>
                    <Link href="/shop" className="bg-zt-teal-deep hover:bg-zt-teal mt-10 inline-block px-10 py-4 text-[0.72rem] tracking-[0.2em] text-white uppercase transition-colors">
                        {t('cart.browse')}
                    </Link>
                </div>
            </ShopLayout>
        );
    }

    return (
        <ShopLayout title="Your bag — Zaartaj Elegance">
            <div className="mx-auto max-w-7xl px-5 py-14 lg:px-8">
                <p className="zt-eyebrow">{t('cart.eyebrow')}</p>
                <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">
                    {t(itemCount === 1 ? 'cart.count_one' : 'cart.count_other', { count: itemCount })}
                </h1>

                <div className="mt-12 grid gap-12 lg:grid-cols-[1.6fr_1fr] lg:gap-16">
                    {/* Lines */}
                    <ul className="border-zt-sand border-t">
                        {resolvedLines.map((line) => (
                            <li key={`${line.slug}-${line.size}`} className="border-zt-sand flex gap-5 border-b py-7">
                                <Link href={`/shop/${line.slug}`} className="bg-zt-sand aspect-[3/4] w-24 shrink-0 overflow-hidden sm:w-28">
                                    <ProductFigure product={line} />
                                </Link>

                                <div className="flex flex-1 flex-col">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <Link href={`/shop/${line.slug}`} className="font-display text-zt-ink hover:text-zt-teal text-xl transition-colors">
                                                {line.name}
                                            </Link>
                                            <p className="text-zt-muted mt-1 text-xs tracking-[0.14em] uppercase">{t('cart.size', { size: line.size })}</p>
                                            <p className="text-zt-muted mt-1 text-sm">{t('cart.each', { price: formatTaka(line.price) })}</p>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() => remove(line.slug, line.size)}
                                            aria-label={t('cart.remove_aria', { name: line.name })}
                                            className="text-zt-muted hover:text-zt-ink transition-colors"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    </div>

                                    <div className="mt-auto flex items-end justify-between gap-4 pt-4">
                                        <div className="border-zt-sand flex items-center border">
                                            <button
                                                type="button"
                                                onClick={() => setQuantity(line.slug, line.size, line.quantity - 1)}
                                                aria-label={t('cart.decrease_aria', { name: line.name })}
                                                className="text-zt-ink hover:text-zt-teal px-3 py-2.5 transition-colors"
                                            >
                                                <Minus className="size-3.5" />
                                            </button>
                                            <span className="w-9 text-center text-sm">{line.quantity}</span>
                                            <button
                                                type="button"
                                                onClick={() => setQuantity(line.slug, line.size, line.quantity + 1)}
                                                aria-label={t('cart.increase_aria', { name: line.name })}
                                                className="text-zt-ink hover:text-zt-teal px-3 py-2.5 transition-colors"
                                            >
                                                <Plus className="size-3.5" />
                                            </button>
                                        </div>

                                        <p className="text-zt-ink text-sm font-medium">{formatTaka(line.lineTotal)}</p>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>

                    {/* Summary */}
                    <aside className="bg-zt-sand/40 h-fit p-8">
                        <h2 className="font-display text-zt-ink text-2xl">{t('cart.summary')}</h2>
                        <div className="zt-rule my-6" />

                        <dl className="space-y-4 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-zt-muted">{t('cart.subtotal')}</dt>
                                <dd className="text-zt-ink">{formatTaka(subtotal)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-zt-muted">{t('cart.delivery')}</dt>
                                <dd className="text-zt-muted">{t('cart.delivery_note')}</dd>
                            </div>
                            <div className="border-zt-sand flex justify-between border-t pt-4 text-base">
                                <dt className="text-zt-ink">{t('cart.estimated_total')}</dt>
                                <dd className="text-zt-ink font-medium">{formatTaka(subtotal)}</dd>
                            </div>
                        </dl>

                        <p className="text-zt-muted mt-5 text-xs leading-relaxed">{t('cart.delivery_hint')}</p>

                        <Link
                            href="/checkout"
                            className="bg-zt-teal-deep hover:bg-zt-teal mt-8 block px-8 py-4 text-center text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors"
                        >
                            {t('cart.checkout')}
                        </Link>

                        <Link href="/shop" className="text-zt-muted hover:text-zt-ink mt-5 block text-center text-xs tracking-[0.16em] uppercase transition-colors">
                            {t('cart.continue')}
                        </Link>

                        <p className="text-zt-muted mt-8 text-center text-[0.7rem] tracking-[0.16em] uppercase">{t('cart.payments')}</p>
                    </aside>
                </div>
            </div>
        </ShopLayout>
    );
}
