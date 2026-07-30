import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    /** Whether the signed-in user may reach the /admin routes. */
    isAdmin: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

/** The shopper's active presentment currency (display-only). */
export interface ActiveCurrency {
    code: string;
    symbol: string;
    decimals: number;
}

/** A currency offered in the storefront selector. */
export interface CurrencyOption extends ActiveCurrency {
    /** Units of this currency per 1 unit of the base (AED). */
    rate_to_base: number;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** Top-level shop categories, for the header and footer navigation. */
    shopCategories: { slug: string; name: string }[];
    /** The active presentment currency prices are shown in. */
    currency: ActiveCurrency;
    /** The currencies the header selector offers. */
    currencies: CurrencyOption[];
    /** The active UI locale code (`en` or `ar`). */
    locale: string;
    /** Reading direction for the active locale. */
    direction: 'ltr' | 'rtl';
    /** Flat message catalogue for the active locale (key → string). */
    translations: Record<string, string>;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
