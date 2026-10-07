import { EmptyState } from '@/components/empty-state';
import { NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';

interface InvoiceRow {
    id: number;
    number: string;
    date: string;
    customer: { id: number; name: string; code: string } | null;
    source: string;
    payment_method: string;
    status: string;
    total: string;
    driver: string | null;
}

interface InvoicesPageProps {
    invoices: Paginated<InvoiceRow>;
    filters: { from: string; to: string; search: string; status: string | null; payment_method: string | null; source: string | null };
    summary: { total: string; cash: string; credit: string; mada: string; count: number };
}

const statusColors: Record<string, string> = {
    posted: 'text-emerald-700 dark:text-emerald-400',
    draft: 'text-muted-foreground',
    void: 'text-red-600 dark:text-red-400 line-through',
};

export default function InvoicesIndex({ invoices, filters, summary }: InvoicesPageProps) {
    const { t } = useTrans();

    const apply = (updates: Partial<InvoicesPageProps['filters']>) => {
        const next = { ...filters, ...updates };
        router.get(route('invoices.index'), Object.fromEntries(Object.entries(next).filter(([, v]) => v !== null && v !== '')), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const summaryCards = [
        { label: t('invoices.sum_total'), value: summary.total, highlight: true },
        { label: t('invoices.method.cash'), value: summary.cash },
        { label: t('invoices.method.credit'), value: summary.credit, warn: parseFloat(summary.credit) > 0 },
        { label: t('invoices.method.mada'), value: summary.mada },
    ];

    return (
        <AppLayout breadcrumbs={[{ title: t('invoices.title'), href: '/invoices' }]}>
            <Head title={t('invoices.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('invoices.title')} />

                {/* The owner's numbers for the selected period */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {summaryCards.map((card) => (
                        <div
                            key={card.label}
                            className={`rounded-xl border p-4 ${card.highlight ? 'border-primary/30 bg-primary/10 dark:bg-primary/15' : ''}`}
                        >
                            <div className={`text-2xl font-bold tabular-nums ${card.warn ? 'text-amber-700 dark:text-amber-400' : ''}`}>
                                {fmtAmount(card.value)}
                            </div>
                            <div className="text-muted-foreground mt-0.5 text-xs">
                                {card.label}
                                {card.highlight && (
                                    <span className="ms-2">
                                        ({fmtInt(summary.count)} {t('invoices.count')})
                                    </span>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        aria-label={t('common.from')}
                        value={filters.from}
                        onChange={(e) => apply({ from: e.target.value })}
                    />
                    <span className="text-muted-foreground text-sm">—</span>
                    <Input
                        type="date"
                        dir="ltr"
                        className="w-38"
                        aria-label={t('common.to')}
                        value={filters.to}
                        onChange={(e) => apply({ to: e.target.value })}
                    />
                    <SearchInput value={filters.search ?? ''} onChange={(v) => apply({ search: v })} />
                    <NativeSelect
                        className="w-32"
                        value={filters.payment_method ?? ''}
                        onChange={(e) => apply({ payment_method: e.target.value || null })}
                    >
                        <option value="">
                            {t('invoices.method')}: {t('common.all')}
                        </option>
                        <option value="cash">{t('invoices.method.cash')}</option>
                        <option value="credit">{t('invoices.method.credit')}</option>
                        <option value="mada">{t('invoices.method.mada')}</option>
                    </NativeSelect>
                    <NativeSelect className="w-32" value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value || null })}>
                        <option value="">
                            {t('common.status')}: {t('common.all')}
                        </option>
                        <option value="posted">{t('invoices.status.posted')}</option>
                        <option value="draft">{t('invoices.status.draft')}</option>
                        <option value="void">{t('invoices.status.void')}</option>
                    </NativeSelect>
                    <NativeSelect className="w-36" value={filters.source ?? ''} onChange={(e) => apply({ source: e.target.value || null })}>
                        <option value="">
                            {t('invoices.source')}: {t('common.all')}
                        </option>
                        <option value="delivery">{t('invoices.source.delivery')}</option>
                        <option value="van">{t('invoices.source.van')}</option>
                        <option value="counter">{t('invoices.source.counter')}</option>
                        <option value="daily_retail">{t('invoices.source.daily_retail')}</option>
                    </NativeSelect>
                </div>

                {invoices.data.length === 0 ? (
                    <EmptyState icon={FileText} message={t('common.no_results')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('invoices.number')}</TableHead>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('customers.name')}</TableHead>
                                    <TableHead>{t('invoices.source')}</TableHead>
                                    <TableHead>{t('invoices.method')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-end">{t('invoices.total')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.data.map((invoice) => (
                                    <TableRow key={invoice.id}>
                                        <TableCell>
                                            <Link
                                                href={route('invoices.show', invoice.id)}
                                                className="font-medium tabular-nums hover:underline"
                                                dir="ltr"
                                            >
                                                {invoice.number}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {invoice.date}
                                        </TableCell>
                                        <TableCell>{invoice.customer?.name ?? t('invoices.walk_in')}</TableCell>
                                        <TableCell className="text-muted-foreground">{t(`invoices.source.${invoice.source}`)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`invoices.method.${invoice.payment_method}`)}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className={`text-sm ${statusColors[invoice.status] ?? ''}`}>
                                                {t(`invoices.status.${invoice.status}`)}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-end font-medium tabular-nums">{fmtAmount(invoice.total)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={invoices} />
            </div>
        </AppLayout>
    );
}
