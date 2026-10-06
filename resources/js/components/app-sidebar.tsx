import { NavMain, type NavSection } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useCan, useTrans } from '@/lib/i18n';
import { Link } from '@inertiajs/react';
import {
    BadgeDollarSign,
    BarChart3,
    CalendarDays,
    CalendarOff,
    Car,
    Contact,
    FileText,
    FileUp,
    FolderTree,
    HandCoins,
    LayoutGrid,
    Lock,
    MapPinned,
    Package,
    PiggyBank,
    ReceiptText,
    Scale,
    Settings2,
    Store,
    Truck,
    UsersRound,
    Wallet,
    Warehouse,
    Wheat,
} from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { t, locale } = useTrans();
    const can = useCan();

    const sections: NavSection[] = [
        {
            items: [{ title: t('nav.dashboard'), url: '/dashboard', icon: LayoutGrid }],
        },
        {
            label: t('nav.group.daily'),
            items: [
                ...(can('orders.manage') ? [{ title: t('nav.orders'), url: '/orders', icon: CalendarDays }] : []),
                ...(can('deliveries.own') ? [{ title: t('nav.delivery'), url: '/delivery', icon: Truck }] : []),
                ...(can('invoices.manage') ? [{ title: t('nav.pos'), url: '/pos', icon: ReceiptText }] : []),
                ...(can('invoices.manage') ? [{ title: t('nav.invoices'), url: '/invoices', icon: FileText }] : []),
                ...(can('closes.approve') || can('deliveries.own') ? [{ title: t('nav.closes'), url: '/closes', icon: Lock }] : []),
            ],
        },
        {
            label: t('nav.group.money'),
            items: [
                ...(can('receipts.manage') ? [{ title: t('nav.receipts'), url: '/receipts', icon: HandCoins }] : []),
                ...(can('balances.view') ? [{ title: t('nav.receivables'), url: '/receivables', icon: Scale }] : []),
                ...(can('expenses.manage') ? [{ title: t('nav.expenses'), url: '/expenses', icon: Wallet }] : []),
                ...(can('suppliers.manage') ? [{ title: t('nav.suppliers'), url: '/suppliers', icon: Warehouse }] : []),
                ...(can('reports.view') ? [{ title: t('nav.reports'), url: '/reports', icon: BarChart3 }] : []),
            ],
        },
        {
            label: t('nav.group.staff'),
            items: [
                ...(can('employees.manage') ? [{ title: t('nav.employees'), url: '/employees', icon: Contact }] : []),
                ...(can('advances.manage') ? [{ title: t('nav.advances'), url: '/advances', icon: PiggyBank }] : []),
                ...(can('payroll.manage') ? [{ title: t('nav.payroll'), url: '/payroll', icon: BadgeDollarSign }] : []),
                ...(can('employees.manage') ? [{ title: t('nav.leaves'), url: '/leaves', icon: CalendarOff }] : []),
            ],
        },
        {
            label: t('nav.group.factory'),
            items: [
                ...(can('expenses.manage') ? [{ title: t('nav.materials'), url: '/materials', icon: Package }] : []),
                ...(can('expenses.manage') ? [{ title: t('nav.vehicles'), url: '/vehicles', icon: Car }] : []),
            ],
        },
        {
            label: t('nav.group.setup'),
            items: [
                ...(can('customers.view') ? [{ title: t('nav.customers'), url: '/customers', icon: Store }] : []),
                ...(can('customers.view') ? [{ title: t('nav.groups'), url: '/groups', icon: FolderTree }] : []),
                ...(can('customers.view') ? [{ title: t('nav.routes'), url: '/delivery-routes', icon: MapPinned }] : []),
                ...(can('products.view') ? [{ title: t('nav.products'), url: '/products', icon: Wheat }] : []),
                ...(can('imports.run') ? [{ title: t('nav.imports'), url: '/imports', icon: FileUp }] : []),
                ...(can('users.manage') ? [{ title: t('nav.users'), url: '/users', icon: UsersRound }] : []),
                ...(can('settings.manage') ? [{ title: t('nav.settings'), url: '/settings/bakery', icon: Settings2 }] : []),
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" side={locale === 'ar' ? 'right' : 'left'}>
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
                <NavMain sections={sections} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
