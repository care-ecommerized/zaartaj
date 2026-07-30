export type CouponFormData = {
    code: string;
    type: string;
    value: number | string;
    currency: string | null;
    min_subtotal: number | string | null;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | string | null;
    per_user_limit: number | string | null;
    is_active: boolean;
};

interface Props {
    data: CouponFormData;
    setData: (key: keyof CouponFormData, value: CouponFormData[keyof CouponFormData]) => void;
    errors: Record<string, string | undefined>;
    types: string[];
    currencyOptions: string[];
    baseCurrency: string;
    processing: boolean;
    submitLabel: string;
    onSubmit: (event: React.FormEvent) => void;
}

const field = 'border-sidebar-border/70 bg-background w-full rounded-lg border px-3 py-2 text-sm';
const labelCls = 'text-muted-foreground text-xs font-medium uppercase';

export function CouponForm({ data, setData, errors, types, currencyOptions, baseCurrency, processing, submitLabel, onSubmit }: Props) {
    const isFixed = data.type === 'fixed';

    // Empty text inputs come back as '' — persist as null so nullable columns clear.
    const numOrNull = (value: string): string | null => (value === '' ? null : value);

    return (
        <form onSubmit={onSubmit} className="flex max-w-3xl flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="code" className={labelCls}>
                        Code
                    </label>
                    <input
                        id="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())}
                        className={`mt-1 ${field}`}
                    />
                    {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                </div>
                <div>
                    <label htmlFor="type" className={labelCls}>
                        Type
                    </label>
                    <select id="type" value={data.type} onChange={(e) => setData('type', e.target.value)} className={`mt-1 ${field}`}>
                        {types.map((t) => (
                            <option key={t} value={t}>
                                {t}
                            </option>
                        ))}
                    </select>
                    {errors.type && <p className="mt-1 text-xs text-red-600">{errors.type}</p>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="value" className={labelCls}>
                        {isFixed ? 'Amount' : 'Percent off'}
                    </label>
                    <input
                        id="value"
                        type="number"
                        step="0.01"
                        value={data.value}
                        onChange={(e) => setData('value', e.target.value)}
                        className={`mt-1 ${field}`}
                    />
                    {errors.value && <p className="mt-1 text-xs text-red-600">{errors.value}</p>}
                </div>
                {isFixed && (
                    <div>
                        <label htmlFor="currency" className={labelCls}>
                            Currency
                        </label>
                        <select
                            id="currency"
                            value={data.currency ?? ''}
                            onChange={(e) => setData('currency', e.target.value === '' ? null : e.target.value)}
                            className={`mt-1 ${field}`}
                        >
                            <option value="">{baseCurrency} (base)</option>
                            {currencyOptions.map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                        </select>
                        {errors.currency && <p className="mt-1 text-xs text-red-600">{errors.currency}</p>}
                    </div>
                )}
            </div>

            <div>
                <label htmlFor="min_subtotal" className={labelCls}>
                    Minimum subtotal ({baseCurrency})
                </label>
                <input
                    id="min_subtotal"
                    type="number"
                    step="0.01"
                    value={data.min_subtotal ?? ''}
                    onChange={(e) => setData('min_subtotal', numOrNull(e.target.value))}
                    className={`mt-1 ${field}`}
                />
                {errors.min_subtotal && <p className="mt-1 text-xs text-red-600">{errors.min_subtotal}</p>}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="starts_at" className={labelCls}>
                        Starts at
                    </label>
                    <input
                        id="starts_at"
                        type="datetime-local"
                        value={data.starts_at ?? ''}
                        onChange={(e) => setData('starts_at', numOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.starts_at && <p className="mt-1 text-xs text-red-600">{errors.starts_at}</p>}
                </div>
                <div>
                    <label htmlFor="ends_at" className={labelCls}>
                        Ends at
                    </label>
                    <input
                        id="ends_at"
                        type="datetime-local"
                        value={data.ends_at ?? ''}
                        onChange={(e) => setData('ends_at', numOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.ends_at && <p className="mt-1 text-xs text-red-600">{errors.ends_at}</p>}
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label htmlFor="usage_limit" className={labelCls}>
                        Global usage limit
                    </label>
                    <input
                        id="usage_limit"
                        type="number"
                        value={data.usage_limit ?? ''}
                        onChange={(e) => setData('usage_limit', numOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.usage_limit && <p className="mt-1 text-xs text-red-600">{errors.usage_limit}</p>}
                </div>
                <div>
                    <label htmlFor="per_user_limit" className={labelCls}>
                        Per-user limit
                    </label>
                    <input
                        id="per_user_limit"
                        type="number"
                        value={data.per_user_limit ?? ''}
                        onChange={(e) => setData('per_user_limit', numOrNull(e.target.value))}
                        className={`mt-1 ${field}`}
                    />
                    {errors.per_user_limit && <p className="mt-1 text-xs text-red-600">{errors.per_user_limit}</p>}
                </div>
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                Active
            </label>

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
