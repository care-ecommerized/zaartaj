import { Link, usePage } from '@inertiajs/react';
import { BrandMark } from '@/components/shop/brand-mark';
import { useTranslation } from '@/lib/i18n';
import type { SharedData } from '@/types';

const HELP_LINKS = [
    { key: 'footer.help.delivery', href: '/shop' },
    { key: 'footer.help.size_guide', href: '/shop' },
    { key: 'footer.help.care', href: '/shop' },
    { key: 'footer.help.contact', href: '/shop' },
];

export function SiteFooter() {
    const { shopCategories: categories = [] } = usePage<SharedData>().props;
    const { t, translations } = useTranslation();

    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;

    return (
        <footer className="bg-zt-teal-deep text-white">
            <div className="mx-auto max-w-7xl px-5 py-16 lg:px-8">
                <div className="grid gap-12 md:grid-cols-2 lg:grid-cols-4">
                    <div className="lg:col-span-2">
                        <BrandMark tone="light" />
                        <p className="mt-5 max-w-sm text-sm leading-relaxed text-white/70">{t('footer.tagline')}</p>
                    </div>

                    <div>
                        <h2 className="text-zt-gold-light text-xs font-medium tracking-[0.22em] uppercase">{t('footer.shop')}</h2>
                        <ul className="mt-5 space-y-3">
                            {categories.map((category) => (
                                <li key={category.slug}>
                                    <Link href={`/shop?category=${category.slug}`} className="text-sm text-white/70 transition-colors hover:text-white">
                                        {categoryLabel(category.slug, category.name)}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <h2 className="text-zt-gold-light text-xs font-medium tracking-[0.22em] uppercase">{t('footer.help')}</h2>
                        <ul className="mt-5 space-y-3">
                            {HELP_LINKS.map((link) => (
                                <li key={link.key}>
                                    <Link href={link.href} className="text-sm text-white/70 transition-colors hover:text-white">
                                        {t(link.key)}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <div className="mt-14 flex flex-col gap-4 border-t border-white/15 pt-8 text-xs text-white/55 sm:flex-row sm:items-center sm:justify-between">
                    <p>{t('footer.rights', { year: new Date().getFullYear() })}</p>
                    <p className="tracking-[0.18em] uppercase">{t('footer.payments')}</p>
                </div>
            </div>
        </footer>
    );
}
