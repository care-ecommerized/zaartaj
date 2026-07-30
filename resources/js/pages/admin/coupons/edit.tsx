import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { CouponForm, type CouponFormData } from '@/components/coupon-form';
import { type BreadcrumbItem } from '@/types';

interface Coupon extends CouponFormData {
    id: number;
}

interface Props {
    coupon: Coupon;
    types: string[];
    currencyOptions: string[];
    baseCurrency: string;
}

export default function AdminCouponEdit({ coupon, types, currencyOptions, baseCurrency }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Coupons', href: '/admin/coupons' },
        { title: coupon.code, href: `/admin/coupons/${coupon.id}/edit` },
    ];

    const form = useForm<CouponFormData>({
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        currency: coupon.currency,
        min_subtotal: coupon.min_subtotal,
        starts_at: coupon.starts_at,
        ends_at: coupon.ends_at,
        usage_limit: coupon.usage_limit,
        per_user_limit: coupon.per_user_limit,
        is_active: coupon.is_active,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(`/admin/coupons/${coupon.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${coupon.code}`} />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Edit coupon</h1>

                <CouponForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors as Record<string, string | undefined>}
                    types={types}
                    currencyOptions={currencyOptions}
                    baseCurrency={baseCurrency}
                    processing={form.processing}
                    submitLabel="Save changes"
                    onSubmit={submit}
                />
            </div>
        </AppLayout>
    );
}
