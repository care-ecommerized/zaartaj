import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { ShippingZoneForm, type RateRow, type ZoneFormData } from '@/components/shipping-zone-form';
import { type BreadcrumbItem } from '@/types';

interface Zone {
    id: number;
    name: string;
    countries: string[];
    priority: number;
    is_active: boolean;
    rates: RateRow[];
}

interface Props {
    zone: Zone;
    methods: string[];
    countryOptions: { code: string; name: string }[];
}

export default function AdminShippingEdit({ zone, methods, countryOptions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Shipping', href: '/admin/shipping' },
        { title: zone.name, href: `/admin/shipping/${zone.id}/edit` },
    ];

    const form = useForm<ZoneFormData>({
        name: zone.name,
        countries: zone.countries,
        priority: zone.priority,
        is_active: zone.is_active,
        rates: zone.rates,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(`/admin/shipping/${zone.id}`);
    };

    return (
        <AdminLayout>
            <Head title={`Edit ${zone.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold">Edit shipping zone</h1>

                <ShippingZoneForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors as Record<string, string | undefined>}
                    methods={methods}
                    countryOptions={countryOptions}
                    processing={form.processing}
                    submitLabel="Save changes"
                    onSubmit={submit}
                />
            </div>
        </AdminLayout>
    );
}
