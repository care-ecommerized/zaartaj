import { cn } from '@/lib/utils';
import { useState } from 'react';

/**
 * The Zaartaj Elegance lockup: crowned gown silhouette + wordmark.
 *
 * Preferred source is the real artwork at `public/images/zaartaj-logo.png`
 * (see LOGO_SRC). If that file is missing the component falls back to the
 * inline SVG below, so the header never depends on an asset being present.
 *
 * Two transparent PNGs: the bordeaux/gold emblem for light backgrounds (header,
 * checkout) and an all-white version for the dark footer. Both fall back to the
 * inline SVG below if the file is ever missing.
 */
const LOGO_SRC: string | null = '/images/zaartaj-logo.png';
const LOGO_SRC_LIGHT: string | null = '/images/zaartaj-footer-white.png';

interface BrandMarkProps {
    /** 'full' shows the wordmark; 'mark' shows only the crowned gown. */
    variant?: 'full' | 'mark';
    /** 'gold' for cream backgrounds, 'light' for the dark teal footer. */
    tone?: 'gold' | 'light';
    className?: string;
}

export function BrandMark({ variant = 'full', tone = 'gold', className }: BrandMarkProps) {
    const [rasterFailed, setRasterFailed] = useState(false);

    // The white emblem for the dark footer, the bordeaux emblem elsewhere.
    const rasterSrc = tone === 'light' ? LOGO_SRC_LIGHT : LOGO_SRC;

    if (rasterSrc && variant === 'full' && !rasterFailed) {
        return (
            <img
                src={rasterSrc}
                alt="Zaartaj Elegance"
                onError={() => setRasterFailed(true)}
                className={cn('h-12 w-auto', className)}
            />
        );
    }

    const wordmark = tone === 'light' ? '#f3e7c8' : '#9c7f3f';
    const gownTop = tone === 'light' ? '#7fc5bc' : '#12766e';
    const gownBottom = tone === 'light' ? '#cdece7' : '#7fc5bc';
    const gold = tone === 'light' ? '#e6cf94' : '#c9a94f';

    return (
        <span className={cn('inline-flex items-center gap-3', className)}>
            <svg viewBox="0 0 60 88" role="img" aria-label="Zaartaj Elegance" className="h-11 w-auto shrink-0">
                <defs>
                    <linearGradient id="zt-gown" x1="0" y1="0" x2="0.4" y2="1">
                        <stop offset="0%" stopColor={gownTop} />
                        <stop offset="100%" stopColor={gownBottom} />
                    </linearGradient>
                </defs>

                {/* crown */}
                <path d="M23 15 L25.5 9 L28 13 L30 7 L32 13 L34.5 9 L37 15 Z" fill={gold} />
                <rect x="23" y="15.5" width="14" height="2.4" rx="1.2" fill={gold} />

                {/* sparkles */}
                <path d="M17 12 l1.1 2.6 2.6 1.1 -2.6 1.1 -1.1 2.6 -1.1 -2.6 -2.6 -1.1 2.6 -1.1 Z" fill={gold} opacity="0.85" />
                <path d="M44 20 l0.9 2.1 2.1 0.9 -2.1 0.9 -0.9 2.1 -0.9 -2.1 -2.1 -0.9 2.1 -0.9 Z" fill={gold} opacity="0.6" />

                {/* stand + bodice */}
                <circle cx="30" cy="21.5" r="2.2" fill={gold} />
                <path d="M30 23.5 v3" stroke={gold} strokeWidth="1.6" />
                <path d="M24 30 q6 -4 12 0 l1.5 10 q-7.5 3 -15 0 Z" fill="url(#zt-gown)" />

                {/* skirt sweeping to the right */}
                <path d="M22.5 40 q7.5 3 15 0 L57 82 q-13 5 -27 0 Q17 78 8 84 Q12 60 22.5 40 Z" fill="url(#zt-gown)" />
                <path d="M30 42 Q34 62 52 80" stroke={gold} strokeWidth="1.4" fill="none" opacity="0.85" />
            </svg>

            {variant === 'full' && (
                <span className="font-display leading-[0.86]" style={{ color: wordmark }}>
                    <span className="block text-[1.6rem] font-semibold tracking-[0.02em]">Zaartaj</span>
                    <span className="block text-[1.35rem] font-normal tracking-[0.03em]">Elegance</span>
                </span>
            )}
        </span>
    );
}
