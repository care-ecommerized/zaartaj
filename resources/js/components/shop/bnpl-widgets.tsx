import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { PaymentLogo } from '@/components/shop/payment-logos';
import type { BnplConfig, SharedData } from '@/types';
import { formatMoney } from '@/lib/shop/catalog';

declare global {
    interface Window {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        TabbyPromo?: new (options: Record<string, unknown>) => any;
        tamaraWidgetConfig?: Record<string, unknown>;
        TamaraWidgetV2?: { refresh?: () => void };
    }
}

/** Inject a third-party script once, keyed by id; resolves when it loads. */
function loadScript(src: string, id: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.getElementById(id)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.id = id;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(script);
    });
}

/**
 * Tabby and Tamara "pay in instalments" promo widgets for a product's price.
 *
 * When the provider's publishable key is configured, the provider's own SDK
 * renders the live widget (localised, with a "Learn more" popup). Until then a
 * static, accurate fallback shows the instalment breakdown so the option is
 * still visible. Amounts are in the BNPL currency (the base currency, AED), so
 * the product price is passed through unconverted.
 */
export default function BnplWidgets({ amount }: { amount: number }) {
    const bnpl = usePage<SharedData>().props.bnpl as BnplConfig | undefined;
    const tabbyMounted = useRef(false);

    const currency = bnpl?.currency ?? 'AED';
    const perFour = amount / 4;

    // Tabby promo — the SDK renders into #tabby-promo when a key is set.
    useEffect(() => {
        if (!bnpl?.tabby.publicKey || tabbyMounted.current) {
            return;
        }
        loadScript('https://checkout.tabby.ai/tabby-promo.js', 'tabby-promo-sdk')
            .then(() => {
                if (!window.TabbyPromo) {
                    return;
                }
                tabbyMounted.current = true;
                new window.TabbyPromo({
                    selector: '#tabby-promo',
                    currency,
                    price: amount.toFixed(2),
                    installmentsCount: 4,
                    lang: bnpl.tamara.lang || 'en',
                    source: 'product',
                    publicKey: bnpl.tabby.publicKey,
                    merchantCode: bnpl.tabby.merchantCode,
                });
            })
            .catch(() => {
                // Best-effort: a blocked/failed SDK simply leaves the fallback in place.
            });
    }, [amount, currency, bnpl]);

    // Tamara widget — the custom element is upgraded by the SDK when a key is set.
    useEffect(() => {
        if (!bnpl?.tamara.publicKey) {
            return;
        }
        window.tamaraWidgetConfig = {
            lang: bnpl.tamara.lang || 'en',
            country: bnpl.tamara.country || 'AE',
            publicKey: bnpl.tamara.publicKey,
            css: {},
        };
        loadScript('https://cdn.tamara.co/widget-v2/tamara-widget.js', 'tamara-widget-sdk')
            .then(() => window.TamaraWidgetV2?.refresh?.())
            .catch(() => {
                // Best-effort: leave the fallback in place if the SDK cannot load.
            });
    }, [amount, bnpl]);

    if (!bnpl) {
        return null;
    }

    const money = formatMoney(perFour, currency);
    const cardClass = 'border-zt-sand flex items-center justify-between gap-3 border px-4 py-3 text-sm';

    return (
        <div className="mt-6 space-y-2">
            {/* Tabby */}
            <div className={cardClass}>
                <div id="tabby-promo" className="flex-1">
                    {!bnpl.tabby.publicKey && (
                        <span className="text-zt-ink">
                            4 interest-free payments of <span className="font-medium">{money}</span>. No fees.
                        </span>
                    )}
                </div>
                <PaymentLogo value="tabby" />
            </div>

            {/* Tamara */}
            <div className={cardClass}>
                <div className="flex-1">
                    {bnpl.tamara.publicKey ? (
                        // The Tamara SDK upgrades this custom element in place; React
                        // does not type custom elements, so inject its markup.
                        <div
                            dangerouslySetInnerHTML={{
                                __html: `<tamara-widget type="tamara-summary" inline-type="2" amount="${amount.toFixed(2)}"></tamara-widget>`,
                            }}
                        />
                    ) : (
                        <span className="text-zt-ink">
                            Or split in 4 payments of <span className="font-medium">{money}</span> — no late fees.
                        </span>
                    )}
                </div>
                <PaymentLogo value="tamara" />
            </div>
        </div>
    );
}
