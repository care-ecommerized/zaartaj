import { Trash2 } from 'lucide-react';

export type RateRow = {
    method: string;
    amount: number | string;
    min_threshold: number | string | null;
    max_threshold: number | string | null;
    free_over: number | string | null;
    priority: number | string;
};

export type ZoneFormData = {
    name: string;
    countries: string[];
    priority: number | string;
    is_active: boolean;
    rates: RateRow[];
};

interface Props {
    data: ZoneFormData;
    setData: (key: keyof ZoneFormData, value: ZoneFormData[keyof ZoneFormData]) => void;
    errors: Record<string, string | undefined>;
    methods: string[];
    countryOptions: { code: string; name: string }[];
    processing: boolean;
    submitLabel: string;
    onSubmit: (event: React.FormEvent) => void;
}

const emptyRate: RateRow = { method: 'flat', amount: 0, min_threshold: null, max_threshold: null, free_over: null, priority: 0 };

const field = 'border-sidebar-border/70 bg-background w-full rounded-lg border px-3 py-2 text-sm';
const labelCls = 'text-muted-foreground text-xs font-medium uppercase';

export function ShippingZoneForm({ data, setData, errors, methods, countryOptions, processing, submitLabel, onSubmit }: Props) {
    const toggleCountry = (code: string) => {
        const next = data.countries.includes(code) ? data.countries.filter((c) => c !== code) : [...data.countries, code];
        setData('countries', next);
    };

    const updateRate = (index: number, changes: Partial<RateRow>) => {
        const next = data.rates.map((rate, i) => (i === index ? { ...rate, ...changes } : rate));
        setData('rates', next);
    };

    const addRate = () => setData('rates', [...data.rates, { ...emptyRate }]);
    const removeRate = (index: number) => setData('rates', data.rates.filter((_, i) => i !== index));

    // Empty text inputs come back as '' — persist as null so nullable thresholds
    // clear rather than coercing to 0.
    const numOrNull = (value: string): string | null => (value === '' ? null : value);

    return (
        <form onSubmit={onSubmit} className="flex max-w-3xl flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="name" className={labelCls}>
                        Zone name
                    </label>
                    <input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} className={`mt-1 ${field}`} />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>
                <div>
                    <label htmlFor="priority" className={labelCls}>
                        Priority (higher wins)
                    </label>
                    <input
                        id="priority"
                        type="number"
                        value={data.priority}
                        onChange={(e) => setData('priority', e.target.value)}
                        className={`mt-1 ${field}`}
                    />
                    {errors.priority && <p className="mt-1 text-xs text-red-600">{errors.priority}</p>}
                </div>
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                Active
            </label>

            <div>
                <p className={labelCls}>Countries</p>
                <p className="text-muted-foreground mt-1 text-xs">Select none to make this the catch-all zone for the rest of the world.</p>
                <div className="border-sidebar-border/70 mt-2 grid max-h-56 grid-cols-2 gap-1 overflow-y-auto rounded-lg border p-3 sm:grid-cols-3">
                    {countryOptions.map((c) => (
                        <label key={c.code} className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={data.countries.includes(c.code)} onChange={() => toggleCountry(c.code)} />
                            <span className="truncate">
                                {c.name} <span className="text-muted-foreground">({c.code})</span>
                            </span>
                        </label>
                    ))}
                </div>
                {errors.countries && <p className="mt-1 text-xs text-red-600">{errors.countries}</p>}
            </div>

            <div>
                <div className="flex items-center justify-between">
                    <p className={labelCls}>Rates (AED)</p>
                    <button type="button" onClick={addRate} className="text-primary text-xs font-medium hover:underline">
                        + Add rate
                    </button>
                </div>

                <div className="mt-2 flex flex-col gap-3">
                    {data.rates.map((rate, index) => (
                        <div key={index} className="border-sidebar-border/70 rounded-lg border p-3">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label className={labelCls}>Method</label>
                                    <select value={rate.method} onChange={(e) => updateRate(index, { method: e.target.value })} className={`mt-1 ${field}`}>
                                        {methods.map((m) => (
                                            <option key={m} value={m}>
                                                {m}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className={labelCls}>Amount</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={rate.amount}
                                        onChange={(e) => updateRate(index, { amount: e.target.value })}
                                        className={`mt-1 ${field}`}
                                    />
                                    {errors[`rates.${index}.amount`] && <p className="mt-1 text-xs text-red-600">{errors[`rates.${index}.amount`]}</p>}
                                </div>
                                <div>
                                    <label className={labelCls}>Free over</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={rate.free_over ?? ''}
                                        onChange={(e) => updateRate(index, { free_over: numOrNull(e.target.value) })}
                                        className={`mt-1 ${field}`}
                                    />
                                </div>
                                <div>
                                    <label className={labelCls}>Min (kg / AED)</label>
                                    <input
                                        type="number"
                                        step="0.001"
                                        value={rate.min_threshold ?? ''}
                                        onChange={(e) => updateRate(index, { min_threshold: numOrNull(e.target.value) })}
                                        className={`mt-1 ${field}`}
                                    />
                                </div>
                                <div>
                                    <label className={labelCls}>Max (kg / AED)</label>
                                    <input
                                        type="number"
                                        step="0.001"
                                        value={rate.max_threshold ?? ''}
                                        onChange={(e) => updateRate(index, { max_threshold: numOrNull(e.target.value) })}
                                        className={`mt-1 ${field}`}
                                    />
                                </div>
                                <div>
                                    <label className={labelCls}>Rate priority</label>
                                    <input
                                        type="number"
                                        value={rate.priority}
                                        onChange={(e) => updateRate(index, { priority: e.target.value })}
                                        className={`mt-1 ${field}`}
                                    />
                                </div>
                            </div>
                            <div className="mt-2 flex justify-end">
                                <button type="button" onClick={() => removeRate(index)} className="inline-flex items-center gap-1 text-xs text-red-600 hover:underline">
                                    <Trash2 className="size-3.5" />
                                    Remove
                                </button>
                            </div>
                        </div>
                    ))}

                    {data.rates.length === 0 && (
                        <p className="text-muted-foreground border-sidebar-border/70 rounded-lg border border-dashed px-3 py-6 text-center text-sm">
                            No rates yet. Without a rate this zone falls back to the configured default charge.
                        </p>
                    )}
                </div>
            </div>

            <div className="flex gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50"
                >
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}
