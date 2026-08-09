import { Link, router } from '@inertiajs/react';
import { Download, Search, Upload } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';
import { formatMoney, type Paginated } from '@/lib/shop/catalog';

interface CustomerRow {
    email: string;
    name: string;
    phone: string | null;
    is_registered: boolean;
    orders_count: number;
    delivered_count: number;
    total_spent: number;
    last_ordered: string | null;
    cities: string[];
    countries: string[];
}

interface Filters {
    search?: string | null;
    city?: string | null;
    country?: string | null;
    sort?: string | null;
    min_amount?: string | null;
    max_amount?: string | null;
    date_from?: string | null;
    date_to?: string | null;
}

interface CountryOption {
    code: string;
    name: string;
}

interface Props {
    customers: Paginated<CustomerRow>;
    filters: Filters;
    countries: CountryOption[];
    sorts: string[];
    baseCurrency: string;
}

function formatWhen(iso: string | null): string {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
}

/** A compact, wrapping strip of chips (cities / countries), truncated politely. */
function Chips({ values }: { values: string[] }) {
    if (values.length === 0) {
        return <span className="text-zt-muted">—</span>;
    }

    const shown = values.slice(0, 3);
    const extra = values.length - shown.length;

    return (
        <div className="flex flex-wrap gap-1">
            {shown.map((value) => (
                <span key={value} className="bg-zt-teal-mist text-zt-teal-deep rounded-full px-2 py-0.5 text-xs">
                    {value}
                </span>
            ))}
            {extra > 0 && <span className="text-zt-muted text-xs">+{extra}</span>}
        </div>
    );
}

