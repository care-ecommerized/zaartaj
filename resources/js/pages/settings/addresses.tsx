import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useTranslation } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Address {
    id: number;
    label: string | null;
    recipient_name: string;
    phone: string;
    address_line: string;
    city: string | null;
    state: string | null;
    postcode: string | null;
    country: string;
    is_default: boolean;
}

interface Country {
    code: string;
    name: string;
}

interface AddressesProps {
    addresses: Address[];
    countries: Country[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Addresses',
        href: '/settings/addresses',
    },
];

type AddressForm = {
    label: string;
    recipient_name: string;
    phone: string;
    address_line: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
    is_default: boolean;
};

const emptyForm: AddressForm = {
    label: '',
    recipient_name: '',
    phone: '',
    address_line: '',
    city: '',
    state: '',
    postcode: '',
    country: '',
    is_default: false,
};

export default function Addresses({ addresses, countries }: AddressesProps) {
    const { t } = useTranslation();
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<AddressForm>({ ...emptyForm });
    const destroyForm = useForm({});

    const startAdd = () => {
        setEditingId(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
    };

    const startEdit = (address: Address) => {
        setEditingId(address.id);
        form.setData({
            label: address.label ?? '',
            recipient_name: address.recipient_name,
            phone: address.phone,
            address_line: address.address_line,
            city: address.city ?? '',
            state: address.state ?? '',
            postcode: address.postcode ?? '',
            country: address.country,
            is_default: address.is_default,
        });
        form.clearErrors();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (editingId === null) {
            form.post(route('addresses.store'), {
                preserveScroll: true,
                onSuccess: () => form.setData({ ...emptyForm }),
            });
        } else {
            form.put(route('addresses.update', editingId), {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingId(null);
                    form.setData({ ...emptyForm });
                },
            });
        }
    };

    const remove = (address: Address) => {
        destroyForm.delete(route('addresses.destroy', address.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('account.addresses.title')} />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall title={t('account.addresses.title')} description={t('account.addresses.description')} />

                    {addresses.length > 0 && (
                        <ul className="space-y-3">
                            {addresses.map((address) => (
                                <li key={address.id} className="rounded-lg border border-border p-4 text-sm">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="font-medium">
                                                {address.recipient_name}
                                                {address.label ? ` · ${address.label}` : ''}
                                                {address.is_default && (
                                                    <span className="ml-2 rounded bg-muted px-2 py-0.5 text-xs">{t('address.default')}</span>
                                                )}
                                            </p>
                                            <p className="mt-1 text-muted-foreground">
                                                {[address.address_line, address.city, address.state, address.postcode, address.country]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </p>
                                            <p className="text-muted-foreground">{address.phone}</p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <Button type="button" size="sm" variant="outline" onClick={() => startEdit(address)}>
                                                {t('address.edit')}
                                            </Button>
                                            <Button type="button" size="sm" variant="ghost" onClick={() => remove(address)}>
                                                {t('address.delete')}
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <p className="text-sm font-medium">{editingId === null ? t('address.add') : t('address.edit')}</p>

                        <div className="grid gap-2">
                            <Label htmlFor="label">{t('address.label')}</Label>
                            <Input id="label" value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} />
                            <InputError message={form.errors.label} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="recipient_name">{t('address.recipient')}</Label>
                            <Input
                                id="recipient_name"
                                value={form.data.recipient_name}
                                onChange={(e) => form.setData('recipient_name', e.target.value)}
                            />
                            <InputError message={form.errors.recipient_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">{t('address.phone')}</Label>
                            <Input id="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                            <InputError message={form.errors.phone} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address_line">{t('address.line')}</Label>
                            <Input
                                id="address_line"
                                value={form.data.address_line}
                                onChange={(e) => form.setData('address_line', e.target.value)}
                            />
                            <InputError message={form.errors.address_line} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="city">{t('address.city')}</Label>
                                <Input id="city" value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                                <InputError message={form.errors.city} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="state">{t('address.state')}</Label>
                                <Input id="state" value={form.data.state} onChange={(e) => form.setData('state', e.target.value)} />
                                <InputError message={form.errors.state} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="postcode">{t('address.postcode')}</Label>
                                <Input id="postcode" value={form.data.postcode} onChange={(e) => form.setData('postcode', e.target.value)} />
                                <InputError message={form.errors.postcode} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">{t('address.country')}</Label>
                                <select
                                    id="country"
                                    value={form.data.country}
                                    onChange={(e) => form.setData('country', e.target.value)}
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm shadow-sm"
                                >
                                    <option value="">{t('address.select_country')}</option>
                                    {countries.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.country} />
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_default}
                                onChange={(e) => form.setData('is_default', e.target.checked)}
                            />
                            {t('address.default')}
                        </label>

                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {t('address.save')}
                            </Button>
                            {editingId !== null && (
                                <Button type="button" variant="ghost" onClick={startAdd}>
                                    {t('address.cancel')}
                                </Button>
                            )}
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
