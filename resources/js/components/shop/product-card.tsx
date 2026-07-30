import { Link } from '@inertiajs/react';
import { ProductFigure } from '@/components/shop/product-figure';
import { useTranslation } from '@/lib/i18n';
import { formatTaka, type ProductCardData } from '@/lib/shop/catalog';
import { cn } from '@/lib/utils';

export function ProductCard({ product, className }: { product: ProductCardData; className?: string }) {
    const { t } = useTranslation();

    return (
        <Link href={`/shop/${product.slug}`} className={cn('group block', className)}>
            <div className="bg-zt-sand relative aspect-[3/4] overflow-hidden">
                <div className="h-full w-full transition-transform duration-700 ease-out group-hover:scale-[1.04]">
                    <ProductFigure product={product} />
                </div>

                {!product.inStock && (
                    <span className="text-zt-ink/80 absolute top-4 left-4 bg-white/90 px-3 py-1 text-[0.65rem] font-medium tracking-[0.18em] uppercase">
                        {t('product_card.sold_out')}
                    </span>
                )}

                <span className="bg-zt-ink/85 pointer-events-none absolute inset-x-0 bottom-0 translate-y-full px-5 py-3 text-center text-[0.7rem] tracking-[0.2em] text-white uppercase transition-transform duration-300 group-hover:translate-y-0">
                    {t('product_card.view')}
                </span>
            </div>

            <div className="pt-4">
                <h3 className="font-display text-zt-ink line-clamp-2 text-xl leading-snug">{product.name}</h3>
                {product.material && <p className="text-zt-muted mt-1 line-clamp-1 text-sm">{product.material}</p>}
                {/* TODO(currency-display): once catalogue:rebase prices products in base AED,
                    convert with formatPresentment(product.price, currency, currencies) using the
                    active presentment currency from usePage() props. Left as Taka until then so
                    display stays correct while prices are still stored in Taka. */}
                <p className="mt-2 flex items-baseline gap-2">
                    <span className="text-zt-ink text-sm font-medium">{formatTaka(product.price)}</span>
                    {product.compareAtPrice ? <span className="text-zt-muted/70 text-xs line-through">{formatTaka(product.compareAtPrice)}</span> : null}
                </p>
            </div>
        </Link>
    );
}
