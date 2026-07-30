import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import ShopLayout from '@/layouts/shop-layout';
import { formatMoney, formatTaka } from '@/lib/shop/catalog';

const labels: Record<string, string> = {
    bkash: 'bKash',
    nagad: 'Nagad',
    stripe: 'Card (Stripe)',
    tap: 'Card / Apple Pay (Tap)',
};

interface GatewayOption {
    value: string;
    currency: string;
    amount: number | null;
}

interface CheckoutProps {
    gateways: GatewayOption[];
    baseCurrency: string;
    baseAmount: number | null;
}

export default function Checkout({ gateways, baseCurrency, baseAmount }: CheckoutProps) {
    const { data, setData, post, processing, errors } = useForm({
        gateway: gateways[0]?.value ?? '',
        amount: baseAmount ? String(baseAmount) : '',
        payer_reference: '',
    });

    const selected = gateways.find((g) => g.value === data.gateway);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('payments.store'));
    };

    return (
        <ShopLayout title="Checkout — Zaartaj Elegance">
            <div className="mx-auto max-w-xl px-5 py-14 lg:px-8">
                <p className="zt-eyebrow">Payment</p>
                <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">Checkout</h1>

                {baseAmount !== null && (
                    <p className="text-zt-muted mt-4 text-sm">
                        Amount due <span className="text-zt-ink font-medium">{formatTaka(baseAmount)}</span>
                    </p>
                )}

                <form onSubmit={submit} className="mt-10 flex flex-col gap-8">
                    <div>
                        <span className="text-zt-ink text-[0.72rem] font-medium tracking-[0.2em] uppercase">Pay with</span>
                        <div className="mt-4 flex flex-wrap gap-3">
                            {gateways.map((gateway) => (
                                <button
                                    key={gateway.value}
                                    type="button"
                                    onClick={() => setData('gateway', gateway.value)}
                                    className={`flex-1 border px-4 py-4 text-[0.72rem] font-medium tracking-[0.2em] uppercase transition-colors ${
                                        data.gateway === gateway.value
                                            ? 'border-zt-teal-deep text-zt-teal-deep'
                                            : 'border-zt-sand text-zt-muted hover:text-zt-ink'
                                    }`}
                                >
                                    {labels[gateway.value] ?? gateway.value}
                                </button>
                            ))}
                        </div>
                        {errors.gateway && <p className="mt-2 text-sm text-red-600">{errors.gateway}</p>}
                    </div>

                    {/* When the chosen gateway settles abroad, show what will actually be charged. */}
                    {selected && selected.currency !== baseCurrency && selected.amount !== null && (
                        <p className="border-zt-sand bg-zt-sand/30 border px-4 py-3 text-sm">
                            You will be charged <span className="text-zt-ink font-medium">{formatMoney(selected.amount, selected.currency)}</span>{' '}
                            in {selected.currency}.
                        </p>
                    )}

                    <div>
                        <label htmlFor="amount" className="text-zt-ink text-[0.72rem] font-medium tracking-[0.2em] uppercase">
                            Amount ({baseCurrency})
                        </label>
                        <input
                            id="amount"
                            type="number"
                            step="0.01"
                            min="1"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            readOnly={baseAmount !== null}
                            required
                            className="border-zt-sand focus:border-zt-teal-deep mt-3 block w-full border bg-transparent px-4 py-3 text-sm outline-none read-only:opacity-70"
                        />
                        {errors.amount && <p className="mt-2 text-sm text-red-600">{errors.amount}</p>}
                    </div>

                    <div>
                        <label htmlFor="payer_reference" className="text-zt-ink text-[0.72rem] font-medium tracking-[0.2em] uppercase">
                            Mobile number (optional)
                        </label>
                        <input
                            id="payer_reference"
                            value={data.payer_reference}
                            onChange={(e) => setData('payer_reference', e.target.value)}
                            placeholder="01XXXXXXXXX"
                            className="border-zt-sand focus:border-zt-teal-deep mt-3 block w-full border bg-transparent px-4 py-3 text-sm outline-none"
                        />
                        {errors.payer_reference && <p className="mt-2 text-sm text-red-600">{errors.payer_reference}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-zt-teal-deep hover:bg-zt-teal px-8 py-4 text-center text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors disabled:opacity-60"
                    >
                        Continue to payment
                    </button>
                </form>
            </div>
        </ShopLayout>
    );
}
