import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { ProductForm, type ProductFormData, emptyVariant } from '@/components/admin/product-form';
import { ProductMediaPicker, type MediaLimits } from '@/components/admin/product-media-picker';
import { useTranslation } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';

interface Props {
    categories: { id: number; name: string }[];
    statuses: string[];
    baseCurrency: string;
    mediaLimits: MediaLimits;
}

/** The create form carries its media alongside the product fields. */
interface CreateFormData extends ProductFormData {
    images: File[];
    video: File | null;
}

export default function AdminProductCreate({ categories, statuses, baseCurrency, mediaLimits }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Products', href: '/admin/products' },
        { title: t('admin.product.new'), href: '/admin/products/create' },
    ];

    const form = useForm<CreateFormData>({
        title: '',
        title_ar: null,
        handle: null,
        body_html: null,
        body_html_ar: null,
        vendor: null,
        brand: null,
        product_type: null,
        category_id: null,
        status: 'draft',
        seo_title: null,
        seo_title_ar: null,
        seo_description: null,
        seo_description_ar: null,
        option_name: null,
        variants: [emptyVariant()],
        images: [],
        video: null,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        // Inertia switches to multipart on its own once a File is in the payload.
        form.post('/admin/products');
    };

    return (
        <AdminLayout>
            <Head title={t('admin.product.new')} />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">{t('admin.product.new')}</h1>

                <ProductForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors as Record<string, string | undefined>}
                    categories={categories}
                    statuses={statuses}
                    baseCurrency={baseCurrency}
                    processing={form.processing}
                    submitLabel={t('admin.product.save')}
                    onSubmit={submit}
                >
                    <ProductMediaPicker
                        images={form.data.images}
                        video={form.data.video}
                        onImagesChange={(files) => form.setData('images', files)}
                        onVideoChange={(file) => form.setData('video', file)}
                        limits={mediaLimits}
                        errors={form.errors as Record<string, string | undefined>}
                    />
                </ProductForm>

                {form.progress && (
                    <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full transition-all" style={{ width: `${form.progress.percentage ?? 0}%` }} />
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
