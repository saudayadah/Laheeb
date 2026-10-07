import { ConfirmDelete } from '@/components/confirm-delete';
import { EmptyState } from '@/components/empty-state';
import { NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { ActiveBadge, PaymentTermBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtPrice } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type CustomerListItem, type IdName, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Store } from 'lucide-react';

interface CustomersPageProps {
    customers: Paginated<CustomerListItem>;
    filters: {
        search: string;
        route_id: string | null;
        group_id: string | null;
        type: string | null;
        payment_term: string | null;
        status: string | null;
    };
    routes: IdName[];
    groups: IdName[];
    canViewPrices: boolean;
    canManage: boolean;
}

export default function CustomersIndex({ customers, filters, routes, groups, canViewPrices, canManage }: CustomersPageProps) {
    const { t } = useTrans();

    const applyFilters = (updates: Partial<CustomersPageProps['filters']>) => {
        const next = { ...filters, ...updates };
        router.get(route('customers.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== '')), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('customers.title'), href: '/customers' }]}>
            <Head title={t('customers.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('customers.title')}
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link href={route('customers.create')}>
                                    <Plus className="size-4" />
                                    {t('customers.create')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-2">
                    <SearchInput value={filters.search ?? ''} onChange={(v) => applyFilters({ search: v })} />
                    <NativeSelect
                        className="w-40"
                        value={filters.route_id ?? ''}
                        onChange={(e) => applyFilters({ route_id: e.target.value || null })}
                        aria-label={t('customers.route')}
                    >
                        <option value="">
                            {t('customers.route')}: {t('common.all')}
                        </option>
                        {routes.map((r) => (
                            <option key={r.id} value={r.id}>
                                {r.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-40"
                        value={filters.group_id ?? ''}
                        onChange={(e) => applyFilters({ group_id: e.target.value || null })}
                        aria-label={t('customers.group')}
                    >
                        <option value="">
                            {t('customers.group')}: {t('common.all')}
                        </option>
                        {groups.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect
                        className="w-36"
                        value={filters.payment_term ?? ''}
                        onChange={(e) => applyFilters({ payment_term: e.target.value || null })}
                        aria-label={t('customers.payment_term')}
                    >
                        <option value="">
                            {t('customers.payment_term')}: {t('common.all')}
                        </option>
                        <option value="cash">{t('customers.term.cash')}</option>
                        <option value="credit">{t('customers.term.credit')}</option>
                    </NativeSelect>
                    <NativeSelect
                        className="w-32"
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilters({ status: e.target.value || null })}
                        aria-label={t('common.status')}
                    >
                        <option value="">
                            {t('common.status')}: {t('common.all')}
                        </option>
                        <option value="active">{t('common.active')}</option>
                        <option value="inactive">{t('common.inactive')}</option>
                    </NativeSelect>
                </div>

                {customers.data.length === 0 ? (
                    <EmptyState icon={Store} message={t('customers.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('customers.code')}</TableHead>
                                    <TableHead>{t('customers.name')}</TableHead>
                                    <TableHead>{t('customers.group')}</TableHead>
                                    <TableHead>{t('customers.route')}</TableHead>
                                    <TableHead>{t('customers.payment_term')}</TableHead>
                                    {canViewPrices && <TableHead className="text-end">{t('customers.default_price')}</TableHead>}
                                    <TableHead>{t('customers.phone')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    {canManage && <TableHead className="w-20 text-end">{t('common.actions')}</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.data.map((customer) => (
                                    <TableRow key={customer.id}>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {customer.code}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {canManage ? (
                                                <Link href={route('customers.edit', customer.id)} className="hover:underline">
                                                    {customer.name}
                                                </Link>
                                            ) : (
                                                customer.name
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{customer.group?.name ?? '—'}</TableCell>
                                        <TableCell className="text-muted-foreground">{customer.route?.name ?? '—'}</TableCell>
                                        <TableCell>
                                            <PaymentTermBadge term={customer.payment_term} />
                                        </TableCell>
                                        {canViewPrices && <TableCell className="text-end tabular-nums">{fmtPrice(customer.default_price)}</TableCell>}
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {customer.phone ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <ActiveBadge active={customer.active} />
                                        </TableCell>
                                        {canManage && (
                                            <TableCell className="text-end">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" className="size-8" asChild>
                                                        <Link href={route('customers.edit', customer.id)}>
                                                            <Pencil className="size-4" />
                                                            <span className="sr-only">{t('common.edit')}</span>
                                                        </Link>
                                                    </Button>
                                                    <ConfirmDelete url={route('customers.destroy', customer.id)} />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={customers} />
            </div>
        </AppLayout>
    );
}
