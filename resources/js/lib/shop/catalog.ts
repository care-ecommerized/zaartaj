/**
 * Storefront types and formatting helpers.
 *
 * Products used to be hard-coded here so the design could be reviewed without a
 * database. They now come from the `products` table via Inertia props, and these
 * types are the contract between ShopController/ProductPresenter and the pages.
 */

export interface Category {
    slug: string;
    name: string;
}

/** The compact form used by grids and rails. */
export interface ProductCardData {
    slug: string;
    name: string;
    brand?: string | null;
    category?: string | null;
    categoryName?: string | null;
    /** Price in whole Taka, taken from the cheapest variant. */
    price: number;
    /** Original price, when the piece is reduced. */
    compareAtPrice?: number | null;
    /** Fabric, product type or brand — whatever best describes the piece. */
    material?: string | null;
    image?: string | null;
    inStock: boolean;
}

export interface ProductVariant {
    id: number;
    title: string;
    price: number;
    compareAtPrice?: number | null;
    available: boolean;
    inventory: number;
}

export interface ProductOption {
    name: string;
    values: string[];
}

export interface ProductImage {
    url: string;
    alt: string;
}

/** Everything the product page renders. */
export interface Product extends ProductCardData {
    /** Sanitised at import, so it is safe to render as markup. */
    description?: string | null;
    blurb?: string | null;
    images: ProductImage[];
    options: ProductOption[];
    variants: ProductVariant[];
    details: { label: string; value: string }[];
    totalInventory: number;
}

/** A Laravel length-aware paginator, as Inertia serialises it. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export const CURRENCY = 'BDT';

export function formatTaka(amount: number): string {
    return `৳${Math.round(amount).toLocaleString('en-BD')}`;
}

/**
 * Format an amount in any currency. BDT keeps the ৳ Taka styling (whole units);
 * everything else (e.g. AED) shows the ISO code with two decimals.
 */
export function formatMoney(amount: number, currency: string): string {
    if (currency === CURRENCY) {
        return formatTaka(amount);
    }

    return `${currency} ${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** The active presentment currency, as shared by HandleInertiaRequests. */
export interface PresentmentCurrency {
    code: string;
    symbol: string;
    decimals: number;
    /** Units of this currency per 1 unit of the base. Base itself is 1. */
    rate_to_base?: number;
}

/**
 * Convert a base-currency (AED) amount into the active presentment currency for
 * display. Display-only: the server still charges the base amount from the DB.
 *
 * The rate is `rate_to_base` (units of the target per 1 base unit), so converting
 * out of the base multiplies. Prefer the rate carried on `currency`; otherwise
 * look it up in the shared `currencies` list. Falls back to the base amount when
 * no rate is known.
 */
export function presentmentAmount(baseAmount: number, currency: PresentmentCurrency, currencies: PresentmentCurrency[] = []): number {
    const rate = currency.rate_to_base ?? currencies.find((c) => c.code === currency.code)?.rate_to_base ?? 1;

    return baseAmount * rate;
}

/**
 * Format a base-currency (AED) amount in the active presentment currency,
 * honouring that currency's decimal precision. BDT keeps its ৳ Taka styling.
 */
export function formatPresentment(baseAmount: number, currency: PresentmentCurrency, currencies: PresentmentCurrency[] = []): string {
    const converted = presentmentAmount(baseAmount, currency, currencies);

    if (currency.code === CURRENCY) {
        return formatTaka(converted);
    }

    return `${currency.symbol || currency.code} ${converted.toLocaleString('en-US', {
        minimumFractionDigits: currency.decimals,
        maximumFractionDigits: currency.decimals,
    })}`;
}
