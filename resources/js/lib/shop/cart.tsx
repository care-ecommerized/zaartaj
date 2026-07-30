import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

const STORAGE_KEY = 'zaartaj.cart';

/**
 * A cart line carries its own snapshot of the product.
 *
 * The catalogue lives in the database now, so the browser has no registry to look
 * a slug up in. Storing name and price at the moment of adding also means a price
 * change mid-session cannot silently reprice what someone already put in the bag —
 * the server re-checks both at checkout.
 */
export interface CartLine {
    slug: string;
    size: string;
    quantity: number;
    name: string;
    price: number;
    image?: string | null;
}

/** What the cart page renders, with the line total worked out. */
export interface ResolvedCartLine extends CartLine {
    lineTotal: number;
}

/** The fields a page must supply when adding to the bag. */
export interface CartItemInput {
    slug: string;
    name: string;
    price: number;
    image?: string | null;
}

interface CartContextValue {
    lines: CartLine[];
    resolvedLines: ResolvedCartLine[];
    itemCount: number;
    subtotal: number;
    add: (item: CartItemInput, size: string, quantity?: number) => void;
    setQuantity: (slug: string, size: string, quantity: number) => void;
    remove: (slug: string, size: string) => void;
    clear: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);

function isCartLine(value: unknown): value is CartLine {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const line = value as CartLine;

    return (
        typeof line.slug === 'string' &&
        typeof line.size === 'string' &&
        typeof line.name === 'string' &&
        Number.isFinite(line.price) &&
        Number.isFinite(line.quantity) &&
        line.quantity > 0
    );
}

function readStoredCart(): CartLine[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return [];
        }

        // Drops malformed entries, and carts saved before lines carried a snapshot.
        return parsed.filter(isCartLine);
    } catch {
        return [];
    }
}

export function CartProvider({ children }: { children: ReactNode }) {
    const [lines, setLines] = useState<CartLine[]>(readStoredCart);

    useEffect(() => {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
        } catch {
            // Private browsing or a full quota — the cart just will not persist.
        }
    }, [lines]);

    const add = useCallback((item: CartItemInput, size: string, quantity = 1) => {
        setLines((current) => {
            const existing = current.find((line) => line.slug === item.slug && line.size === size);

            if (existing) {
                return current.map((line) => (line === existing ? { ...line, quantity: line.quantity + quantity } : line));
            }

            return [...current, { ...item, size, quantity }];
        });
    }, []);

    const setQuantity = useCallback((slug: string, size: string, quantity: number) => {
        setLines((current) =>
            quantity <= 0
                ? current.filter((line) => !(line.slug === slug && line.size === size))
                : current.map((line) => (line.slug === slug && line.size === size ? { ...line, quantity } : line)),
        );
    }, []);

    const remove = useCallback((slug: string, size: string) => {
        setLines((current) => current.filter((line) => !(line.slug === slug && line.size === size)));
    }, []);

    const clear = useCallback(() => setLines([]), []);

    const value = useMemo<CartContextValue>(() => {
        const resolvedLines = lines.map<ResolvedCartLine>((line) => ({ ...line, lineTotal: line.price * line.quantity }));

        return {
            lines,
            resolvedLines,
            itemCount: resolvedLines.reduce((total, line) => total + line.quantity, 0),
            subtotal: resolvedLines.reduce((total, line) => total + line.lineTotal, 0),
            add,
            setQuantity,
            remove,
            clear,
        };
    }, [lines, add, setQuantity, remove, clear]);

    return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
    const context = useContext(CartContext);

    if (!context) {
        throw new Error('useCart must be used inside a <CartProvider>, which ShopLayout provides.');
    }

    return context;
}
