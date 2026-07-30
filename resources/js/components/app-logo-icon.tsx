import { SVGAttributes } from 'react';

/**
 * Zaartaj Elegance mark: a crowned dress-form gown, drawn in a single colour so
 * it inherits `fill-current` / the surrounding text colour wherever it is placed
 * (admin sidebar, app header, auth pages). For the full colour lockup on the
 * storefront, see `components/shop/brand-mark.tsx`.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 60 88" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
            {/* crown */}
            <path d="M23 15 L25.5 9 L28 13 L30 7 L32 13 L34.5 9 L37 15 Z" />
            <rect x="23" y="15.5" width="14" height="2.4" rx="1.2" />

            {/* stand + bodice */}
            <circle cx="30" cy="21.5" r="2.2" />
            <rect x="29.2" y="23.5" width="1.6" height="3.4" rx="0.8" />
            <path d="M24 30 q6 -4 12 0 l1.5 10 q-7.5 3 -15 0 Z" />

            {/* skirt sweeping to the right */}
            <path d="M22.5 40 q7.5 3 15 0 L57 82 q-13 5 -27 0 Q17 78 8 84 Q12 60 22.5 40 Z" />
        </svg>
    );
}
