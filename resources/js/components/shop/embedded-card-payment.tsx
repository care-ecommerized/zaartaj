import { router } from '@inertiajs/react';
import {
    CardCvcElement,
    CardExpiryElement,
    CardNumberElement,
    Elements,
    useElements,
    useStripe,
} from '@stripe/react-stripe-js';
import { loadStripe, type Stripe, type StripeElementStyle } from '@stripe/stripe-js';
import { HelpCircle, Lock } from 'lucide-react';
import { useMemo, useState } from 'react';

/** Laravel's XSRF-TOKEN cookie, url-decoded, for the JSON card POST. */
function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export interface CardPayload {
    customer: Record<string, string>;
    payment_method: string;
    items: { slug: string; size: string; quantity: number }[];
}

interface EmbeddedCardPaymentProps {
    /** Which processor backs the card fields — only 'stripe' is live so far. */
    provider: string;
    publishableKey: string;
    /** Built lazily at pay time so it always reflects the latest form state. */
    buildPayload: () => CardPayload;
    /** e.g. "AED 100.00" — shown on the pay button. */
    amountLabel: string;
    /** Cleared on a successful charge. */
    onPaid?: () => void;
}

const elementStyle: StripeElementStyle = {
    base: {
        fontSize: '15px',
        color: '#1f2a2e',
        '::placeholder': { color: '#9aa5a8' },
    },
    invalid: { color: '#dc2626' },
};

const cellClass = 'border-zt-sand focus-within:border-zt-teal flex items-center border bg-white px-4 py-3.5';

/** The live Stripe card form + pay button, mounted inside an Elements provider. */
function CardForm({ buildPayload, amountLabel, onPaid }: Omit<EmbeddedCardPaymentProps, 'provider' | 'publishableKey'>) {
    const stripe = useStripe();
    const elements = useElements();
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const pay = async () => {
        if (!stripe || !elements || submitting) {
            return;
        }
        setSubmitting(true);
        setError(null);

        try {
            // 1. Place the order server-side and get a PaymentIntent client secret.
            const response = await fetch('/checkout/card', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() },
                body: JSON.stringify(buildPayload()),
            });
            const data = await response.json();

            if (!response.ok) {
                setError(data.message ?? 'We could not start the payment. Please check your details.');
                setSubmitting(false);
                return;
            }

            // 2. Confirm the card entirely client-side; the number never hit our server.
            const card = elements.getElement(CardNumberElement);
            const result = await stripe.confirmCardPayment(data.client_secret, {
                payment_method: { card: card!, billing_details: { name } },
            });

            if (result.error) {
                // The order stands as unpaid; the customer can retry or pick another method.
                setError(result.error.message ?? 'Your card could not be charged.');
                setSubmitting(false);
                return;
            }

            // 3. Settle server-side (authoritative) and land on the confirmation page.
            onPaid?.();
            router.post(data.reconcile_url, {}, { preserveState: false });
        } catch {
            setError('Something went wrong reaching the payment service. Please try again.');
            setSubmitting(false);
        }
    };

    return (
        <div className="border-zt-sand mt-3 space-y-3 border-t pt-4">
            <div>
                <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Card number</label>
                <div className={`mt-1.5 ${cellClass}`}>
                    <div className="flex-1">
                        <CardNumberElement options={{ style: elementStyle, showIcon: true }} />
                    </div>
                    <Lock className="text-zt-muted size-4" />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Expiry (MM / YY)</label>
                    <div className={`mt-1.5 ${cellClass}`}>
                        <div className="flex-1">
                            <CardExpiryElement options={{ style: elementStyle }} />
                        </div>
                    </div>
                </div>
                <div>
                    <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Security code</label>
                    <div className={`mt-1.5 ${cellClass}`}>
                        <div className="flex-1">
                            <CardCvcElement options={{ style: elementStyle }} />
                        </div>
                        <HelpCircle className="text-zt-muted size-4" />
                    </div>
                </div>
            </div>

            <div>
                <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Name on card</label>
                <input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    autoComplete="cc-name"
                    className="border-zt-sand focus:border-zt-teal mt-1.5 w-full border bg-white px-4 py-3 text-sm focus:outline-none"
                />
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <button
                type="button"
                onClick={pay}
                disabled={!stripe || submitting}
                className="bg-zt-teal-deep hover:bg-zt-teal flex w-full items-center justify-center gap-2 px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors disabled:opacity-50"
            >
                <Lock className="size-3.5" />
                {submitting ? 'Processing…' : `Pay ${amountLabel}`}
            </button>
        </div>
    );
}

/**
 * A non-interactive preview of the card fields, shown before a Stripe key is
 * configured. Inputs are disabled and never submitted — no card data is ever
 * collected here; the live Stripe fields replace this once the key is set.
 */
function CardPreview() {
    const previewCell = 'border-zt-sand text-zt-muted mt-1.5 flex items-center justify-between border bg-white px-4 py-3.5 text-sm';

    return (
        <div className="border-zt-sand mt-3 space-y-3 border-t pt-4">
            <div>
                <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Card number</label>
                <div className={previewCell}>
                    <span>1234 1234 1234 1234</span>
                    <Lock className="size-4" />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Expiry (MM / YY)</label>
                    <div className={previewCell}>MM / YY</div>
                </div>
                <div>
                    <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Security code</label>
                    <div className={previewCell}>
                        <span>CVC</span>
                        <HelpCircle className="size-4" />
                    </div>
                </div>
            </div>
            <div>
                <label className="text-zt-muted text-[0.68rem] tracking-[0.14em] uppercase">Name on card</label>
                <div className={previewCell}>Name on card</div>
            </div>
            <p className="text-zt-muted text-[0.7rem] leading-relaxed">
                Card entry activates once the payment gateway is configured. No card details are collected here.
            </p>
        </div>
    );
}

/**
 * Embedded card entry for a card gateway. The live card fields are the
 * provider's own iframes (Stripe Elements) — the PAN never touches our server.
 * Only Stripe is wired for live entry so far; other providers (Tap) show the
 * disabled preview until their SDK is integrated, as does an unconfigured key.
 */
export default function EmbeddedCardPayment({ provider, publishableKey, ...props }: EmbeddedCardPaymentProps) {
    const stripePromise = useMemo<Promise<Stripe | null> | null>(
        () => (provider === 'stripe' && publishableKey ? loadStripe(publishableKey) : null),
        [provider, publishableKey],
    );

    if (!stripePromise) {
        return <CardPreview />;
    }

    return (
        <Elements stripe={stripePromise}>
            <CardForm {...props} />
        </Elements>
    );
}
