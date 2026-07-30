import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Coins, Folder, LayoutGrid, Package, ShoppingCart, Ticket, Truck, UploadCloud } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
];

/** Only rendered for staff; the routes themselves 404 for everyone else. */
const adminNavItems: NavItem[] = [
    {
        title: 'Products',
        url: '/admin/products',
        icon: Package,
    },
    {
        title: 'Imports',
        url: '/admin/products/imports',
        icon: UploadCloud,
    },
    {
        title: 'Currencies',
        url: '/admin/currencies',
        icon: Coins,
    },
    {
        title: 'Shipping',
        url: '/admin/shipping',
        icon: Truck,
    },
    {
        title: 'Coupons',
        url: '/admin/coupons',
        icon: Ticket,
    },
    {
        title: 'Abandoned',
        url: '/admin/abandoned',
        icon: ShoppingCart,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={auth.isAdmin ? [...mainNavItems, ...adminNavItems] : mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
