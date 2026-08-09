import { router, useForm, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import EmbeddedCardPayment, { type CardPayload } from '@/components/shop/embedded-card-payment';
import { PaymentLogo } from '@/components/shop/payment-logos';
import { ProductFigure } from '@/components/shop/product-figure';
import ShopLayout from '@/layouts/shop-layout';
import { useTranslation } from '@/lib/i18n';
import { useCart } from '@/lib/shop/cart';
import { formatMoney, formatTaka } from '@/lib/shop/catalog';

/** Laravel's XSRF-TOKEN cookie, url-decoded, for the bare capture fetch. */
function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** An extra explainer line shown under a method once it is selected. */
const PAYMENT_NOTES: Record<string, string> = {
    cod: 'Pay with cash when your order is delivered.',
    tabby: 'Split in 4 — interest-free.',
    tamara: 'Pay later in monthly installments.',
};

interface ShippingRatePreview {
    method: string;
    amount: number;
    min_threshold: number | null;
    max_threshold: number | null;
    free_over: number | null;
}

interface ShippingZonePreview {
    countries: string[];
    // Lowercased district names this zone is scoped to (e.g. ["dhaka"]).
    // Empty = the zone prices the whole country.
    districts: string[];
    priority: number;
    rates: ShippingRatePreview[];
}

interface SavedAddress {
    id: number;
    label: string | null;
    recipient: string;
    phone: string;
    address_line: string;
    city: string | null;
    state: string | null;
    postcode: string | null;
    country: string;
    is_default: boolean;
}

interface CheckoutProps {
    districts: string[];
    countries: { code: string; name: string }[];
    shippingZones: ShippingZonePreview[];
    paymentMethods: { value: string; label: string; currency: string }[];
    // Methods whose card form is embedded on-page (keyed by method value).
    cardGateways: Record<string, { provider: string; publishable_key: string }>;
    baseCurrency: string;
    exchangeRates: Record<string, number>;
    shipping: { inside_dhaka: number; outside_dhaka: number; free_over: number };
    dhakaDistricts: string[];
    prefill?: { name?: string; email?: string } | null;
    // The signed-in customer's saved addresses, for the prefill selector.
    addresses?: SavedAddress[];
    // The promo code held server-side (if any) and, right after applying, the
    // resolved discount the server computed for the current bag.
    appliedCouponCode?: string | null;
    couponResult?: { code: string; amount: number } | null;
    // A resumed bag (re-priced server-side from the catalogue) and the captured
    // contact, present only when the page is opened via ?resume={token}.
    resumeCart?: { slug: string; size: string | null; quantity: number; name: string; price: number; image?: string | null }[] | null;
    resumeContact?: { email?: string | null; phone?: string | null } | null;
}

export default function Checkout({
    districts,
    countries,
    shippingZones,
    paymentMethods,
    cardGateways,
    baseCurrency,
    exchangeRates,
    prefill,
    addresses,
    appliedCouponCode,
    couponResult,
    resumeCart,
    resumeContact,
}: CheckoutProps) {
    const { resolvedLines, subtotal, clear, add } = useCart();
    const { t, locale } = useTranslation();
    const page = usePage();
    const presentmentCurrency = (page.props.currency as { code?: string } | undefined)?.code ?? baseCurrency;
    const couponError = (page.props.errors as Record<string, string | undefined>)?.coupon;

    const form = useForm({
        customer: {
            name: prefill?.name ?? '',
            phone: resumeContact?.phone ?? '',
            email: resumeContact?.email ?? prefill?.email ?? '',
            address: '',
            district: '',
            country: '',
            postcode: '',
            note: '',
        },
        payment_method: paymentMethods[0]?.value ?? 'cod',
        items: [] as { slug: string; size: string; quantity: number }[],
    });

    const country = form.data.customer.country;
    const isBd = country === 'BD';

    // Prefill the delivery fields from a saved address. Additive: it only writes
    // the address fields, leaving email/note (and the rest of the form) intact.
    const savedAddresses = addresses ?? [];
    const applyAddress = (id: string) => {
        const address = savedAddresses.find((a) => String(a.id) === id);
        if (!address) {
            return;
        }
        form.setData('customer', {
            ...form.data.customer,
            name: address.recipient,
            phone: address.phone,
            address: address.address_line,
            country: address.country,
            district: address.country === 'BD' ? address.state ?? address.city ?? '' : '',
            postcode: address.postcode ?? '',
        });
    };

    // Mirror the server's zone pricing so the customer sees the real delivery
    // charge. Returns null when no country is chosen yet (shown as "—"). Weight
    // rates are skipped here — the parcel weight is only known server-side, so a
    // weight-only zone previews as "calculated at checkout".
    const delivery = useMemo<number | null>(() => {
        if (!country || subtotal === 0) {
            return country ? 0 : null;
        }

        // Mirror the server resolver: a district-scoped zone for this country
        // wins first (e.g. inside Dhaka), then a country-wide zone, then the
        // catch-all. District names arrive lowercased from the server.
        const district = form.data.customer.district.trim().toLowerCase();
        const covers = (z: ShippingZonePreview) => z.countries.includes(country);
        const zone =
            (district ? shippingZones.find((z) => covers(z) && z.districts.includes(district)) : undefined) ??
            shippingZones.find((z) => covers(z) && z.districts.length === 0) ??
            shippingZones.find((z) => z.countries.length === 0);

        if (!zone) {
            return null;
        }

        const rate = zone.rates.find((r) => {
            if (r.method === 'flat') return true;
            if (r.method === 'order_value') {
                return (r.min_threshold === null || subtotal >= r.min_threshold) && (r.max_threshold === null || subtotal <= r.max_threshold);
            }
            return false; // weight — resolved server-side
        });

        if (!rate) {
            return null;
        }

        if (rate.free_over !== null && subtotal >= rate.free_over) {
            return 0;
        }

        return rate.amount;
    }, [country, subtotal, shippingZones, form.data.customer.district]);

    // Promo code. The server is the pricing authority — Apply re-prices the bag
    // and resolves the discount there; this only mirrors what it returned. The
    // discount is known after applying (couponResult); on a fresh reload with a
    // code still held, re-apply once to recover its value.
    const [couponInput, setCouponInput] = useState(appliedCouponCode ?? couponResult?.code ?? '');
    const discount = appliedCouponCode ? (couponResult?.amount ?? 0) : 0;
    const items = () => resolvedLines.map((line) => ({ slug: line.slug, size: line.size, quantity: line.quantity }));

    const applyCoupon = () => {
        if (couponInput.trim() === '') {
            return;
        }
        router.post('/checkout/coupon', { code: couponInput, items: items() }, { preserveScroll: true });
    };

    const removeCoupon = () => {
        router.delete('/checkout/coupon', { preserveScroll: true });
    };

    const reappliedFor = useRef<string | null>(null);
    useEffect(() => {
        // A held code but no resolved amount (e.g. after a page refresh): silently
        // re-resolve it once against the current bag so the summary is accurate.
        if (appliedCouponCode && !couponResult && resolvedLines.length > 0 && reappliedFor.current !== appliedCouponCode) {
            reappliedFor.current = appliedCouponCode;
            router.post('/checkout/coupon', { code: appliedCouponCode, items: items() }, { preserveScroll: true, preserveState: false });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [appliedCouponCode, couponResult, resolvedLines.length]);

    // Resume: hydrate an empty bag from a re-priced abandoned session exactly
    // once. Lines the server could not re-price are absent from resumeCart, so
    // stale/unavailable slugs are silently skipped.
    const hydratedResume = useRef(false);
    useEffect(() => {
        if (hydratedResume.current || !resumeCart || resumeCart.length === 0 || resolvedLines.length > 0) {
            return;
        }
        hydratedResume.current = true;
        resumeCart.forEach((line) => {
            add({ slug: line.slug, name: line.name, price: line.price, image: line.image ?? null }, line.size ?? 'Default', line.quantity);
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resumeCart]);

    // Abandoned-checkout capture: once the shopper has left an email or phone and
    // the bag is non-empty, debounce a no-content ping that upserts the session
    // against the checkout_token cookie. Deliberately bare (not an Inertia visit)
    // so it never disturbs the form, coupon or address state.
    const email = form.data.customer.email;
    const phone = form.data.customer.phone;
    useEffect(() => {
        const hasContact = email.trim() !== '' || phone.trim() !== '';
        if (!hasContact || resolvedLines.length === 0) {
            return;
        }

        const handle = window.setTimeout(() => {
            void fetch('/checkout/session', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: JSON.stringify({
                    email: email.trim() || null,
                    phone: phone.trim() || null,
                    currency: presentmentCurrency,
                    locale,
                    items: resolvedLines.map((line) => ({ slug: line.slug, size: line.size, quantity: line.quantity })),
                }),
            }).catch(() => {
                // Capture is best-effort; a failed ping must never surface to the shopper.
            });
        }, 1500);

        return () => window.clearTimeout(handle);
         
    }, [email, phone, resolvedLines, presentmentCurrency, locale]);

    const total = Math.max(0, subtotal + (delivery ?? 0) - discount);

    // When the selected method settles in another currency, show the customer
    // the converted amount they will actually be charged.
    const selectedMethod = paymentMethods.find((m) => m.value === form.data.payment_method);
    const foreignCharge = useMemo(() => {
        const currency = selectedMethod?.currency;
        if (!currency || currency === baseCurrency) {
            return null;
        }
        const rate = exchangeRates[currency];
        if (!rate) {
            return null;
        }
        return { currency, amount: Math.round((total / rate) * 100) / 100 };
    }, [selectedMethod, baseCurrency, exchangeRates, total]);

    // When the chosen method collects the card on-page, we render its embedded
    // form and hand over the pay action to it instead of the normal submit.
    const embeddedCard = cardGateways[form.data.payment_method];
    const chargeLabel = foreignCharge ? formatMoney(foreignCharge.amount, foreignCharge.currency) : formatMoney(total, baseCurrency);
    const buildCardPayload = (): CardPayload => ({
        customer: form.data.customer,
        payment_method: form.data.payment_method,
        items: resolvedLines.map((line) => ({ slug: line.slug, size: line.size, quantity: line.quantity })),
    });

    // Laravel validates nested fields as "customer.name"; Inertia's error type only
    // knows the top-level keys, so read the dotted keys through a widened view.
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        // Post the current bag alongside the form; the server re-prices every line.
        form.transform((data) => ({
            ...data,
            items: resolvedLines.map((line) => ({ slug: line.slug, size: line.size, quantity: line.quantity })),
        }));

        form.post('/checkout', {
            onSuccess: () => clear(),
        });
    };

    if (resolvedLines.length === 0) {
        return (
            <ShopLayout title="Checkout — Zaartaj Elegance">
                <div className="mx-auto max-w-xl px-5 py-32 text-center">
                    <h1 className="font-display text-zt-ink text-4xl">{t('checkout.empty.heading')}</h1>
                    <p className="text-zt-muted mt-4 text-sm">{t('checkout.empty.body')}</p>
                    <button
                        type="button"
                        onClick={() => router.visit('/shop')}
                        className="bg-zt-teal-deep mt-8 px-8 py-4 text-[0.72rem] tracking-[0.2em] text-white uppercase"
                    >
                        {t('checkout.browse')}
                    </button>
                </div>
            </ShopLayout>
        );
    }

    const field = 'border-zt-sand text-zt-ink focus:border-zt-teal w-full border bg-white px-4 py-3 text-sm focus:outline-none';
    const label = 'text-zt-ink text-[0.72rem] tracking-[0.16em] uppercase';

    return (
        <ShopLayout title="Checkout — Zaartaj Elegance">
            <div className="mx-auto max-w-6xl px-5 py-14 lg:px-8">
                <p className="zt-eyebrow">{t('checkout.eyebrow')}</p>
                <h1 className="font-display text-zt-ink mt-3 text-4xl sm:text-5xl">{t('checkout.heading')}</h1>

                {errors.checkout && (
                    <div className="mt-6 border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">{errors.checkout}</div>
                )}

                <form onSubmit={submit} className="mt-10 grid gap-12 lg:grid-cols-[1.4fr_1fr] lg:gap-16">
                    <div className="space-y-8">
                        <section className="space-y-5">
                            <h2 className="font-display text-zt-ink text-2xl">{t('checkout.delivery_details')}</h2>

                            {savedAddresses.length > 0 && (
                                <div>
                                    <label htmlFor="saved_address" className={label}>
                                        {t('checkout.use_saved_address')}
                                    </label>
                                    <select
                                        id="saved_address"
                                        defaultValue=""
                                        onChange={(e) => applyAddress(e.target.value)}
                                        className={`mt-2 ${field}`}
                                    >
                                        <option value="">{t('checkout.select_saved_address')}</option>
                                        {savedAddresses.map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {(a.label ? `${a.label} — ` : '') + a.recipient + ', ' + a.address_line}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div>
                                <label htmlFor="name" className={label}>
                                    {t('checkout.full_name')}
                                </label>
                                <input
                                    id="name"
                                    value={form.data.customer.name}
                                    onChange={(e) => form.setData('customer', { ...form.data.customer, name: e.target.value })}
                                    className={`mt-2 ${field}`}
                                    autoComplete="name"
                                />
                                {errors['customer.name'] && <p className="mt-1 text-xs text-red-600">{errors['customer.name']}</p>}
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="phone" className={label}>
                                        {t('checkout.mobile')}
                                    </label>
                                    <input
                                        id="phone"
                                        value={form.data.customer.phone}
                                        onChange={(e) => form.setData('customer', { ...form.data.customer, phone: e.target.value })}
                                        placeholder={isBd || !country ? '01712345678' : '+971 50 123 4567'}
                                        className={`mt-2 ${field}`}
                                        autoComplete="tel"
                                    />
                                    {errors['customer.phone'] && <p className="mt-1 text-xs text-red-600">{errors['customer.phone']}</p>}
                                </div>
                                <div>
                                    <label htmlFor="email" className={label}>
                                        {t('checkout.email')} <span className="text-zt-muted normal-case">{t('checkout.optional')}</span>
                                    </label>
                                    <input
                                        id="email"
                                        type="email"
                                        value={form.data.customer.email}
                                        onChange={(e) => form.setData('customer', { ...form.data.customer, email: e.target.value })}
                                        className={`mt-2 ${field}`}
                                        autoComplete="email"
                                    />
                                    {errors['customer.email'] && <p className="mt-1 text-xs text-red-600">{errors['customer.email']}</p>}
                                </div>
                            </div>

                            <div>
                                <label htmlFor="country" className={label}>
                                    {t('checkout.country')}
                                </label>
                                <select
                                    id="country"
                                    value={form.data.customer.country}
                                    onChange={(e) =>
                                        form.setData('customer', {
                                            ...form.data.customer,
                                            country: e.target.value,
                                            // Clear the country-specific fields when switching markets.
                                            district: '',
                                            postcode: '',
                                        })
                                    }
                                    className={`mt-2 ${field}`}
                                    autoComplete="country"
                                >
                                    <option value="">{t('checkout.select_country')}</option>
                                    {countries.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                {errors['customer.country'] && <p className="mt-1 text-xs text-red-600">{errors['customer.country']}</p>}
                            </div>

                            {isBd ? (
                                <div>
                                    <label htmlFor="district" className={label}>
                                        {t('checkout.district')}
                                    </label>
                                    <select
                                        id="district"
                                        value={form.data.customer.district}
                                        onChange={(e) => form.setData('customer', { ...form.data.customer, district: e.target.value })}
                                        className={`mt-2 ${field}`}
                                    >
                                        <option value="">{t('checkout.select_district')}</option>
                                        {districts.map((d) => (
                                            <option key={d} value={d}>
                                                {d}
                                            </option>
                                        ))}
                                    </select>
                                    {errors['customer.district'] && <p className="mt-1 text-xs text-red-600">{errors['customer.district']}</p>}
                                </div>
                            ) : (
                                country && (
                                    <div>
                                        <label htmlFor="postcode" className={label}>
                                            {t('checkout.postcode')}
                                        </label>
                                        <input
                                            id="postcode"
                                            value={form.data.customer.postcode}
                                            onChange={(e) => form.setData('customer', { ...form.data.customer, postcode: e.target.value })}
                                            className={`mt-2 ${field}`}
                                            autoComplete="postal-code"
                                        />
                                        {errors['customer.postcode'] && <p className="mt-1 text-xs text-red-600">{errors['customer.postcode']}</p>}
                                    </div>
                                )
                            )}

                            <div>
                                <label htmlFor="address" className={label}>
                                    {t('checkout.address')}
                                </label>
                                <textarea
                                    id="address"
                                    rows={3}
                                    value={form.data.customer.address}
                                    onChange={(e) => form.setData('customer', { ...form.data.customer, address: e.target.value })}
                                    placeholder={t('checkout.address_placeholder')}
                                    className={`mt-2 ${field}`}
                                    autoComplete="street-address"
                                />
                                {errors['customer.address'] && <p className="mt-1 text-xs text-red-600">{errors['customer.address']}</p>}
                            </div>

                            <div>
                                <label htmlFor="note" className={label}>
                                    {t('checkout.note')} <span className="text-zt-muted normal-case">{t('checkout.optional')}</span>
                                </label>
                                <textarea
                                    id="note"
                                    rows={2}
                                    value={form.data.customer.note}
                                    onChange={(e) => form.setData('customer', { ...form.data.customer, note: e.target.value })}
                                    className={`mt-2 ${field}`}
                                />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="font-display text-zt-ink text-2xl">{t('checkout.payment')}</h2>
                            <p className="text-zt-muted -mt-2 flex items-center gap-1.5 text-xs">
                                <Lock className="size-3" /> All transactions are secure and encrypted.
                            </p>

                            {/* Radio cards, one bordered group per method, with brand badges and
                                a note panel that opens under the selected method. */}
                            <div className="border-zt-sand divide-zt-sand divide-y overflow-hidden rounded-sm border">
                                {paymentMethods.map((method) => {
                                    const selected = form.data.payment_method === method.value;
                                    const label = t(`payment.${method.value}`) !== `payment.${method.value}` ? t(`payment.${method.value}`) : method.label;
                                    const note = PAYMENT_NOTES[method.value];
                                    const card = cardGateways[method.value];

                                    return (
                                        <div key={method.value} className={selected ? 'ring-zt-teal relative z-10 ring-1' : ''}>
                                            <label className={`flex cursor-pointer items-center justify-between gap-3 px-4 py-4 transition-colors ${selected ? 'bg-zt-teal-mist/50' : 'hover:bg-zt-sand/30'}`}>
                                                <span className="flex items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        name="payment_method"
                                                        value={method.value}
                                                        checked={selected}
                                                        onChange={(e) => form.setData('payment_method', e.target.value)}
                                                        className="accent-zt-teal-deep size-4"
                                                    />
                                                    <span className="text-zt-ink text-sm font-medium">{label}</span>
                                                </span>
                                                <PaymentLogo value={method.value} />
                                            </label>

                                            {/* The selected method reveals its details right below its own row. */}
                                            {selected && card && (
                                                <div className="border-zt-sand bg-zt-sand/20 border-t px-4 py-5">
                                                    <EmbeddedCardPayment
                                                        provider={card.provider}
                                                        publishableKey={card.publishable_key}
                                                        amountLabel={chargeLabel}
                                                        buildPayload={buildCardPayload}
                                                        onPaid={clear}
                                                    />
                                                </div>
                                            )}
                                            {selected && !card && note && (
                                                <p className="bg-zt-sand/40 text-zt-muted border-zt-sand border-t px-4 py-3 text-center text-xs">{note}</p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                            {errors.payment_method && <p className="text-xs text-red-600">{errors.payment_method}</p>}
                        </section>
                    </div>

                    <aside className="bg-zt-sand/40 h-fit p-8">
                        <h2 className="font-display text-zt-ink text-2xl">{t('checkout.your_order')}</h2>
                        <div className="zt-rule my-6" />

                        <ul className="space-y-4">
                            {resolvedLines.map((line) => (
                                <li key={`${line.slug}-${line.size}`} className="flex gap-4">
                                    <div className="bg-zt-sand aspect-[3/4] w-14 shrink-0 overflow-hidden">
                                        <ProductFigure product={line} />
                                    </div>
                                    <div className="flex flex-1 justify-between gap-3 text-sm">
                                        <div>
                                            <p className="text-zt-ink line-clamp-2">{line.name}</p>
                                            <p className="text-zt-muted mt-0.5 text-xs">
                                                {line.size && line.size !== 'Default' ? `${line.size} · ` : ''}
                                                {t('checkout.qty', { count: line.quantity })}
                                            </p>
                                        </div>
                                        <p className="text-zt-ink shrink-0">{formatTaka(line.lineTotal)}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div className="zt-rule my-6" />

                        <div className="space-y-2">
                            <label htmlFor="coupon" className={label}>
                                {t('checkout.coupon_label')}
                            </label>
                            {appliedCouponCode ? (
                                <div className="flex items-center justify-between gap-3 border border-zt-teal bg-zt-teal-mist/40 px-4 py-3 text-sm">
                                    <span className="text-zt-ink">
                                        {t('checkout.coupon_applied')} <span className="font-medium">{appliedCouponCode}</span>
                                    </span>
                                    <button type="button" onClick={removeCoupon} className="text-zt-teal-deep text-xs underline">
                                        {t('checkout.remove')}
                                    </button>
                                </div>
                            ) : (
                                <div className="flex gap-2">
                                    <input
                                        id="coupon"
                                        value={couponInput}
                                        onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                applyCoupon();
                                            }
                                        }}
                                        className={`${field} flex-1`}
                                    />
                                    <button
                                        type="button"
                                        onClick={applyCoupon}
                                        className="bg-zt-teal-deep shrink-0 px-5 text-[0.72rem] tracking-[0.16em] text-white uppercase"
                                    >
                                        {t('checkout.apply')}
                                    </button>
                                </div>
                            )}
                            {couponError && <p className="text-xs text-red-600">{couponError}</p>}
                        </div>

                        <div className="zt-rule my-6" />

                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-zt-muted">{t('checkout.subtotal')}</dt>
                                <dd className="text-zt-ink">{formatTaka(subtotal)}</dd>
                            </div>
                            {discount > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-zt-muted">{t('checkout.discount')}</dt>
                                    <dd className="text-zt-teal-deep">−{formatTaka(discount)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-zt-muted">{t('checkout.delivery')}</dt>
                                <dd className="text-zt-ink">
                                    {delivery === null ? '—' : delivery === 0 ? t('checkout.complimentary') : formatTaka(delivery)}
                                </dd>
                            </div>
                            <div className="border-zt-sand flex justify-between border-t pt-3 text-base">
                                <dt className="text-zt-ink">{t('checkout.total')}</dt>
                                <dd className="text-zt-ink font-medium">{formatTaka(total)}</dd>
                            </div>
                            {foreignCharge && (
                                <div className="flex justify-between text-xs">
                                    <dt className="text-zt-muted">{t('checkout.charged')}</dt>
                                    <dd className="text-zt-ink">{formatMoney(foreignCharge.amount, foreignCharge.currency)}</dd>
                                </div>
                            )}
                        </dl>

                        {embeddedCard ? (
                            // The embedded card form owns its own pay button (in the
                            // payment section), so the summary just points to it.
                            <p className="text-zt-muted mt-8 text-center text-[0.72rem] leading-relaxed">
                                Enter your card details under Payment to pay {chargeLabel}.
                            </p>
                        ) : (
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="bg-zt-teal-deep hover:bg-zt-teal mt-8 flex w-full items-center justify-center gap-2 px-8 py-4 text-[0.72rem] font-medium tracking-[0.2em] text-white uppercase transition-colors disabled:opacity-50"
                            >
                                <Lock className="size-3.5" />
                                {form.processing ? t('checkout.placing') : t('checkout.place_order')}
                            </button>
                        )}

                        <p className="text-zt-muted mt-4 text-center text-[0.7rem] leading-relaxed">{t('checkout.terms')}</p>
                    </aside>
                </form>
            </div>
        </ShopLayout>
    );
}
