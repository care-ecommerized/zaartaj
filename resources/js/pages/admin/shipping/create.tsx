import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { ShippingZoneForm, type ZoneFormData } from '@/components/shipping-zone-form';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Shipping', href: '/admin/shipping' },
    { title: 'New zone', href: '/admin/shipping/create' },
];

interface Props {
    methods: string[];
    countryOptions: { code: string; name: string }[];
}

export default function AdminShippingCreate({ methods, countryOptions }: Props) {
    const form = useForm<ZoneFormData>({
        name: '',
        countries: [],
        priority: 0,
        is_active: true,
        rates: [{ method: 'flat', amount: 0, min_threshold: null, max_threshold: null, free_over: null, priority: 0 }],
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admin/shipping');
    };

    return (
        <AdminLayout>
            <Head title="New shipping zone" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">New shipping zone</h1>

                <ShippingZoneForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors as Record<string, string | undefined>}
                    methods={methods}
                    countryOptions={countryOptions}
                    processing={form.processing}
                    submitLabel="Create zone"
                    onSubmit={submit}
                />
            </div>
        </AdminLayout>
    );
}
