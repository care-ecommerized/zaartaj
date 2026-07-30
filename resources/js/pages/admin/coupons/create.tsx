import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { CouponForm, type CouponFormData } from '@/components/coupon-form';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Coupons', href: '/admin/coupons' },
    { title: 'New coupon', href: '/admin/coupons/create' },
];

interface Props {
    types: string[];
    currencyOptions: string[];
    baseCurrency: string;
}

export default function AdminCouponCreate({ types, currencyOptions, baseCurrency }: Props) {
    const form = useForm<CouponFormData>({
        code: '',
        type: 'percent',
        value: '',
        currency: null,
        min_subtotal: null,
        starts_at: null,
        ends_at: null,
        usage_limit: null,
        per_user_limit: null,
        is_active: true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/coupons');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New coupon" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">New coupon</h1>

                <CouponForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors as Record<string, string | undefined>}
                    types={types}
                    currencyOptions={currencyOptions}
                    baseCurrency={baseCurrency}
                    processing={form.processing}
                    submitLabel="Create coupon"
                    onSubmit={submit}
                />
            </div>
        </AppLayout>
    );
}