export default function AdminCustomersIndex({ customers, filters, countries, sorts, baseCurrency }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [city, setCity] = useState(filters.city ?? '');
    const [minAmount, setMinAmount] = useState(filters.min_amount ?? '');
    const [maxAmount, setMaxAmount] = useState(filters.max_amount ?? '');

    const applyFilter = (changes: Record<string, string | undefined>) => {
        const next: Record<string, string | undefined> = {
            search: filters.search ?? undefined,
            city: filters.city ?? undefined,
            country: filters.country ?? undefined,
            sort: filters.sort ?? undefined,
            min_amount: filters.min_amount ?? undefined,
            max_amount: filters.max_amount ?? undefined,
            date_from: filters.date_from ?? undefined,
            date_to: filters.date_to ?? undefined,
            ...changes,
        };

        router.get('/admin/customers', next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const exportQuery = new URLSearchParams(
        Object.entries({
            search: filters.search,
            city: filters.city,
            country: filters.country,
            sort: filters.sort,
            min_amount: filters.min_amount,
            max_amount: filters.max_amount,
            date_from: filters.date_from,
            date_to: filters.date_to,
        }).filter(([, value]) => value != null && value !== '') as [string, string][],
    ).toString();

    return (
        <AdminLayout
            title={t('admin.customers.title')}
            heading={t('admin.customers.title')}
            actions={
                <div className="flex items-center gap-2">
                    {/* TODO(import): the reference shows an Import button; real CSV
                        import of customers is not built yet. */}
                    <button
                        type="button"
                        disabled
                        className="border-zt-sand text-zt-muted inline-flex cursor-not-allowed items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium opacity-60"
                        title={t('admin.customers.import_soon')}
                    >
                        <Upload className="size-4" />
                        {t('admin.customers.import')}
                    </button>
                    <a
                        href={`/admin/customers/export${exportQuery ? `?${exportQuery}` : ''}`}
                        className="border-zt-sand text-zt-teal hover:bg-zt-teal-mist inline-flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium"
                    >
                        <Download className="size-4" />
                        {t('admin.customers.export')}
                    </a>
                </div>
            }
        >
            <div className="mt-4 flex flex-col gap-6">
                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ search: search || undefined });
                        }}
                        className="border-zt-sand focus-within:border-zt-gold flex items-center gap-2 rounded-lg border bg-white px-3 py-2"
                    >
                        <Search className="text-zt-muted size-4 shrink-0" />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('admin.customers.search_name')}
                            className="text-zt-ink placeholder:text-zt-muted w-60 bg-transparent text-sm outline-none"
                        />
                    </form>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ city: city || undefined });
                        }}
                        className="border-zt-sand focus-within:border-zt-gold flex items-center gap-2 rounded-lg border bg-white px-3 py-2"
                    >
                        <Search className="text-zt-muted size-4 shrink-0" />
                        <input
                            type="search"
                            value={city}
                            onChange={(event) => setCity(event.target.value)}
                            placeholder={t('admin.customers.search_city')}
                            className="text-zt-ink placeholder:text-zt-muted w-40 bg-transparent text-sm outline-none"
                        />
                    </form>

                    <select
                        value={filters.country ?? ''}
                        onChange={(event) => applyFilter({ country: event.target.value || undefined })}
                        className="border-zt-sand rounded-lg border bg-white px-3 py-2 text-sm"
                    >
                        <option value="">{t('admin.customers.all_countries')}</option>
                        {countries.map((country) => (
                            <option key={country.code} value={country.code}>
                                {country.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={filters.sort ?? 'newest'}
                        onChange={(event) => applyFilter({ sort: event.target.value === 'newest' ? undefined : event.target.value })}
                        className="border-zt-sand rounded-lg border bg-white px-3 py-2 text-sm"
                    >
                        {sorts.map((sort) => (
                            <option key={sort} value={sort}>
                                {t(`admin.customers.sort.${sort}`)}
                            </option>
                        ))}
                    </select>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ min_amount: minAmount || undefined, max_amount: maxAmount || undefined });
                        }}
                        className="flex items-end gap-3"
                    >
                        <label className="text-zt-muted flex flex-col gap-1 text-xs">
                            {t('admin.customers.amount_more')}
                            <input
                                type="number"
                                inputMode="decimal"
                                min="0"
                                value={minAmount}
                                onChange={(event) => setMinAmount(event.target.value)}
                                onBlur={() => applyFilter({ min_amount: minAmount || undefined })}
                                className="border-zt-sand text-zt-ink w-28 rounded-lg border bg-white px-2 py-1.5 text-sm"
                            />
                        </label>
                        <label className="text-zt-muted flex flex-col gap-1 text-xs">
                            {t('admin.customers.amount_less')}
                            <input
                                type="number"
                                inputMode="decimal"
                                min="0"
                                value={maxAmount}
                                onChange={(event) => setMaxAmount(event.target.value)}
                                onBlur={() => applyFilter({ max_amount: maxAmount || undefined })}
                                className="border-zt-sand text-zt-ink w-28 rounded-lg border bg-white px-2 py-1.5 text-sm"
                            />
                        </label>
                    </form>

                    <label className="text-zt-muted flex flex-col gap-1 text-xs">
                        {t('admin.customers.date_from')}
                        <input
                            type="date"
                            value={filters.date_from ?? ''}
                            onChange={(event) => applyFilter({ date_from: event.target.value || undefined })}
                            className="border-zt-sand text-zt-ink rounded-lg border bg-white px-2 py-1.5 text-sm"
                        />
                    </label>
                    <label className="text-zt-muted flex flex-col gap-1 text-xs">
                        {t('admin.customers.date_to')}
                        <input
                            type="date"
                            value={filters.date_to ?? ''}
                            onChange={(event) => applyFilter({ date_to: event.target.value || undefined })}
                            className="border-zt-sand text-zt-ink rounded-lg border bg-white px-2 py-1.5 text-sm"
                        />
                    </label>
                </div>

                {/* Customers table */}
                <div className="border-zt-sand overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="border-zt-sand text-zt-muted border-b text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">#</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.name')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.email')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.phone')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.cities')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.countries')}</th>
                                <th className="px-4 py-3 font-medium">{t('admin.customers.col.last_ordered')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('admin.customers.col.total_spent')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('admin.customers.col.delivered')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.map((customer, index) => {
                                const rowNumber = (customers.current_page - 1) * 25 + index + 1;

                                return (
                                    <tr key={customer.email} className="border-zt-sand hover:bg-zt-cream/60 border-t">
                                        <td className="text-zt-muted px-4 py-3 tabular-nums">{rowNumber}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/admin/orders?search=${encodeURIComponent(customer.email)}`}
                                                className="text-zt-teal font-medium hover:underline"
                                            >
                                                {customer.name}
                                            </Link>
                                            {!customer.is_registered && (
                                                <span className="bg-zt-sand/60 text-zt-muted ms-2 rounded-full px-1.5 py-0.5 text-[10px] font-medium uppercase">
                                                    {t('admin.customers.guest')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-zt-muted px-4 py-3">{customer.email}</td>
                                        <td className="text-zt-muted px-4 py-3">{customer.phone ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Chips values={customer.cities} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <Chips values={customer.countries} />
                                        </td>
                                        <td className="text-zt-muted px-4 py-3">{formatWhen(customer.last_ordered)}</td>
                                        <td className="text-zt-ink px-4 py-3 text-right tabular-nums">
                                            {formatMoney(customer.total_spent, baseCurrency)}
                                        </td>
                                        <td className="text-zt-ink px-4 py-3 text-right tabular-nums">{customer.delivered_count}</td>
                                    </tr>
                                );
                            })}

                            {customers.data.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="text-zt-muted px-4 py-12 text-center">
                                        {t('admin.customers.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {customers.last_page > 1 && (
                    <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-2">
                        {customers.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={
                                    'rounded-lg border px-3 py-1.5 text-xs ' +
                                    (link.active
                                        ? 'border-zt-teal bg-zt-teal text-white'
                                        : link.url
                                          ? 'border-zt-sand hover:bg-zt-cream'
                                          : 'border-zt-sand/50 text-zt-muted/50 pointer-events-none')
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AdminLayout>
    );
}
