import { Film, ImageIcon, Trash2, UploadCloud, VideoIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from '@/lib/i18n';

export interface MediaLimits {
    imageMaxMb: number;
    videoMaxMb: number;
    imageBatchMax: number;
    /** All the files in one request have to fit inside PHP's post_max_size. */
    requestMaxMb: number;
}

interface Props {
    images: File[];
    video: File | null;
    onImagesChange: (files: File[]) => void;
    onVideoChange: (file: File | null) => void;
    limits: MediaLimits;
    errors: Record<string, string | undefined>;
}

const dropzone =
    'border-sidebar-border/70 flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed px-4 py-10 text-center text-sm transition-colors';

/**
 * Object URLs for a list of files, revoked when the list changes or unmounts.
 * Without the revoke each re-pick would leak the previous blob for the life of
 * the tab.
 */
function useObjectUrls(files: File[]): string[] {
    const urls = useMemo(() => files.map((file) => URL.createObjectURL(file)), [files]);

    useEffect(() => () => urls.forEach((url) => URL.revokeObjectURL(url)), [urls]);

    return urls;
}

/**
 * The same for a single optional file. Kept separate so the <video> src is
 * stable across renders — a fresh blob URL each render would restart playback.
 */
function useObjectUrl(file: File | null): string | null {
    const url = useMemo(() => (file ? URL.createObjectURL(file) : null), [file]);

    useEffect(() => () => {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }, [url]);

    return url;
}

/**
 * Media staged on the create form.
 *
 * Nothing is uploaded here — the files ride along with the product's POST and
 * are stored once the row exists, so a product can be created complete with its
 * gallery and clip in a single save. The edit screen manages media against a
 * saved product instead, one request per change.
 */
export function ProductMediaPicker({ images, video, onImagesChange, onVideoChange, limits, errors }: Props) {
    const { t } = useTranslation();

    const imageInput = useRef<HTMLInputElement>(null);
    const videoInput = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState<'images' | 'video' | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const previews = useObjectUrls(images);
    const videoPreview = useObjectUrl(video);

    const tooBig = (file: File, maxMb: number) => file.size > maxMb * 1024 * 1024;

    const megabytes = (files: (File | null)[]) => files.reduce((total, file) => total + (file?.size ?? 0), 0) / (1024 * 1024);

    // Everything posts in one request, so the whole selection has to fit too.
    const overRequestLimit = (nextImages: File[], nextVideo: File | null) =>
        megabytes([...nextImages, nextVideo]) > limits.requestMaxMb;

    const addImages = (incoming: File[]) => {
        const pictures = incoming.filter((file) => file.type.startsWith('image/'));
        const withinSize = pictures.filter((file) => !tooBig(file, limits.imageMaxMb));
        const next = [...images, ...withinSize].slice(0, limits.imageBatchMax);

        if (overRequestLimit(next, video)) {
            setNotice(t('admin.product.request_limit', { size: limits.requestMaxMb }));

            return;
        }

        if (withinSize.length < pictures.length) {
            setNotice(t('admin.product.image_too_large', { size: limits.imageMaxMb }));
        } else if (next.length < images.length + withinSize.length) {
            setNotice(t('admin.product.image_limit', { count: limits.imageBatchMax }));
        } else {
            setNotice(null);
        }

        onImagesChange(next);
    };

    const setVideo = (file: File | null) => {
        if (file && tooBig(file, limits.videoMaxMb)) {
            setNotice(t('admin.product.video_too_large', { size: limits.videoMaxMb }));

            return;
        }

        if (file && overRequestLimit(images, file)) {
            setNotice(t('admin.product.request_limit', { size: limits.requestMaxMb }));

            return;
        }

        setNotice(null);
        onVideoChange(file);
    };

    const removeImage = (index: number) => onImagesChange(images.filter((_, i) => i !== index));

    const drop = (kind: 'images' | 'video') => (event: React.DragEvent) => {
        event.preventDefault();
        setDragging(null);

        const files = Array.from(event.dataTransfer.files);

        if (kind === 'images') {
            addImages(files);
        } else {
            setVideo(files.find((file) => file.type.startsWith('video/')) ?? null);
        }
    };

    const allow = (kind: 'images' | 'video') => (event: React.DragEvent) => {
        event.preventDefault();
        setDragging(kind);
    };

    // Nested error keys land as `images.0`, so surface whichever file failed.
    const imageError = errors.images ?? Object.entries(errors).find(([key]) => key.startsWith('images.'))?.[1];

    return (
        <section className="flex flex-col gap-6">
            {/* Images: multi-select, previewed and removable before saving. */}
            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">
                        {t('admin.product.images')}
                        {images.length > 0 && ` (${images.length})`}
                    </h2>
                    <button
                        type="button"
                        onClick={() => imageInput.current?.click()}
                        className="border-sidebar-border/70 inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                    >
                        <UploadCloud className="size-4" />
                        {t('admin.product.upload_images')}
                    </button>
                    <input
                        ref={imageInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif"
                        multiple
                        onChange={(event) => {
                            addImages(Array.from(event.target.files ?? []));
                            event.target.value = '';
                        }}
                        className="hidden"
                    />
                </div>

                <button
                    type="button"
                    onClick={() => imageInput.current?.click()}
                    onDragOver={allow('images')}
                    onDragLeave={() => setDragging(null)}
                    onDrop={drop('images')}
                    className={`${dropzone} ${dragging === 'images' ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'}`}
                >
                    <ImageIcon className="text-muted-foreground size-7" />
                    <span>{t('admin.product.images_hint')}</span>
                    <span className="text-muted-foreground text-xs">
                        {t('admin.product.images_hint_sub', { count: limits.imageBatchMax, size: limits.imageMaxMb })}
                    </span>
                </button>

                {images.length > 0 && (
                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-8">
                        {images.map((file, index) => (
                            <figure key={`${file.name}-${index}`} className="space-y-1">
                                <div className="bg-muted aspect-square overflow-hidden rounded-lg">
                                    {previews[index] && <img src={previews[index]} alt={file.name} className="size-full object-cover" />}
                                </div>
                                <div className="flex items-center justify-between gap-1">
                                    <span className="text-muted-foreground truncate text-[11px]">{file.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => removeImage(index)}
                                        aria-label={t('admin.product.remove')}
                                        className="text-muted-foreground hover:text-red-600"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            </figure>
                        ))}
                    </div>
                )}

                {imageError && <p className="text-xs text-red-600">{imageError}</p>}
            </div>

            {/* Video: a single clip, replaced by picking another. */}
            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-medium">{t('admin.product.video')}</h2>
                    <button
                        type="button"
                        onClick={() => videoInput.current?.click()}
                        className="border-sidebar-border/70 inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                    >
                        <Film className="size-4" />
                        {t('admin.product.upload_video')}
                    </button>
                    <input
                        ref={videoInput}
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime"
                        onChange={(event) => {
                            setVideo(event.target.files?.[0] ?? null);
                            event.target.value = '';
                        }}
                        className="hidden"
                    />
                </div>

                {video && videoPreview ? (
                    <div className="flex flex-wrap items-center gap-4">
                        <video src={videoPreview} controls className="bg-muted max-h-56 rounded-xl" />
                        <div className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">{video.name}</span>
                            <span className="text-muted-foreground text-xs">{(video.size / (1024 * 1024)).toFixed(1)} MB</span>
                            <button
                                type="button"
                                onClick={() => onVideoChange(null)}
                                className="inline-flex w-fit items-center gap-1 text-xs text-red-600"
                            >
                                <Trash2 className="size-3.5" />
                                {t('admin.product.remove')}
                            </button>
                        </div>
                    </div>
                ) : (
                    <button
                        type="button"
                        onClick={() => videoInput.current?.click()}
                        onDragOver={allow('video')}
                        onDragLeave={() => setDragging(null)}
                        onDrop={drop('video')}
                        className={`${dropzone} ${dragging === 'video' ? 'border-primary bg-primary/5' : 'hover:bg-muted/40'}`}
                    >
                        <VideoIcon className="text-muted-foreground size-7" />
                        <span>{t('admin.product.video_hint')}</span>
                        <span className="text-muted-foreground text-xs">{t('admin.product.video_hint_sub', { size: limits.videoMaxMb })}</span>
                    </button>
                )}

                {errors.video && <p className="text-xs text-red-600">{errors.video}</p>}
            </div>

            {notice && <p className="text-xs text-amber-600">{notice}</p>}
        </section>
    );
}
