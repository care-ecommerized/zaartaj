import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bell,
    FileText,
    LayoutGrid,
    LogOut,
    Mail,
    Menu,
    Package,
    Palette,
    Percent,
    Search,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tags,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { BrandMark } from '@/components/shop/brand-mark';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

interface NavLink {
    /** i18n key for the label. */
    key: string;
    /** English fallback, also the default the key resolves to. */
    label: string;
    href: string;
    icon: LucideIcon;
}

/** Sidebar order matches the reference Shopify-style admin. */
const NAV: NavLink[] = [
    { key: 'admin.nav.dashboard', label: 'Dashboard', href: '/admin', icon: LayoutGrid },
    { key: 'admin.nav.products', label: 'Products', href: '/admin/products', icon: Package },
    { key: 'admin.nav.orders', label: 'Orders', href: '/admin/orders', icon: ShoppingCart },
    { key: 'admin.nav.draft_orders', label: 'Draft Orders', href: '/admin/draft-orders', icon: FileText },
    { key: 'admin.nav.abandoned', label: 'Abandoned Checkouts', href: '/admin/abandoned', icon: ShoppingBag },
    { key: 'admin.nav.customers', label: 'Customers', href: '/admin/customers', icon: Users },
    { key: 'admin.nav.categories', label: 'Categories', href: '/admin/categories', icon: Tags },
    { key: 'admin.nav.discounts', label: 'Discounts', href: '/admin/coupons', icon: Percent },
    { key: 'admin.nav.contact', label: 'Contact', href: '/admin/contact', icon: Mail },
    { key: 'admin.nav.theme', label: 'Theme', href: '/admin/theme', icon: Palette },
    { key: 'admin.nav.settings', label: 'Settings', href: '/admin/settings', icon: Settings },
];

/** Prefix-match the current path, treating /admin as an exact match. */
function isActive(currentPath: string, href: string): boolean {
    if (href === '/admin') {
        return currentPath === '/admin';
    }

    return currentPath === href || currentPath.startsWith(`${href}/`);
}

interface AdminLayoutProps {
    /**
     * Document <title>. Optional: rehomed pages keep their own <Head title>, so
     * the layout only emits a <Head> when a title is supplied here.
     */
    title?: string;
    /** Optional page heading shown in the content header; defaults to `title`. */
    heading?: ReactNode;
    /** Optional actions rendered on the right of the page heading row. */
    actions?: ReactNode;
    children: ReactNode;
}

