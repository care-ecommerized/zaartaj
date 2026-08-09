import { useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    Check,
    Coins,
    FileText,
    Percent,
    Share2,
    Truck,
    type LucideIcon,
} from 'lucide-react';
import { type ReactNode } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    /** Every canonical setting key mapped to its stored-or-default value. */
    settings: Record<string, string>;
    /** The base currency the catalogue is priced in (config('payment.currency')). */
    baseCurrency: string;
    /** Number of active currencies, for the read-only Operations panel. */
    currencyCount: number;
    /** Number of active shipping zones, for the read-only Operations panel. */
    zoneCount: number;
}

/** Expand a flat `{'store.name': 'x'}` map into a nested `{store:{name:'x'}}` object. */
function undot(flat: Record<string, string>): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(flat)) {
        const parts = key.split('.');
        let node = result;

        parts.forEach((part, index) => {
            if (index === parts.length - 1) {
                node[part] = value;
            } else {
                node[part] = (node[part] as Record<string, unknown>) ?? {};
                node = node[part] as Record<string, unknown>;
            }
        });
    }

    return result;
}

/** The four policy documents, each with an EN and AR body. */
const POLICIES = [
    { base: 'policy.privacy', key: 'admin.settings.policy.privacy', label: 'Privacy Policy' },
    { base: 'policy.terms', key: 'admin.settings.policy.terms', label: 'Terms & Conditions' },
    { base: 'policy.shipping', key: 'admin.settings.policy.shipping', label: 'Shipping Policy' },
    { base: 'policy.returns', key: 'admin.settings.policy.returns', label: 'Returns & Refunds' },
] as const;

