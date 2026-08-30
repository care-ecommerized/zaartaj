import { Link, usePage } from '@inertiajs/react';
import { Facebook, Instagram, Linkedin, Mail, MapPin, Phone, Youtube } from 'lucide-react';
import { BrandMark } from '@/components/shop/brand-mark';
import { useTranslation } from '@/lib/i18n';
import type { SharedData } from '@/types';

const INFO_LINKS = [
    { label: 'About Us', href: '/shop' },
    { label: 'Contact Us', href: '/shop' },
    { label: 'Delivery & Returns', href: '/shop' },
    { label: 'Size Guide', href: '/shop' },
    { label: 'Privacy Policy', href: '/shop' },
    { label: 'Terms of Service', href: '/shop' },
];

/** Social channels, each resolving its URL from the store settings (with a
 *  sensible default) so the owner can point them at real profiles from admin. */
const SOCIALS = [
    { label: 'Facebook', settingKey: 'social.facebook', fallback: 'https://facebook.com', Icon: Facebook },
    { label: 'Instagram', settingKey: 'social.instagram', fallback: 'https://instagram.com', Icon: Instagram },
    { label: 'YouTube', settingKey: 'social.youtube', fallback: 'https://youtube.com', Icon: Youtube },
    { label: 'LinkedIn', settingKey: 'social.linkedin', fallback: 'https://linkedin.com', Icon: Linkedin },
] as const;

const PAYMENTS = ['bKash', 'Nagad', 'Visa', 'Mastercard', 'Cash on delivery'];

export function SiteFooter() {
    const { shopCategories: categories = [], storeSettings } = usePage<SharedData>().props;
    const { t, translations } = useTranslation();

    const categoryLabel = (slug: string, name: string) => translations[`category.${slug}`] ?? name;

    // Contact details are editable from admin Settings; fall back to the house
    // defaults for anything the owner has not filled in yet.
    const settings = storeSettings ?? ({} as SharedData['storeSettings']);
    const phone = settings['store.phone']?.trim() || '+880 1700-000000';
    const email = settings['store.email']?.trim() || 'support@zaartaj.com';
    const addressLine =
        [settings['store.address']?.trim(), settings['store.city']?.trim()].filter(Boolean).join(', ') ||
        'Gulshan Avenue, Dhaka 1212, Bangladesh';

    return (
        <footer className="bg-zt-teal-deep text-white">
            <div className="mx-auto max-w-7xl px-5 py-16 lg:px-8">
                <div className="grid gap-12 sm:grid-cols-2 lg:grid-cols-4">
                    {/* 1 — Brand + contact + social */}
                    <div>
                        <BrandMark tone="light" className="h-20" />
                        <p className="mt-5 max-w-xs text-sm leading-relaxed text-white/70">{t('footer.tagline')}</p>

                        <ul className="mt-6 space-y-3 text-sm text-white/70">
                            <li className="flex items-start gap-3">
                                <MapPin className="text-zt-gold-light mt-0.5 size-4 shrink-0" />
                                <span>{addressLine}</span>
                            </li>
                            <li className="flex items-center gap-3">
                                <Phone className="text-zt-gold-light size-4 shrink-0" />
                                <a href={`tel:${phone.replace(/\s+/g, '')}`} className="transition-colors hover:text-white">
                                    {phone}
                                </a>
                            </li>
                            <li className="flex items-center gap-3">
                                <Mail className="text-zt-gold-light size-4 shrink-0" />
                                <a href={`mailto:${email}`} className="transition-colors hover:text-white">
                                    {email}
                                </a>
                            </li>
                        </ul>

                        <div className="mt-6 flex gap-3">
                            {SOCIALS.map(({ label, settingKey, fallback, Icon }) => (
                                <a
                                    key={label}
                                    href={settings[settingKey]?.trim() || fallback}
                                    target="_blank"
                                    rel="noreferrer"
                                    aria-label={label}
                                    className="hover:bg-zt-gold flex size-9 items-center justify-center rounded-full ring-1 ring-white/25 transition-colors"
                                >
                                    <Icon className="size-4" />
                                </a>
                            ))}
                        </div>
                    </div>

                    {/* 2 — Shop */}
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

                    {/* 3 — Information */}
                    <div>
                        <h2 className="text-zt-gold-light text-xs font-medium tracking-[0.22em] uppercase">Information</h2>
                        <ul className="mt-5 space-y-3">
                            {INFO_LINKS.map((link) => (
                                <li key={link.label}>
                                    <Link href={link.href} className="text-sm text-white/70 transition-colors hover:text-white">
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* 4 — Newsletter */}
                    <div>
                        <h2 className="text-zt-gold-light text-xs font-medium tracking-[0.22em] uppercase">Sign Up &amp; Save</h2>
                        <p className="mt-5 text-sm leading-relaxed text-white/70">
                            Subscribe for early access to new arrivals, private offers and styling notes.
                        </p>
                        <form onSubmit={(event) => event.preventDefault()} className="mt-5">
                            <div className="flex overflow-hidden rounded-sm ring-1 ring-white/25 focus-within:ring-zt-gold-light">
                                <input
                                    type="email"
                                    required
                                    placeholder="Your email address"
                                    aria-label="Your email address"
                                    className="min-w-0 flex-1 bg-white/10 px-4 py-3 text-sm text-white placeholder:text-white/50 focus:outline-none"
                                />
                                <button type="submit" className="bg-zt-gold hover:bg-zt-gold-light px-5 text-[0.7rem] font-medium tracking-[0.18em] text-white uppercase transition-colors">
                                    Join
                                </button>
                            </div>
                        </form>

                        <p className="text-zt-gold-light mt-8 text-[0.65rem] font-medium tracking-[0.22em] uppercase">We accept</p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {PAYMENTS.map((method) => (
                                <span key={method} className="rounded-sm bg-white/10 px-2.5 py-1 text-[0.65rem] text-white/80">
                                    {method}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="mt-14 flex flex-col gap-4 border-t border-white/15 pt-8 text-xs text-white/55 sm:flex-row sm:items-center sm:justify-between">
                    <p>{t('footer.rights', { year: new Date().getFullYear() })}</p>
                    <p className="tracking-[0.18em] uppercase">Crafted in Dhaka · Delivered nationwide</p>
                </div>
            </div>
        </footer>
    );
}
