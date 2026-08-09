import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, EyeOff, FolderTree, ImagePlus, Pencil, Plus, Tags, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

interface Category {
    id: number;
    name: string;
    name_ar: string | null;
    slug: string;
    description: string | null;
    position: number;
    is_visible: boolean;
    image_url: string | null;
    products_count: number;
}

interface Props {
    categories: Category[];
}

interface CategoryFormData {
    name: string;
    name_ar: string;
    slug: string;
    description: string;
    is_visible: boolean;
    /** A newly chosen photo to upload; null keeps (or, with remove_image, clears) the current one. */
    image: File | null;
    /** On edit, drop the existing photo without replacing it. */
    remove_image: boolean;
    [key: string]: string | boolean | File | null;
}

const emptyForm: CategoryFormData = {
    name: '',
    name_ar: '',
    slug: '',
    description: '',
    is_visible: true,
    image: null,
    remove_image: false,
};

export default function AdminCategoriesIndex({ categories }: Props) {
    const { t } = useTranslation();

    // t() falls back to the key when a string is missing, so wrap it to supply a
    // readable English default until the admin.categories.* JSON keys land.
    const tr = (key: string, fallback: string) => {
        const value = t(key);
        return value === key ? fallback : value;
    };

    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    // A local object-URL of a freshly chosen file, or the saved image_url when
    // editing, or null when there is no photo.
    const [preview, setPreview] = useState<string | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);

    const form = useForm<CategoryFormData>({ ...emptyForm });

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
        setPreview(null);
        setOpen(true);
    };

    const openEdit = (category: Category) => {
        setEditing(category);
        form.setData({
            name: category.name,
            name_ar: category.name_ar ?? '',
            slug: category.slug,
            description: category.description ?? '',
            is_visible: category.is_visible,
            image: null,
            remove_image: false,
        });
        form.clearErrors();
        setPreview(category.image_url);
        setOpen(true);
    };

    const close = () => {
        setOpen(false);
        setEditing(null);
        setPreview(null);
    };

    const chooseFile = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('image', file);
        form.setData('remove_image', false);
        setPreview(file ? URL.createObjectURL(file) : (editing?.image_url ?? null));
    };

    const dropImage = () => {
        form.setData('image', null);
        form.setData('remove_image', true);
        setPreview(null);
        if (fileInput.current) fileInput.current.value = '';
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const isEdit = Boolean(editing);

        // Files force a multipart request; PUT is method-spoofed via _method. The
        // image field is dropped when no new file was chosen so the server keeps
        // the current photo instead of validating an empty value.
        form.transform((data) => {
            const out: Record<string, unknown> = { ...data };
            if (!out.image) delete out.image;
            if (isEdit) out._method = 'put';
            else delete out.remove_image;
            return out;
        });

        form.post(isEdit ? `/admin/categories/${editing!.id}` : '/admin/categories', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => close(),
        });
    };

    const remove = (category: Category) => {
        const warning =
            category.products_count > 0
                ? tr('admin.categories.delete_confirm_products', ':count products will be uncategorized.').replace(
                      ':count',
                      String(category.products_count),
                  )
                : tr('admin.categories.delete_confirm', 'Delete this category?');

        if (confirm(`${tr('admin.categories.delete', 'Delete')} "${category.name}"?\n\n${warning}`)) {
            router.delete(`/admin/categories/${category.id}`, { preserveScroll: true });
        }
    };

    // Reorder by swapping a row with its neighbour, then POST the full id list.
    const move = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= categories.length) return;

        const ids = categories.map((category) => category.id);
        [ids[index], ids[target]] = [ids[target], ids[index]];

        router.post('/admin/categories/reorder', { ids }, { preserveScroll: true });
    };

    return (
        <AdminLayout
            title={tr('admin.nav.categories', 'Categories')}
            heading={tr('admin.nav.categories', 'Categories')}
            actions={
                <button
                    type="button"
                    onClick={openCreate}
                    className="bg-zt-teal hover:bg-zt-teal-deep inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                >
                    <Plus className="size-4" />
                    {tr('admin.categories.add', 'Add category')}
                </button>
            }
        >
            <div className="mt-4">
                {categories.length === 0 ? (
                    <div className="border-zt-sand flex flex-col items-center justify-center gap-4 rounded-2xl border bg-white/60 px-6 py-20 text-center">
                        <span className="bg-zt-teal-mist text-zt-teal flex size-16 items-center justify-center rounded-full">
                            <FolderTree className="size-8" />
                        </span>
                        <h2 className="font-display text-zt-ink text-xl font-semibold">
                            {tr('admin.categories.empty_title', 'No categories yet')}
                        </h2>
                        <p className="text-zt-muted max-w-md text-sm">
                            {tr('admin.categories.empty_body', 'Create your first category to organise the shop.')}
                        </p>
                        <button
                            type="button"
                            onClick={openCreate}
                            className="bg-zt-teal hover:bg-zt-teal-deep mt-2 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors"
                        >
                            <Plus className="size-4" />
                            {tr('admin.categories.add', 'Add category')}
                        </button>
                    </div>
                ) : (
                    <div className="border-zt-sand overflow-x-auto rounded-2xl border bg-white/60">
                        <table className="w-full text-sm">
                            <thead className="border-zt-sand text-zt-muted border-b text-start text-xs uppercase">
                                <tr>
                                    <th className="w-24 px-4 py-3 font-medium">{tr('admin.categories.order', 'Order')}</th>
                                    <th className="px-4 py-3 text-start font-medium">{tr('admin.categories.name', 'Name')}</th>
                                    <th className="px-4 py-3 text-end font-medium">{tr('admin.categories.products', 'Products')}</th>
                                    <th className="px-4 py-3 font-medium">{tr('admin.categories.visibility', 'Visibility')}</th>
                                    <th className="px-4 py-3 text-end font-medium">{tr('admin.categories.actions', 'Actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.map((category, index) => (
                                    <tr key={category.id} className="border-zt-sand hover:bg-zt-teal-mist/30 border-b last:border-b-0">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => move(index, -1)}
                                                    disabled={index === 0}
                                                    className="text-zt-muted hover:bg-zt-teal-mist hover:text-zt-teal rounded p-1 disabled:cursor-not-allowed disabled:opacity-30"
                                                    aria-label={tr('admin.categories.move_up', 'Move up')}
                                                >
                                                    <ArrowUp className="size-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => move(index, 1)}
                                                    disabled={index === categories.length - 1}
                                                    className="text-zt-muted hover:bg-zt-teal-mist hover:text-zt-teal rounded p-1 disabled:cursor-not-allowed disabled:opacity-30"
                                                    aria-label={tr('admin.categories.move_down', 'Move down')}
                                                >
                                                    <ArrowDown className="size-4" />
                                                </button>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {category.image_url ? (
                                                    <img
                                                        src={category.image_url}
                                                        alt={category.name}
                                                        className="border-zt-sand size-9 shrink-0 rounded-lg border object-cover"
                                                    />
                                                ) : (
                                                    <span className="bg-zt-teal-mist text-zt-teal flex size-9 shrink-0 items-center justify-center rounded-lg">
                                                        <Tags className="size-4" />
                                                    </span>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="text-zt-ink font-medium">{category.name}</p>
                                                    <p className="text-zt-muted text-xs">
                                                        <span dir="ltr">/{category.slug}</span>
                                                        {category.name_ar ? <span className="ms-2" dir="rtl">{category.name_ar}</span> : null}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="text-zt-ink px-4 py-3 text-end tabular-nums">{category.products_count}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                                                    category.is_visible
                                                        ? 'bg-emerald-500/10 text-emerald-600'
                                                        : 'bg-zt-sand/60 text-zt-muted',
                                                )}
                                            >
                                                {category.is_visible ? <Eye className="size-3.5" /> : <EyeOff className="size-3.5" />}
                                                {category.is_visible
                                                    ? tr('admin.categories.visible', 'Visible')
                                                    : tr('admin.categories.hidden', 'Hidden')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(category)}
                                                    className="text-zt-teal hover:bg-zt-teal-mist inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium"
                                                >
                                                    <Pencil className="size-3.5" />
                                                    {tr('admin.categories.edit', 'Edit')}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => remove(category)}
                                                    className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    {tr('admin.categories.delete', 'Delete')}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Slide-over form for create + edit. */}
            {open && (
                <div className="fixed inset-0 z-50 flex justify-end">
                    <button
                        type="button"
                        aria-label={tr('admin.categories.close', 'Close')}
                        onClick={close}
                        className="absolute inset-0 bg-black/40"
                    />
                    <div className="bg-zt-cream relative flex h-full w-full max-w-md flex-col shadow-xl">
                        <div className="border-zt-sand flex items-center justify-between border-b px-6 py-4">
                            <h2 className="font-display text-zt-ink text-lg font-semibold">
                                {editing
                                    ? tr('admin.categories.edit_title', 'Edit category')
                                    : tr('admin.categories.add', 'Add category')}
                            </h2>
                            <button
                                type="button"
                                onClick={close}
                                className="text-zt-muted hover:text-zt-ink rounded-lg p-1"
                                aria-label={tr('admin.categories.close', 'Close')}
                            >
                                <X className="size-5" />
                            </button>
                        </div>

                        <form onSubmit={submit} className="flex flex-1 flex-col overflow-y-auto">
                            <div className="flex-1 space-y-5 px-6 py-5">
                                <div>
                                    <label htmlFor="category-name" className="text-zt-ink mb-1.5 block text-sm font-medium">
                                        {tr('admin.categories.name', 'Name')}
                                    </label>
                                    <input
                                        id="category-name"
                                        type="text"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        className="border-zt-sand focus:border-zt-gold w-full rounded-lg border bg-white px-3 py-2 text-sm outline-none"
                                        autoFocus
                                    />
                                    {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                                </div>

                                <div>
                                    <label htmlFor="category-name-ar" className="text-zt-ink mb-1.5 block text-sm font-medium">
                                        {tr('admin.categories.name_ar', 'Arabic name')}
                                    </label>
                                    <input
                                        id="category-name-ar"
                                        type="text"
                                        dir="rtl"
                                        value={form.data.name_ar}
                                        onChange={(event) => form.setData('name_ar', event.target.value)}
                                        className="border-zt-sand focus:border-zt-gold w-full rounded-lg border bg-white px-3 py-2 text-sm outline-none"
                                    />
                                    {form.errors.name_ar && <p className="mt-1 text-xs text-red-600">{form.errors.name_ar}</p>}
                                </div>

                                <div>
                                    <label htmlFor="category-slug" className="text-zt-ink mb-1.5 block text-sm font-medium">
                                        {tr('admin.categories.slug', 'Slug')}
                                    </label>
                                    <input
                                        id="category-slug"
                                        type="text"
                                        dir="ltr"
                                        value={form.data.slug}
                                        onChange={(event) => form.setData('slug', event.target.value)}
                                        className="border-zt-sand focus:border-zt-gold w-full rounded-lg border bg-white px-3 py-2 text-sm outline-none"
                                        placeholder="auto"
                                    />
                                    <p className="text-zt-muted mt-1 text-xs">
                                        {tr('admin.categories.slug_hint', 'auto-generated from name')}
                                    </p>
                                    {form.errors.slug && <p className="mt-1 text-xs text-red-600">{form.errors.slug}</p>}
                                </div>

                                <div>
                                    <label htmlFor="category-description" className="text-zt-ink mb-1.5 block text-sm font-medium">
                                        {tr('admin.categories.description', 'Description')}
                                    </label>
                                    <textarea
                                        id="category-description"
                                        rows={4}
                                        value={form.data.description}
                                        onChange={(event) => form.setData('description', event.target.value)}
                                        className="border-zt-sand focus:border-zt-gold w-full rounded-lg border bg-white px-3 py-2 text-sm outline-none"
                                    />
                                    {form.errors.description && <p className="mt-1 text-xs text-red-600">{form.errors.description}</p>}
                                </div>

                                <div>
                                    <span className="text-zt-ink mb-1.5 block text-sm font-medium">
                                        {tr('admin.categories.image', 'Category image')}
                                    </span>
                                    <div className="flex items-center gap-4">
                                        <div className="border-zt-sand bg-zt-teal-mist/40 relative flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border">
                                            {preview ? (
                                                <img src={preview} alt="" className="size-full object-cover" />
                                            ) : (
                                                <ImagePlus className="text-zt-teal size-7" />
                                            )}
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <input
                                                ref={fileInput}
                                                id="category-image"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                onChange={chooseFile}
                                                className="text-zt-muted file:bg-zt-teal file:hover:bg-zt-teal-deep block w-full text-xs file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:px-3 file:py-2 file:text-xs file:font-medium file:text-white"
                                            />
                                            {preview && (
                                                <button
                                                    type="button"
                                                    onClick={dropImage}
                                                    className="inline-flex w-fit items-center gap-1.5 text-xs font-medium text-red-600 hover:underline"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    {tr('admin.categories.image_remove', 'Remove image')}
                                                </button>
                                            )}
                                            <p className="text-zt-muted text-xs">
                                                {tr('admin.categories.image_hint', 'JPG, PNG or WEBP. Shown on the storefront category tile.')}
                                            </p>
                                        </div>
                                    </div>
                                    {form.errors.image && <p className="mt-1 text-xs text-red-600">{form.errors.image}</p>}
                                </div>

                                <label className="flex items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_visible}
                                        onChange={(event) => form.setData('is_visible', event.target.checked)}
                                        className="text-zt-teal focus:ring-zt-gold border-zt-sand size-4 rounded"
                                    />
                                    <span className="text-zt-ink text-sm font-medium">
                                        {tr('admin.categories.visible_label', 'Visible in the storefront')}
                                    </span>
                                </label>
                            </div>

                            <div className="border-zt-sand flex items-center justify-end gap-3 border-t px-6 py-4">
                                <button
                                    type="button"
                                    onClick={close}
                                    className="text-zt-ink hover:bg-zt-sand/50 rounded-lg px-4 py-2 text-sm font-medium"
                                >
                                    {tr('admin.categories.cancel', 'Cancel')}
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-zt-teal hover:bg-zt-teal-deep inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60"
                                >
                                    {editing
                                        ? tr('admin.categories.save', 'Save changes')
                                        : tr('admin.categories.create', 'Create category')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