export default function AdminSettings({ settings, baseCurrency, currencyCount, zoneCount }: Props) {
    const { t } = useTranslation();
    const form = useForm<Record<string, string>>({ ...settings });

    /** Translate a key, falling back to a plain-English label when it is absent. */
    const tr = (key: string, fallback: string): string => {
        const value = t(key);
        return value === key ? fallback : value;
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        // The fields bind to flat dotted keys for simple markup; the server reads
        // them via dot-notation, so expand them into a nested object on the way out.
        form.transform((data) => undot(data));
        form.put('/admin/settings', { preserveScroll: true });
    };

    const heading = tr('admin.nav.settings', 'Settings');

    return (
        <AdminLayout title={heading} heading={heading}>
            <form onSubmit={submit} className="mt-4 flex flex-col gap-6 pb-24">
                {/* Store details */}
                <Section
                    icon={Building2}
                    title={tr('admin.settings.store.title', 'Store details')}
                    description={tr('admin.settings.store.description', 'Your store identity and how customers reach you.')}
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            label={tr('admin.settings.store.name', 'Store name')}
                            name="store.name"
                            form={form}
                            required
                        />
                        <Field
                            label={tr('admin.settings.store.email', 'Contact email')}
                            name="store.email"
                            type="email"
                            form={form}
                        />
                        <Field
                            label={tr('admin.settings.store.phone', 'Phone')}
                            name="store.phone"
                            form={form}
                        />
                        <Field
                            label={tr('admin.settings.store.city', 'City')}
                            name="store.city"
                            form={form}
                        />
                        <Field
                            label={tr('admin.settings.store.address', 'Address')}
                            name="store.address"
                            form={form}
                            className="sm:col-span-2"
                        />
                        <Field
                            label={tr('admin.settings.store.country', 'Country (ISO code)')}
                            name="store.country"
                            form={form}
                            maxLength={2}
                            className="uppercase"
                        />
                    </div>
                </Section>

                {/* Social links */}
                <Section
                    icon={Share2}
                    title={tr('admin.settings.social.title', 'Social links')}
                    description={tr('admin.settings.social.description', 'Profiles linked from the storefront footer.')}
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Facebook" name="social.facebook" form={form} placeholder="https://facebook.com/…" />
                        <Field label="Instagram" name="social.instagram" form={form} placeholder="https://instagram.com/…" />
                        <Field label="YouTube" name="social.youtube" form={form} placeholder="https://youtube.com/…" />
                        <Field label="LinkedIn" name="social.linkedin" form={form} placeholder="https://linkedin.com/…" />
                    </div>
                </Section>

                {/* Policies */}
                <Section
                    icon={FileText}
                    title={tr('admin.settings.policies.title', 'Store policies')}
                    description={tr('admin.settings.policies.description', 'Shown on the storefront in English and Arabic.')}
                >
                    <div className="flex flex-col gap-8">
                        {POLICIES.map((policy) => (
                            <div key={policy.base} className="flex flex-col gap-3">
                                <h3 className="font-display text-zt-ink text-base font-semibold">
                                    {tr(policy.key, policy.label)}
                                </h3>
                                <div className="grid gap-5 lg:grid-cols-2">
                                    <TextArea
                                        label={tr('admin.settings.lang.en', 'English')}
                                        name={`${policy.base}.en`}
                                        form={form}
                                    />
                                    <TextArea
                                        label={tr('admin.settings.lang.ar', 'Arabic')}
                                        name={`${policy.base}.ar`}
                                        form={form}
                                        dir="rtl"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>

                {/* Notifications */}
                <Section
                    icon={Building2}
                    title={tr('admin.settings.notifications.title', 'Notifications')}
                    description={tr('admin.settings.notifications.description', 'Where new-order alerts are sent.')}
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            label={tr('admin.settings.notifications.order_email', 'New-order notification email')}
                            name="notifications.order_email"
                            type="email"
                            form={form}
                        />
                    </div>
                </Section>

                {/* Operations — read-only */}
                <Section
                    icon={Coins}
                    title={tr('admin.settings.operations.title', 'Operations')}
                    description={tr('admin.settings.operations.description', 'Configured elsewhere in the admin.')}
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <OperationsCard
                            icon={Coins}
                            label={tr('admin.settings.operations.currencies', 'Currencies')}
                            value={`${currencyCount} active · base ${baseCurrency}`}
                            href="/admin/currencies"
                        />
                        <OperationsCard
                            icon={Truck}
                            label={tr('admin.settings.operations.shipping', 'Shipping zones')}
                            value={`${zoneCount} active`}
                            href="/admin/shipping"
                        />
                        <OperationsCard
                            icon={Percent}
                            label={tr('admin.settings.operations.discounts', 'Discounts')}
                            value={tr('admin.settings.operations.manage', 'Manage coupons')}
                            href="/admin/coupons"
                        />
                    </div>
                </Section>

                {/* Sticky save bar */}
                <div className="border-zt-sand bg-zt-cream/95 fixed inset-x-0 bottom-0 z-10 border-t backdrop-blur lg:ms-64">
                    <div className="flex items-center justify-end gap-4 px-4 py-3 sm:px-6">
                        {form.recentlySuccessful && (
                            <span className="text-zt-teal inline-flex items-center gap-1.5 text-sm font-medium">
                                <Check className="size-4" />
                                {tr('admin.settings.saved', 'Saved')}
                            </span>
                        )}
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-zt-teal hover:bg-zt-teal-deep inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-60"
                        >
                            {tr('admin.settings.save', 'Save changes')}
                        </button>
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}

type SettingsForm = ReturnType<typeof useForm<Record<string, string>>>;

interface SectionProps {
    icon: LucideIcon;
    title: string;
    description: string;
    children: ReactNode;
}

function Section({ icon: Icon, title, description, children }: SectionProps) {
    return (
        <section className="border-zt-sand rounded-2xl border bg-white p-5 sm:p-6">
            <header className="mb-5 flex items-start gap-3">
                <span className="bg-zt-teal-mist text-zt-teal flex size-10 shrink-0 items-center justify-center rounded-full">
                    <Icon className="size-5" />
                </span>
                <div>
                    <h2 className="font-display text-zt-ink text-lg font-semibold">{title}</h2>
                    <p className="text-zt-muted text-sm">{description}</p>
                </div>
            </header>
            {children}
        </section>
    );
}

interface FieldProps {
    label: string;
    name: string;
    form: SettingsForm;
    type?: string;
    required?: boolean;
    placeholder?: string;
    maxLength?: number;
    className?: string;
}

function Field({ label, name, form, type = 'text', required, placeholder, maxLength, className }: FieldProps) {
    const error = form.errors[name];

    return (
        <label className={`flex flex-col gap-1.5 ${className ?? ''}`}>
            <span className="text-zt-ink text-sm font-medium">
                {label}
                {required && <span className="text-zt-gold ms-0.5">*</span>}
            </span>
            <input
                type={type}
                name={name}
                value={form.data[name] ?? ''}
                onChange={(event) => form.setData(name, event.target.value)}
                placeholder={placeholder}
                maxLength={maxLength}
                className="border-zt-sand focus:border-zt-gold text-zt-ink placeholder:text-zt-muted rounded-lg border bg-white px-3 py-2 text-sm outline-none"
            />
            {error && <span className="text-sm text-red-600">{error}</span>}
        </label>
    );
}

interface TextAreaProps {
    label: string;
    name: string;
    form: SettingsForm;
    dir?: 'ltr' | 'rtl';
}

function TextArea({ label, name, form, dir }: TextAreaProps) {
    const error = form.errors[name];

    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-zt-ink text-sm font-medium">{label}</span>
            <textarea
                name={name}
                dir={dir}
                rows={5}
                value={form.data[name] ?? ''}
                onChange={(event) => form.setData(name, event.target.value)}
                className="border-zt-sand focus:border-zt-gold text-zt-ink min-h-28 rounded-lg border bg-white px-3 py-2 text-sm outline-none"
            />
            {error && <span className="text-sm text-red-600">{error}</span>}
        </label>
    );
}

interface OperationsCardProps {
    icon: LucideIcon;
    label: string;
    value: string;
    href: string;
}

function OperationsCard({ icon: Icon, label, value, href }: OperationsCardProps) {
    return (
        <a
            href={href}
            className="border-zt-sand hover:border-zt-gold group flex items-center gap-3 rounded-xl border bg-white p-4 transition-colors"
        >
            <span className="bg-zt-teal-mist text-zt-teal flex size-9 shrink-0 items-center justify-center rounded-full">
                <Icon className="size-4.5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-zt-ink text-sm font-medium">{label}</p>
                <p className="text-zt-muted truncate text-xs">{value}</p>
            </div>
            <ArrowUpRight className="text-zt-muted group-hover:text-zt-teal size-4 shrink-0" />
        </a>
    );
}
