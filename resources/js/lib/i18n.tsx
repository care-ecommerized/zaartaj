import { router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import type { SharedData } from '@/types';

export type Direction = 'ltr' | 'rtl';

export interface Translator {
    /** The active locale code (`en` or `ar`). */
    locale: string;
    /** Reading direction for the active locale. */
    direction: Direction;
    /** The raw active-locale message map, for fallbacks (e.g. category names). */
    translations: Record<string, string>;
    /**
     * Translate a flat message key, substituting `:token` placeholders.
     * Falls back to the key itself when a translation is missing, so a stray
     * key is visible in development rather than rendering as blank.
     */
    t: (key: string, replacements?: Record<string, string | number>) => string;
}

function translate(
    translations: Record<string, string>,
    key: string,
    replacements?: Record<string, string | number>,
): string {
    let message = translations[key] ?? key;

    if (replacements) {
        for (const [token, value] of Object.entries(replacements)) {
            message = message.replace(new RegExp(`:${token}\\b`, 'g'), String(value));
        }
    }

    return message;
}

/**
 * The active-locale translator, sourced from Inertia's shared props.
 *
 * Reads usePage() directly rather than a React context: Inertia only exposes
 * the page context to descendants of its <App>, and our providers live above
 * <App> in app.tsx. Every consumer here is a page or a component rendered by a
 * page, so it always sits inside <App> and usePage() resolves correctly.
 */
export function useTranslation(): Translator {
    const { props } = usePage<SharedData>();

    const translations = props.translations ?? {};
    const locale = props.locale ?? 'en';
    const direction: Direction = props.direction === 'rtl' ? 'rtl' : 'ltr';

    return {
        locale,
        direction,
        translations,
        t: (key, replacements) => translate(translations, key, replacements),
    };
}

/**
 * Keeps <html dir>/<html lang> in sync with the shared locale across SPA visits.
 *
 * The Blade root already stamps the correct dir/lang on first paint; this only
 * updates them when an Inertia navigation changes the resolved locale (e.g. the
 * language switcher). It subscribes to router events rather than usePage() so it
 * can live above <App> in app.tsx alongside CartProvider.
 */
export function I18nProvider({ children }: { children: ReactNode }) {
    useEffect(() => {
        const apply = (page: { props: Record<string, unknown> }) => {
            const direction = page.props.direction;
            const locale = page.props.locale;

            if (direction === 'ltr' || direction === 'rtl') {
                document.documentElement.dir = direction;
            }

            if (typeof locale === 'string') {
                document.documentElement.lang = locale;
            }
        };

        return router.on('navigate', (event) => apply(event.detail.page));
    }, []);

    return <>{children}</>;
}
