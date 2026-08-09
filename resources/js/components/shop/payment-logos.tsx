import { Banknote } from 'lucide-react';

/**
 * Payment-method logos, drawn as inline SVG so they need no external assets and
 * stay crisp at any size. These are brand-recognisable marks in each provider's
 * colours; swap in official artwork by dropping files under public/images/
 * payments/ and pointing the relevant case at an <img>.
 */

const badge = 'inline-flex h-6 items-center rounded px-1.5 text-[0.6rem] font-bold leading-none';

function Visa() {
    return <span className={`${badge} bg-[#1434cb] italic text-white`}>VISA</span>;
}

function Amex() {
    return <span className={`${badge} bg-[#2e77bc] tracking-tight text-white`}>AMEX</span>;
}

function Mastercard() {
    return (
        <svg viewBox="0 0 36 24" className="h-6 w-9 rounded bg-white p-0.5" role="img" aria-label="Mastercard">
            <circle cx="14" cy="12" r="7" fill="#eb001b" />
            <circle cx="22" cy="12" r="7" fill="#f79e1b" />
            <path d="M18 6.5a7 7 0 0 0 0 11 7 7 0 0 0 0-11Z" fill="#ff5f00" />
        </svg>
    );
}

function WordmarkBadge({ text, className }: { text: string; className: string }) {
    return <span className={`${badge} ${className}`}>{text}</span>;
}

/** The set of logos shown for a payment method. */
export function PaymentLogo({ value }: { value: string }) {
    switch (value) {
        case 'tap':
            return (
                <span className="flex items-center gap-1.5">
                    <Visa />
                    <Mastercard />
                    <Amex />
                </span>
            );
        case 'bkash':
            return <WordmarkBadge text="bKash" className="bg-[#e2136e] text-white" />;
        case 'nagad':
            return <WordmarkBadge text="Nagad" className="bg-[#ec1c24] text-white" />;
        case 'tabby':
            return <WordmarkBadge text="tabby" className="bg-[#3fe3b3] text-black" />;
        case 'tamara':
            return <WordmarkBadge text="tamara" className="bg-black text-white" />;
        case 'cod':
            return (
                <span className="text-zt-muted inline-flex items-center gap-1.5 text-[0.65rem] tracking-[0.12em] uppercase">
                    <Banknote className="size-4" /> Cash
                </span>
            );
        default:
            return null;
    }
}
