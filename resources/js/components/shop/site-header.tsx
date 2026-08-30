import { Link, router, usePage } from '@inertiajs/react';
import { Menu, Search, ShoppingBag, X } from 'lucide-react';
import { useState } from 'react';
import { BrandMark } from '@/components/shop/brand-mark';
import { useTranslation } from '@/lib/i18n';
import { useCart } from '@/lib/shop/cart';
import type { SharedData } from '@/types';

export function SiteHeader() {
    const { itemCount } = useCart();
    const { t, locale, translations } = useTranslation();
    const [mobileOpen, setMobileOpen] = useState(false);
    // Shared by HandleInertiaRequests, so the nav follows whatever has been imported.
    const { shopCategories: categories = [], currency, currencies = [] } = usePage<SharedData>().props;

    // Category names come from the DB (English today); fall back to a per-slug
    // message key when one exists so trivial static categories can show in Arabic.
    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;

    // Display-only: persists the choice in a cookie the server reads next request.
    const changeCurrency = (code: string) => {
        router.post('/currency', { currency: code }, { preserveScroll: true, preserveState: false });
    };

    // Flips the storefront chrome between English (LTR) and Arabic (RTL).
    const changeLocale = (next: string) => {
        if (next === locale) {
            return;
        }
        router.post('/locale', { locale: next }, { preserveScroll: true, preserveState: false });
    };

    return (
        <header className="border-zt-sand bg-zt-cream/95 sticky top-0 z-50 border-b backdrop-blur">
            <div className="bg-zt-teal-deep text-center text-[0.7rem] tracking-[0.22em] text-white/90 uppercase">
                <p className="px-4 py-2.5">{t('header.announcement')}</p>
            </div>

            <div className="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-4 lg:px-8">
                <button
                    type="button"
                    onClick={() => setMobileOpen((open) => !open)}
                    aria-label={mobileOpen ? t('header.menu_close') : t('header.menu_open')}
                    aria-expanded={mobileOpen}
                    className="text-zt-ink lg:hidden"
                >
                    {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                </button>

                <Link href="/" aria-label={t('header.home_aria')}>
                    <BrandMark className="h-14" />
                </Link>

                <nav className="hidden items-center gap-8 lg:flex">
                    {categories.map((category) => (
                        <Link
                            key={category.slug}
                            href={`/shop?category=${category.slug}`}
                            className="text-zt-ink hover:text-zt-teal relative text-[0.72rem] tracking-[0.18em] uppercase transition-colors after:absolute after:-bottom-1.5 after:left-0 after:h-px after:w-0 after:bg-current after:transition-all hover:after:w-full"
                        >
                            {categoryLabel(category.slug, category.name)}
                        </Link>
                    ))}
                </nav>

                <div className="text-zt-ink flex items-center gap-5">
                    {/* Language toggle, sat next to the currency selector. */}
                    <label className="hidden sm:block">
                        <span className="sr-only">{t('header.language')}</span>
                        <select
                            value={locale}
                            onChange={(event) => changeLocale(event.target.value)}
                            aria-label={t('header.language')}
                            className="text-zt-ink hover:text-zt-teal cursor-pointer border-0 bg-transparent text-[0.72rem] tracking-[0.18em] uppercase transition-colors focus:outline-none"
                        >
                            <option value="en">{t('language.en')}</option>
                            <option value="ar">{t('language.ar')}</option>
                        </select>
                    </label>

                    {currencies.length > 1 && (
                        <label className="hidden sm:block">
                            <span className="sr-only">{t('header.currency')}</span>
                            <select
                                value={currency?.code ?? ''}
                                onChange={(event) => changeCurrency(event.target.value)}
                                aria-label={t('header.currency')}
                                className="text-zt-ink hover:text-zt-teal cursor-pointer border-0 bg-transparent text-[0.72rem] tracking-[0.18em] uppercase transition-colors focus:outline-none"
                            >
                                {currencies.map((option) => (
                                    <option key={option.code} value={option.code}>
                                        {option.code}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    <Link href="/shop" aria-label={t('header.search_aria')} className="hover:text-zt-teal hidden transition-colors sm:block">
                        <Search className="size-5" />
                    </Link>

                    <Link
                        href="/cart"
                        aria-label={t(itemCount === 1 ? 'header.cart_aria_one' : 'header.cart_aria_other', { count: itemCount })}
                        className="hover:text-zt-teal relative transition-colors"
                    >
                        <ShoppingBag className="size-5" />
                        {itemCount > 0 && (
                            <span className="bg-zt-gold absolute -top-1.5 -right-2 flex size-4 items-center justify-center rounded-full text-[0.6rem] font-medium text-white">
                                {itemCount}
                            </span>
                        )}
                    </Link>
                </div>
            </div>

            {mobileOpen && (
                <nav className="border-zt-sand bg-zt-cream border-t lg:hidden">
                    <ul className="mx-auto max-w-7xl px-5 py-2">
                        {categories.map((category) => (
                            <li key={category.slug} className="border-zt-sand/70 border-b last:border-0">
                                <Link
                                    href={`/shop?category=${category.slug}`}
                                    onClick={() => setMobileOpen(false)}
                                    className="text-zt-ink flex flex-col py-3.5"
                                >
                                    <span className="text-sm tracking-[0.16em] uppercase">{categoryLabel(category.slug, category.name)}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </header>
    );
}