export default function AdminLayout({ title, heading, actions, children }: AdminLayoutProps) {
    const page = usePage<SharedData>();
    const { auth, locale, direction, adminBadges } = page.props;
    const { t } = useTranslation();
    const currentPath = page.url.split('?')[0];

    const [mobileOpen, setMobileOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    const notificationCount = adminBadges?.total ?? 0;
    const user = auth.user;

    const setLocale = (next: 'en' | 'ar') => {
        if (next === locale) return;
        router.post('/locale', { locale: next }, { preserveScroll: true });
    };

    const sidebar = (
        <div className="bg-zt-teal-deep text-zt-cream flex h-full w-64 flex-col">
            {/* Brand */}
            <div className="border-zt-teal/40 flex h-16 items-center gap-2 border-b px-5">
                <Link href="/admin" className="flex items-center" onClick={() => setMobileOpen(false)}>
                    <BrandMark variant="full" tone="light" className="[&_span]:text-[1.15rem] [&_svg]:h-9" />
                </Link>
                <button
                    type="button"
                    onClick={() => setMobileOpen(false)}
                    className="text-zt-cream/70 hover:text-zt-cream ms-auto lg:hidden"
                    aria-label={t('admin.close_menu')}
                >
                    <X className="size-5" />
                </button>
            </div>

            {/* Nav */}
            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {NAV.map((item) => {
                    const active = isActive(currentPath, item.href);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={() => setMobileOpen(false)}
                            className={cn(
                                'group relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                active
                                    ? 'bg-zt-teal text-white'
                                    : 'text-zt-cream/80 hover:bg-zt-teal/40 hover:text-white',
                            )}
                            aria-current={active ? 'page' : undefined}
                        >
                            {active && (
                                <span
                                    aria-hidden
                                    className="bg-zt-gold-light absolute inset-y-1.5 start-0 w-1 rounded-full"
                                />
                            )}
                            <Icon className={cn('size-4.5 shrink-0', active ? 'text-zt-gold-light' : '')} />
                            <span className="truncate">{t(item.key)}</span>
                        </Link>
                    );
                })}
            </nav>

            <div className="border-zt-teal/40 border-t px-5 py-4 text-xs text-zt-cream/50">
                Zaartaj Elegance
            </div>
        </div>
    );

    return (
        <div dir={direction} className="bg-zt-cream text-zt-ink min-h-screen">
            {title && <Head title={title} />}

            {/* Desktop sidebar (fixed) */}
            <aside className="fixed inset-y-0 start-0 z-30 hidden lg:block">{sidebar}</aside>

            {/* Mobile sidebar (drawer) */}
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        aria-label={t('admin.close_menu')}
                        onClick={() => setMobileOpen(false)}
                        className="absolute inset-0 bg-black/40"
                    />
                    <aside className="absolute inset-y-0 start-0">{sidebar}</aside>
                </div>
            )}

            {/* Content column, offset by the sidebar on desktop */}
            <div className="lg:ms-64">
                {/* Top bar */}
                <header className="border-zt-sand bg-zt-cream/95 sticky top-0 z-20 flex h-16 items-center gap-3 border-b px-4 backdrop-blur sm:px-6">
                    <button
                        type="button"
                        onClick={() => setMobileOpen(true)}
                        className="text-zt-ink/70 hover:text-zt-ink lg:hidden"
                        aria-label={t('admin.open_menu')}
                    >
                        <Menu className="size-6" />
                    </button>

                    {/* Search — GETs the products index for now. TODO(search): a real
                        cross-entity admin search endpoint. */}
                    <form
                        action="/admin/products"
                        method="get"
                        className="border-zt-sand focus-within:border-zt-gold hidden max-w-md flex-1 items-center gap-2 rounded-lg border bg-white px-3 py-2 sm:flex"
                    >
                        <Search className="text-zt-muted size-4 shrink-0" />
                        <input
                            type="search"
                            name="search"
                            placeholder={t('admin.search_placeholder')}
                            className="text-zt-ink placeholder:text-zt-muted w-full bg-transparent text-sm outline-none"
                        />
                    </form>

                    <div className="ms-auto flex items-center gap-1 sm:gap-2">
                        {/* View store */}
                        <a
                            href="/"
                            className="text-zt-teal hover:bg-zt-teal-mist hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium md:inline-flex"
                        >
                            <Store className="size-4" />
                            {t('admin.view_store')}
                        </a>

                        {/* EN / AR toggle */}
                        <div className="border-zt-sand flex items-center rounded-lg border bg-white p-0.5 text-xs font-semibold">
                            {(['en', 'ar'] as const).map((code) => (
                                <button
                                    key={code}
                                    type="button"
                                    onClick={() => setLocale(code)}
                                    className={cn(
                                        'rounded-md px-2.5 py-1 uppercase transition-colors',
                                        locale === code ? 'bg-zt-teal text-white' : 'text-zt-muted hover:text-zt-ink',
                                    )}
                                    aria-pressed={locale === code}
                                >
                                    {code}
                                </button>
                            ))}
                        </div>

                        {/* Notifications */}
                        <Link
                            href="/admin/orders?status=pending"
                            className="text-zt-ink/70 hover:bg-zt-teal-mist hover:text-zt-ink relative rounded-lg p-2"
                            aria-label={t('admin.notifications')}
                        >
                            <Bell className="size-5" />
                            {notificationCount > 0 && (
                                <span className="bg-zt-gold absolute -top-0.5 -end-0.5 flex min-w-4.5 items-center justify-center rounded-full px-1 text-[10px] font-bold text-white">
                                    {notificationCount > 99 ? '99+' : notificationCount}
                                </span>
                            )}
                        </Link>

                        {/* User menu */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setUserMenuOpen((open) => !open)}
                                className="hover:bg-zt-teal-mist flex items-center gap-2 rounded-lg px-1.5 py-1.5"
                                aria-haspopup="menu"
                                aria-expanded={userMenuOpen}
                            >
                                <span className="bg-zt-teal-deep text-zt-cream flex size-8 items-center justify-center rounded-full text-sm font-semibold uppercase">
                                    {user?.name?.charAt(0) ?? 'A'}
                                </span>
                                <span className="text-zt-ink hidden text-sm font-medium sm:block">{user?.name}</span>
                            </button>

                            {userMenuOpen && (
                                <>
                                    <button
                                        type="button"
                                        aria-hidden
                                        tabIndex={-1}
                                        onClick={() => setUserMenuOpen(false)}
                                        className="fixed inset-0 z-10 cursor-default"
                                    />
                                    <div className="border-zt-sand absolute end-0 z-20 mt-2 w-56 rounded-lg border bg-white py-1 shadow-lg">
                                        <div className="border-zt-sand border-b px-4 py-2">
                                            <p className="text-zt-ink truncate text-sm font-medium">{user?.name}</p>
                                            <p className="text-zt-muted truncate text-xs">{user?.email}</p>
                                        </div>
                                        <a href="/" className="text-zt-ink hover:bg-zt-teal-mist flex items-center gap-2 px-4 py-2 text-sm md:hidden">
                                            <Store className="size-4" />
                                            {t('admin.view_store')}
                                        </a>
                                        <Link
                                            href="/settings/profile"
                                            className="text-zt-ink hover:bg-zt-teal-mist flex items-center gap-2 px-4 py-2 text-sm"
                                            onClick={() => setUserMenuOpen(false)}
                                        >
                                            <Settings className="size-4" />
                                            {t('admin.account_settings')}
                                        </Link>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            className="text-zt-ink hover:bg-zt-teal-mist flex w-full items-center gap-2 px-4 py-2 text-start text-sm"
                                            onClick={() => setUserMenuOpen(false)}
                                        >
                                            <LogOut className="size-4" />
                                            {t('admin.logout')}
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page heading — only when a page opts in. Rehomed pages that
                    already render their own <h1> simply omit `heading`. */}
                {(heading || actions) && (
                    <div className="flex flex-wrap items-center justify-between gap-3 px-4 pt-6 pb-2 sm:px-6">
                        {heading ? (
                            <h1 className="font-display text-zt-ink text-2xl font-semibold sm:text-3xl">{heading}</h1>
                        ) : (
                            <span />
                        )}
                        {actions && <div className="flex items-center gap-2">{actions}</div>}
                    </div>
                )}

                <main className="px-4 pb-10 sm:px-6">{children}</main>
            </div>
        </div>
    );
}
