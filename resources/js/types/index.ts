import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    /** Whether the signed-in user may reach the /admin routes. */
    isAdmin: boolean;
}

/** Admin-shell notification counters; null for guests and customers. */
export interface AdminBadges {
    /** Orders awaiting staff confirmation. */
    pendingOrders: number;
    /** Open checkout sessions worth chasing. */
    openCheckouts: number;
    /** Sum of the above — the bell badge count. */
    total: number;
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

/** Store identity + contact + social links, all editable from admin Settings. */
export interface StoreSettings {
    'store.name': string;
    'store.email': string;
    'store.phone': string;
    'store.address': string;
    'store.city': string;
    'store.country': string;
    'social.facebook': string;
    'social.instagram': string;
    'social.youtube': string;
    'social.linkedin': string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    /** Admin-shell notification counters; null unless the viewer is an admin. */
    adminBadges: AdminBadges | null;
    /** Top-level shop categories, for the header and footer navigation. */
    shopCategories: { slug: string; name: string; image?: string | null }[];
    /** Store identity, contact and social links, editable from admin Settings. */
    storeSettings: StoreSettings;
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
    /** Public config for the Tabby/Tamara BNPL promo widgets. */
    bnpl: BnplConfig;
    [key: string]: unknown;
}

/** Public (non-secret) config the BNPL promo widgets need on the client. */
export interface BnplConfig {
    /** Currency the widgets quote in (must be one the provider supports, e.g. AED). */
    currency: string;
    tabby: { publicKey: string; merchantCode: string };
    tamara: { publicKey: string; country: string; lang: string };
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
