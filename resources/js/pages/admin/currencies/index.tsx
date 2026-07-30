import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Currencies', href: '/admin/currencies' }];

interface AdminCurrency {
    code: string;
    name: string;
    symbol: string;
    decimals: number;
    rate_to_base: number;
    is_active: boolean;
    is_base: boolean;
    manual_override: boolean;
    rate_updated_at: string | null;
}

interface Props {
    currencies: AdminCurrency[];
}

interface RowState {
    rate_to_base: string;
    is_active: boolean;
    manual_override: boolean;
}

function CurrencyRow({ currency }: { currency: AdminCurrency }) {
    const [state, setState] = useState<RowState>({
        rate_to_base: String(currency.rate_to_base),
        is_active: currency.is_active,
        manual_override: currency.manual_override,
    });
    const [saving, setSaving] = useState(false);

    const dirty =
        state.rate_to_base !== String(currency.rate_to_base) ||
        state.is_active !== currency.is_active ||
        state.manual_override !== currency.manual_override;

    const save = () => {
        router.put(
            `/admin/currencies/${currency.code}`,
            {
                rate_to_base: Number(state.rate_to_base),
                is_active: state.is_active,
                manual_override: state.manual_override,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <tr className="border-sidebar-border/70 hover:bg-muted/40 border-t">
            <td className="px-4 py-3">
                <div className="font-medium">
                    {currency.code}
                    {currency.is_base && <span className="bg-muted text-muted-foreground ml-2 rounded-full px-2 py-0.5 text-xs">base</span>}
                </div>
                <p className="text-muted-foreground text-xs">
                    {currency.symbol} · {currency.name} · {currency.decimals} dp
                </p>
            </td>
            <td className="px-4 py-3 text-right">
                <input
                    type="number"
                    step="0.00000001"
                    min="0"
                    value={state.rate_to_base}
                    disabled={currency.is_base}
                    onChange={(event) => setState((prev) => ({ ...prev, rate_to_base: event.target.value }))}
                    className="border-sidebar-border/70 bg-background w-36 rounded-lg border px-3 py-1.5 text-right text-sm tabular-nums disabled:opacity-50"
                />
            </td>
            <td className="px-4 py-3 text-center">
                <input
                    type="checkbox"
                    checked={state.is_active}
                    disabled={currency.is_base}
                    onChange={(event) => setState((prev) => ({ ...prev, is_active: event.target.checked }))}
                    className="size-4 disabled:opacity-50"
                    aria-label={`${currency.code} active`}
                />
            </td>
            <td className="px-4 py-3 text-center">
                <input
                    type="checkbox"
                    checked={state.manual_override}
                    disabled={currency.is_base}
                    onChange={(event) => setState((prev) => ({ ...prev, manual_override: event.target.checked }))}
                    className="size-4 disabled:opacity-50"
                    aria-label={`${currency.code} manual override`}
                />
            </td>
            <td className="text-muted-foreground px-4 py-3 text-sm">
                {currency.rate_updated_at ? new Date(currency.rate_updated_at).toLocaleString() : '—'}
            </td>
            <td className="px-4 py-3 text-right">
                <button
                    type="button"
                    onClick={save}
                    disabled={currency.is_base || !dirty || saving}
                    className="bg-primary text-primary-foreground rounded-lg px-3 py-1.5 text-sm font-medium disabled:opacity-40"
                >
                    {saving ? 'Saving…' : 'Save'}
                </button>
            </td>
        </tr>
    );
}

export default function AdminCurrenciesIndex({ currencies }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currencies" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Currencies</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Rates are units of the currency per 1 base unit. Pin a rate with manual override to keep the daily FX job from
                        touching it.
                    </p>
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 font-medium">Currency</th>
                                <th className="px-4 py-3 text-right font-medium">Rate to base</th>
                                <th className="px-4 py-3 text-center font-medium">Active</th>
                                <th className="px-4 py-3 text-center font-medium">Override</th>
                                <th className="px-4 py-3 font-medium">Rate updated</th>
                                <th className="px-4 py-3 text-right font-medium">Save</th>
                            </tr>
                        </thead>
                        <tbody>
                            {currencies.map((currency) => (
                                <CurrencyRow key={currency.code} currency={currency} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
