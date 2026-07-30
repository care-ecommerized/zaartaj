import { Link } from '@inertiajs/react';

import ShopLayout from '@/layouts/shop-layout';

interface StatusProps {
    payment: {
        reference: string;
        gateway: string;
        amount: string;
        currency: string;
        status: string;
        gateway_transaction_id: string | null;
        failure_reason: string | null;
        paid_at: string | null;
    };
}

export default function Status({ payment }: StatusProps) {
    const paid = payment.status === 'paid';

    return (
        <ShopLayout title={paid ? 'Payment successful — Zaartaj' : 'Payment status — Zaartaj'}>
            <div className="mx-auto max-w-xl px-5 py-20 lg:px-8">
                <p className="zt-eyebrow">Payment</p>
                <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">
                    {paid ? 'Payment successful' : 'Payment not completed'}
                </h1>

                <dl className="border-zt-sand mt-10 grid grid-cols-2 gap-y-4 border-t pt-8 text-sm">
                    <dt className="text-zt-muted">Reference</dt>
                    <dd className="text-zt-ink text-right">{payment.reference}</dd>

                    <dt className="text-zt-muted">Amount</dt>
                    <dd className="text-zt-ink text-right">
                        {payment.amount} {payment.currency}
                    </dd>

                    <dt className="text-zt-muted">Method</dt>
                    <dd className="text-zt-ink text-right capitalize">{payment.gateway}</dd>

                    <dt className="text-zt-muted">Status</dt>
                    <dd className="text-zt-ink text-right capitalize">{payment.status}</dd>

                    {payment.gateway_transaction_id && (
                        <>
                            <dt className="text-zt-muted">Transaction</dt>
                            <dd className="text-zt-ink text-right">{payment.gateway_transaction_id}</dd>
                        </>
                    )}
                </dl>

                {payment.failure_reason && (
                    <p className="mt-6 text-sm text-red-600">{payment.failure_reason}</p>
                )}

                <div className="mt-10 flex flex-col gap-4">
                    <Link
                        href="/shop"
                        className="bg-zt-teal-deep hover:bg-zt-teal px-8 py-4 text-center text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors"
                    >
                        {paid ? 'Continue shopping' : 'Back to shop'}
                    </Link>

                    {!paid && (
                        <Link
                            href="/cart"
                            className="text-zt-muted hover:text-zt-ink text-center text-xs tracking-[0.16em] uppercase transition-colors"
                        >
                            Return to bag
                        </Link>
                    )}
                </div>
            </div>
        </ShopLayout>
    );
}
