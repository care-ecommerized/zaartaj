import { cn } from '@/lib/utils';

/**
 * Product imagery.
 *
 * Uses the photograph when there is one. An imported product may still be waiting
 * for its image to be mirrored off the Shopify CDN, and a hand-added one may have
 * no photo yet, so this falls back to a deterministic teal/gold panel drawn from
 * the slug — the grid looks composed rather than half-finished, and no two
 * products look alike.
 */

/** The minimum a figure needs; both card and detail shapes satisfy it. */
interface FigureSubject {
    slug: string;
    name: string;
    image?: string | null;
}

/** Stable hash so a given product always gets the same panel. */
function hashSlug(slug: string): number {
    let hash = 0;

    for (let index = 0; index < slug.length; index += 1) {
        hash = (hash << 5) - hash + slug.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
}

const PANELS = [
    { from: '#0b4f49', to: '#12766e' },
    { from: '#12766e', to: '#7fc5bc' },
    { from: '#0e5f58', to: '#3f9a90' },
    { from: '#134f4a', to: '#8fcfc6' },
    { from: '#0b4f49', to: '#2f8b82' },
];

interface ProductFigureProps {
    product: FigureSubject;
    className?: string;
    /** Larger initial and motif for the product detail page. */
    size?: 'card' | 'feature';
}

export function ProductFigure({ product, className, size = 'card' }: ProductFigureProps) {
    if (product.image) {
        return <img src={product.image} alt={product.name} loading="lazy" className={cn('h-full w-full object-cover', className)} />;
    }

    const seed = hashSlug(product.slug);
    const panel = PANELS[seed % PANELS.length];
    const rotation = (seed % 24) - 12;
    const gradientId = `zt-panel-${product.slug}`;

    return (
        <svg viewBox="0 0 300 400" preserveAspectRatio="xMidYMid slice" role="img" aria-label={product.name} className={cn('h-full w-full', className)}>
            <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="0.6" y2="1">
                    <stop offset="0%" stopColor={panel.from} />
                    <stop offset="100%" stopColor={panel.to} />
                </linearGradient>
            </defs>

            <rect width="300" height="400" fill={`url(#${gradientId})`} />

            {/* Soft drape lines, angled per product. */}
            <g transform={`rotate(${rotation} 150 200)`} opacity="0.35">
                <path d="M-40 320 Q 90 200 150 60" stroke="#f0e9dd" strokeWidth="1.2" fill="none" />
                <path d="M10 360 Q 130 230 190 80" stroke="#f0e9dd" strokeWidth="0.9" fill="none" opacity="0.7" />
                <path d="M70 400 Q 180 250 240 100" stroke="#f0e9dd" strokeWidth="1.2" fill="none" opacity="0.55" />
            </g>

            {/* Gold arc, echoing the swoosh in the logo. */}
            <path d="M-10 300 Q 150 250 320 330" stroke="#d9bd76" strokeWidth="1.6" fill="none" opacity="0.75" />

            <circle cx="150" cy="196" r={size === 'feature' ? 70 : 58} fill="none" stroke="#d9bd76" strokeWidth="1" opacity="0.65" />
            <text
                x="150"
                y="196"
                textAnchor="middle"
                dominantBaseline="central"
                fill="#f0e9dd"
                fontFamily="'Cormorant Garamond', Georgia, serif"
                fontSize={size === 'feature' ? 78 : 64}
                opacity="0.92"
            >
                {product.name.charAt(0)}
            </text>
        </svg>
    );
}
