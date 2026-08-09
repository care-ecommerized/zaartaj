import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef } from 'react';
import { ProductCard } from '@/components/shop/product-card';
import type { ProductCardData } from '@/lib/shop/catalog';

/**
 * A horizontal, scroll-snapping product rail with arrow controls.
 *
 * No carousel library: it scrolls a native overflow container by roughly one
 * viewport of cards, so it stays light and works with touch/trackpad swipe too.
 */
export function ProductCarousel({ products }: { products: ProductCardData[] }) {
    const trackRef = useRef<HTMLDivElement>(null);

    const scrollByPage = (direction: 1 | -1) => {
        const track = trackRef.current;
        if (!track) return;
        track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: 'smooth' });
    };

    if (products.length === 0) {
        return null;
    }

    return (
        <div className="relative">
            <div
                ref={trackRef}
                className="flex snap-x snap-mandatory gap-6 overflow-x-auto scroll-smooth pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {products.map((product) => (
                    <div key={product.slug} className="w-[68%] shrink-0 snap-start sm:w-[44%] md:w-[31%] lg:w-[23.5%]">
                        <ProductCard product={product} />
                    </div>
                ))}
            </div>

            {/* Arrows — hidden on touch-first small screens, where swipe is natural. */}
            <button
                type="button"
                onClick={() => scrollByPage(-1)}
                aria-label="Previous"
                className="text-zt-ink absolute top-[38%] -left-3 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-zt-sand transition-colors hover:bg-zt-teal hover:text-white md:flex"
            >
                <ChevronLeft className="size-5" />
            </button>
            <button
                type="button"
                onClick={() => scrollByPage(1)}
                aria-label="Next"
                className="text-zt-ink absolute top-[38%] -right-3 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-zt-sand transition-colors hover:bg-zt-teal hover:text-white md:flex"
            >
                <ChevronRight className="size-5" />
            </button>
        </div>
    );
}
