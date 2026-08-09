import { Construction } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useTranslation } from '@/lib/i18n';

interface Props {
    /** English section name, from the placeholder route. */
    section: string;
    /** i18n key for the section label, so it localises when available. */
    titleKey: string;
}

export default function AdminComingSoon({ section, titleKey }: Props) {
    const { t } = useTranslation();
    const label = t(titleKey) === titleKey ? section : t(titleKey);

    return (
        <AdminLayout title={label} heading={label}>
            <div className="border-zt-sand mt-6 flex flex-col items-center justify-center gap-4 rounded-2xl border bg-white/60 px-6 py-20 text-center">
                <span className="bg-zt-teal-mist text-zt-teal flex size-16 items-center justify-center rounded-full">
                    <Construction className="size-8" />
                </span>
                <h2 className="font-display text-zt-ink text-2xl font-semibold">{label}</h2>
                <p className="text-zt-muted max-w-md text-sm">{t('admin.coming_soon.body', { section: label })}</p>
            </div>
        </AdminLayout>
    );
}
