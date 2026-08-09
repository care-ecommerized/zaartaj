import type { FormDataConvertible } from '@inertiajs/core';
import { Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from '@/lib/i18n';

export interface VariantRow {
    id?: number;
    option_value: string;
    price: number | string;
    compare_at_price: number | string | null;
    sku: string | null;
    weight: number | string | null;
    stock: number | string;
    [key: string]: FormDataConvertible;
}

export interface ProductFormData {
    title: string;
    title_ar: string | null;
    handle: string | null;
    body_html: string | null;
    body_html_ar: string | null;
    vendor: string | null;
    brand: string | null;
    product_type: string | null;
    category_id: number | null;
    status: string;
    seo_title: string | null;
    seo_title_ar: string | null;
    seo_description: string | null;
    seo_description_ar: string | null;
    option_name: string | null;
    variants: VariantRow[];
    // Inertia's useForm<T> requires the shape to satisfy FormDataType.
    [key: string]: FormDataConvertible;
}

interface Category {
    id: number;
    name: string;
}

interface Props {
    data: ProductFormData;
    setData: <K extends keyof ProductFormData>(key: K, value: ProductFormData[K]) => void;
    errors: Record<string, string | undefined>;
    categories: Category[];
    statuses: string[];
    baseCurrency: string;
    processing: boolean;
    submitLabel: string;
    onSubmit: (event: React.FormEvent) => void;
    /** Extra sections rendered inside the form, above the save button. */
    children?: ReactNode;
}

const field = 'border-sidebar-border/70 bg-background w-full rounded-lg border px-3 py-2 text-sm';
const labelCls = 'text-muted-foreground text-xs font-medium uppercase';

function emptyVariant(): VariantRow {
    return { option_value: '', price: '', compare_at_price: '', sku: '', weight: '', stock: 0 };
}

export function ProductForm({ data, setData, errors, categories, statuses, baseCurrency, processing, submitLabel, onSubmit, children }: Props) {
    const { t } = useTranslation();

    const strOrNull = (value: string): string | null => (value === '' ? null : value);

    const updateVariant = (index: number, key: keyof VariantRow, value: string) => {
        const next = data.variants.map((variant, i) => (i === index ? { ...variant, [key]: value } : variant));
        setData('variants', next);
    };

    const addVariant = () => setData('variants', [...data.variants, emptyVariant()]);

    const removeVariant = (index: number) => {
        // Always keep at least one row — a product needs a price.
        if (data.variants.length === 1) {
            return;
        }
        setData('variants', data.variants.filter((_, i) => i !== index));
    };

    return (
        <form onSubmit={onSubmit} className="flex max-w-4xl flex-col gap-8">
            {/* Names & description, English alongside Arabic. */}
            <section className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="title" className={labelCls}>
                        {t('admin.product.title')}
                    </label>
                    <input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} className={`mt-1 ${field}`} />
                    {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                </div>
                <div>
                    <label htmlFor="title_ar" className={labelCls}>
                        {t('admin.product.title_ar')}
                    </label>
                    <input
                        id="title_ar"
                        dir="rtl"
                        value={data.title_ar ?? ''}
                        onChange={(e) => setData('title_ar', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.title_ar && <p className="mt-1 text-xs text-red-600">{errors.title_ar}</p>}
                </div>

                <div>
                    <label htmlFor="body_html" className={labelCls}>
                        {t('admin.product.description')}
                    </label>
                    <textarea
                        id="body_html"
                        rows={5}
                        value={data.body_html ?? ''}
                        onChange={(e) => setData('body_html', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.body_html && <p className="mt-1 text-xs text-red-600">{errors.body_html}</p>}
                </div>
                <div>
                    <label htmlFor="body_html_ar" className={labelCls}>
                        {t('admin.product.description_ar')}
                    </label>
                    <textarea
                        id="body_html_ar"
                        dir="rtl"
                        rows={5}
                        value={data.body_html_ar ?? ''}
                        onChange={(e) => setData('body_html_ar', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.body_html_ar && <p className="mt-1 text-xs text-red-600">{errors.body_html_ar}</p>}
                </div>
            </section>

            {/* Classification. */}
            <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label htmlFor="category_id" className={labelCls}>
                        {t('admin.product.category')}
                    </label>
                    <select
                        id="category_id"
                        value={data.category_id ?? ''}
                        onChange={(e) => setData('category_id', e.target.value === '' ? null : Number(e.target.value))}
                        className={`mt-1 ${field}`}
                    >
                        <option value="">—</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </select>
                    {errors.category_id && <p className="mt-1 text-xs text-red-600">{errors.category_id}</p>}
                </div>
                <div>
                    <label htmlFor="status" className={labelCls}>
                        {t('admin.product.status')}
                    </label>
                    <select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)} className={`mt-1 ${field}`}>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {t(`admin.product.${status}`)}
                            </option>
                        ))}
                    </select>
                    {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                </div>
                <div>
                    <label htmlFor="brand" className={labelCls}>
                        {t('admin.product.brand')}
                    </label>
                    <input
                        id="brand"
                        value={data.brand ?? ''}
                        onChange={(e) => setData('brand', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="vendor" className={labelCls}>
                        {t('admin.product.vendor')}
                    </label>
                    <input
                        id="vendor"
                        value={data.vendor ?? ''}
                        onChange={(e) => setData('vendor', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="product_type" className={labelCls}>
                        {t('admin.product.type')}
                    </label>
                    <input
                        id="product_type"
                        value={data.product_type ?? ''}
                        onChange={(e) => setData('product_type', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="handle" className={labelCls}>
                        {t('admin.product.handle')}
                    </label>
                    <input
                        id="handle"
                        value={data.handle ?? ''}
                        onChange={(e) => setData('handle', strOrNull(e.target.value))}
                        placeholder="auto"
                        className={`mt-1 ${field}`}
                    />
                    {errors.handle && <p className="mt-1 text-xs text-red-600">{errors.handle}</p>}
                </div>
            </section>

            {/* Variants: one row per option value, priced in the base currency. */}
            <section className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">{t('admin.product.add_variant')}</h2>
                    <div className="w-48">
                        <label htmlFor="option_name" className={labelCls}>
                            {t('admin.product.option_name')}
                        </label>
                        <input
                            id="option_name"
                            value={data.option_name ?? ''}
                            onChange={(e) => setData('option_name', strOrNull(e.target.value))}
                            placeholder="Size"
                            className={`mt-1 ${field}`}
                        />
                    </div>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-3 py-2 font-medium">{t('admin.product.option_name')}</th>
                                <th className="px-3 py-2 font-medium">
                                    {t('admin.product.price')} ({baseCurrency})
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('admin.product.compare_at')} ({baseCurrency})
                                </th>
                                <th className="px-3 py-2 font-medium">{t('admin.product.sku')}</th>
                                <th className="px-3 py-2 font-medium">{t('admin.product.weight')}</th>
                                <th className="px-3 py-2 font-medium">{t('admin.product.stock')}</th>
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {data.variants.map((variant, index) => (
                                <tr key={index} className="border-sidebar-border/70 border-t align-top">
                                    <td className="px-3 py-2">
                                        <input
                                            value={variant.option_value}
                                            onChange={(e) => updateVariant(index, 'option_value', e.target.value)}
                                            className={field}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={variant.price}
                                            onChange={(e) => updateVariant(index, 'price', e.target.value)}
                                            className={`w-28 ${field}`}
                                        />
                                        {errors[`variants.${index}.price`] && (
                                            <p className="mt-1 text-xs text-red-600">{errors[`variants.${index}.price`]}</p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={variant.compare_at_price ?? ''}
                                            onChange={(e) => updateVariant(index, 'compare_at_price', e.target.value)}
                                            className={`w-28 ${field}`}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            value={variant.sku ?? ''}
                                            onChange={(e) => updateVariant(index, 'sku', e.target.value)}
                                            className={`w-32 ${field}`}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            value={variant.weight ?? ''}
                                            onChange={(e) => updateVariant(index, 'weight', e.target.value)}
                                            className={`w-24 ${field}`}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            value={variant.stock}
                                            onChange={(e) => updateVariant(index, 'stock', e.target.value)}
                                            className={`w-24 ${field}`}
                                        />
                                        {errors[`variants.${index}.stock`] && (
                                            <p className="mt-1 text-xs text-red-600">{errors[`variants.${index}.stock`]}</p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            onClick={() => removeVariant(index)}
                                            disabled={data.variants.length === 1}
                                            aria-label={t('admin.product.remove')}
                                            className="text-muted-foreground hover:text-red-600 disabled:opacity-30"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {typeof errors.variants === 'string' && <p className="text-xs text-red-600">{errors.variants}</p>}

                <button
                    type="button"
                    onClick={addVariant}
                    className="border-sidebar-border/70 inline-flex w-fit items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                >
                    <Plus className="size-4" />
                    {t('admin.product.add_variant')}
                </button>
            </section>

            {/* SEO, English alongside Arabic. */}
            <section className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="seo_title" className={labelCls}>
                        {t('admin.product.seo_title')}
                    </label>
                    <input
                        id="seo_title"
                        value={data.seo_title ?? ''}
                        onChange={(e) => setData('seo_title', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="seo_title_ar" className={labelCls}>
                        {t('admin.product.seo_title')} ({t('language.ar')})
                    </label>
                    <input
                        id="seo_title_ar"
                        dir="rtl"
                        value={data.seo_title_ar ?? ''}
                        onChange={(e) => setData('seo_title_ar', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="seo_description" className={labelCls}>
                        {t('admin.product.seo_description')}
                    </label>
                    <textarea
                        id="seo_description"
                        rows={3}
                        value={data.seo_description ?? ''}
                        onChange={(e) => setData('seo_description', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
                <div>
                    <label htmlFor="seo_description_ar" className={labelCls}>
                        {t('admin.product.seo_description')} ({t('language.ar')})
                    </label>
                    <textarea
                        id="seo_description_ar"
                        dir="rtl"
                        rows={3}
                        value={data.seo_description_ar ?? ''}
                        onChange={(e) => setData('seo_description_ar', strOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                </div>
            </section>

            {/* Media pickers on create; the edit screen manages media out-of-band. */}
            {children}

            <div className="flex gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50"
                >
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

export { emptyVariant };
