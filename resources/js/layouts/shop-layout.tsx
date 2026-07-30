import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/shop/site-footer';
import { SiteHeader } from '@/components/shop/site-header';

interface ShopLayoutProps {
    children: ReactNode;
    title: string;
    description?: string;
}

/** CartProvider wraps the whole Inertia app in app.tsx, so it is not repeated here. */
export default function ShopLayout({ children, title, description }: ShopLayoutProps) {
    return (
        <>
            <Head title={title}>{description && <meta name="description" content={description} />}</Head>

            {/* The storefront opts out of the dashboard's light/dark toggle — see app.css. */}
            <div className="bg-zt-cream text-zt-ink flex min-h-screen flex-col">
                <SiteHeader />
                <main className="flex-1">{children}</main>
                <SiteFooter />
            </div>
        </>
    );
}
