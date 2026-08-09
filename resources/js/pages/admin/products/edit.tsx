import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Film, Trash2, UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { ProductForm, type ProductFormData, type VariantRow } from '@/components/admin/product-form';
import { type MediaLimits } from '@/components/admin/product-media-picker';
import { useTranslation } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';

interface ProductImage {
    id: number;
    url: string;
    position: number;
    alt: string | null;
}

interface EditProduct extends ProductFormData {
    id: number;
    handle: string;
}

interface ProductVideo {
    url: string;
    mime: string | null;
}

interface Props {
    product: EditProduct;
    images: ProductImage[];
    video: ProductVideo | null;
    categories: { id: number; name: string }[];
    statuses: string[];
    baseCurrency: string;
    mediaLimits: MediaLimits;
}

export default function AdminProductEdit({ product, images, video, categories, statuses, baseCurrency, mediaLimits }: Props) {
    const { t } = useTranslation();
    const fileInput = useRef<HTMLInputElement>(null);
    const videoInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState<number | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Products', href: '/admin/products' },
        { title: product.title, href: `/admin/products/${product.handle}/edit` },
    ];

    const form = useForm<ProductFormData>({
        title: product.title,
        title_ar: product.title_ar,
        handle: product.handle,
        body_html: product.body_html,
        body_html_ar: product.body_html_ar,
        vendor: product.vendor,
        brand: product.brand,
        product_type: product.product_type,
        category_id: product.category_id,
        status: product.status,
        seo_title: product.seo_title,
        seo_title_ar: product.seo_title_ar,
        seo_description: product.seo_description,
        seo_description_ar: product.seo_description_ar,
        option_name: product.option_name,
        variants: product.variants as VariantRow[],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(`/admin/products/${product.handle}`);
    };

    // Media is managed against the saved product, out-of-band from the main form.
    const uploadOptions = (input: React.RefObject<HTMLInputElement | null>) => ({
        forceFormData: true,
        preserveScroll: true,
        onStart: () => setUploading(true),
        onProgress: (event?: { percentage?: number }) => setProgress(event?.percentage ?? null),
        onFinish: () => {
            setUploading(false);
            setProgress(null);
            if (input.current) {
                input.current.value = '';
            }
        },
    });

    const uploadImages = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);
        if (files.length === 0) {
            return;
        }

        router.post(`/admin/products/${product.handle}/images`, { images: files }, uploadOptions(fileInput));
    };

    const uploadVideo = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        // POST replaces any existing clip — a product carries one video.
        router.post(`/admin/products/${product.handle}/video`, { video: file }, uploadOptions(videoInput));
    };

    const deleteVideo = () => {
        if (confirm(`${t('admin.product.remove')} — ${t('admin.product.video')}?`)) {
            router.delete(`/admin/products/${product.handle}/video`, { preserveScroll: true });
        }
    };

    const deleteImage = (id: number) => {
        router.delete(`/admin/products/${product.handle}/images/${id}`, { preserveScroll: true });
    };

    const move = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= images.length) {
            return;
        }
        const ids = images.map((image) => image.id);
        [ids[index], ids[target]] = [ids[target], ids[index]];
        router.post(`/admin/products/${product.handle}/images/reorder`, { ids }, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title={`${t('admin.product.edit')} â€” ${product.title}`} />

            <div className="flex flex-col gap-8 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <h1 className="text-2xl font-semibold">{t('admin.product.edit')}</h1>

                    <div className="flex items-center gap-3">
                        <Link
                            href={`/shop/${product.handle}`}
                            className="border-sidebar-border/70 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm"
                        >
                            View on storefront
                            <ExternalLink className="size-3.5" />
                        </Link>
                        <button
                            type="button"
                            onClick={() => {
                                if (confirm(`${t('admin.product.delete')}?`)) {
                                    router.delete(`/admin/products/${product.handle}`);
                                }
                            }}
                            className="inline-flex items-center gap-2 rounded-lg border border-red-500/40 px-4 py-2 text-sm text-red-600"
                        >
                            <Trash2 className="size-4" />
                            {t('admin.product.delete')}
                        </button>
                    </div>
                </div>

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
                />

                {/* Image manager: immediate upload, delete, and up/down reordering. */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-medium">
                            {t('admin.product.images')} ({images.length})
                        </h2>
                        <button
                            type="button"
                            onClick={() => fileInput.current?.click()}
                            disabled={uploading}
                            className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50"
                        >
                            <UploadCloud className="size-4" />
                            {t('admin.product.upload_images')}
                        </button>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            multiple
                            onChange={uploadImages}
                            className="hidden"
                        />
                    </div>

                    <p className="text-muted-foreground text-xs">
                        {t('admin.product.images_hint_sub', { count: mediaLimits.imageBatchMax, size: mediaLimits.imageMaxMb })}
                    </p>

                    {images.length > 0 && (
                        <div className="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8">
                            {images.map((image, index) => (
                                <figure key={image.id} className="group relative space-y-1">
                                    <div className="bg-muted aspect-square overflow-hidden rounded-lg">
                                        <img src={image.url} alt={image.alt ?? ''} className="size-full object-contain" />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex gap-1">
                                            <button
                                                type="button"
                                                onClick={() => move(index, -1)}
                                                disabled={index === 0}
                                                aria-label="Move up"
                                                className="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            >
                                                <ArrowUp className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => move(index, 1)}
                                                disabled={index === images.length - 1}
                                                aria-label="Move down"
                                                className="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            >
                                                <ArrowDown className="size-3.5" />
                                            </button>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => deleteImage(image.id)}
                                            aria-label={t('admin.product.delete')}
                                            className="text-muted-foreground hover:text-red-600"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </div>
                                </figure>
                            ))}
                        </div>
                    )}
                </section>

                {/* Video manager: one clip, uploaded/replaced or removed. */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-medium">{t('admin.product.video')}</h2>
                        <button
                            type="button"
                            onClick={() => videoInput.current?.click()}
                            disabled={uploading}
                            className="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50"
                        >
                            <Film className="size-4" />
                            {video ? t('admin.product.replace_video') : t('admin.product.upload_video')}
                        </button>
                        <input
                            ref={videoInput}
                            type="file"
                            accept="video/mp4,video/webm,video/quicktime"
                            onChange={uploadVideo}
                            className="hidden"
                        />
                    </div>

                    {video ? (
                        <div className="flex flex-wrap items-start gap-4">
                            <video src={video.url} controls preload="metadata" className="bg-muted max-h-64 rounded-xl">
                                {video.mime && <source src={video.url} type={video.mime} />}
                            </video>
                            <button type="button" onClick={deleteVideo} className="inline-flex items-center gap-1 text-sm text-red-600">
                                <Trash2 className="size-4" />
                                {t('admin.product.remove')}
                            </button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-xs">{t('admin.product.video_hint_sub', { size: mediaLimits.videoMaxMb })}</p>
                    )}
                </section>

                {progress !== null && (
                    <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full transition-all" style={{ width: `${progress}%` }} />
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
