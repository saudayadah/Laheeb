import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { FileText, HandCoins, PartyPopper } from 'lucide-react';
import { useMemo, useState } from 'react';

interface AgingRow {
    customer: {
        id: number;
        code: string;
        name: string;
        group: { id: number; name: string } | null;
        phone: string | null;
        whatsapp: string | null;
    };
    balance: string;
    buckets: { b30: string; b60: string; b90: string; b90p: string };
    last_payment: string | null;
    last_invoice: string | null;
    overdue: boolean;
}

interface ReceivablesProps {
    rows: AgingRow[];
    totals: { balance: string; b30: string; b60: string; b90: string; b90p: string };
    canReceipt: boolean;
}

export default function ReceivablesIndex({ rows, totals, canReceipt }: ReceivablesProps) {
    const { t } = useTrans();
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim();
        if (!term) return rows;
        return rows.filter(
            (row) => row.customer.name.includes(term) || row.customer.code.includes(term) || (row.customer.group?.name ?? '').includes(term),
        );
    }, [rows, search]);

    const overdueTotal = useMemo(() => parseFloat(totals.b60) + parseFloat(totals.b90) + parseFloat(totals.b90p), [totals]);

    return (
        <AppLayout breadcrumbs={[{ title: t('receivables.title'), href: '/receivables' }]}>
            <Head title={t('receivables.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader title={t('receivables.title')} />

                {/* The three numbers the owner opens this page for */}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div className="border-primary/30 bg-primary/10 dark:bg-primary/15 rounded-xl border p-4">
                        <div className="text-3xl font-bold tabular-nums">{fmtAmount(totals.balance)}</div>
                        <div className="text-muted-foreground mt-1 text-sm">
                            {t('receivables.total')} ({t('common.currency')})
                        </div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className="text-3xl font-bold tabular-nums">{fmtInt(rows.length)}</div>
                        <div className="text-muted-foreground mt-1 text-sm">{t('receivables.debtors')}</div>
                    </div>
                    <div className="rounded-xl border p-4">
                        <div className={`text-3xl font-bold tabular-nums ${overdueTotal > 0 ? 'text-red-600 dark:text-red-400' : ''}`}>
                            {fmtAmount(overdueTotal)}
                        </div>
                        <div className="text-muted-foreground mt-1 text-sm">
                            {t('receivables.overdue_total')} ({t('common.currency')})
                        </div>
                    </div>
                </div>

                <SearchInput value={search} onChange={setSearch} />

                {filtered.length === 0 ? (
                    <EmptyState icon={PartyPopper} message={rows.length === 0 ? t('receivables.empty') : t('common.no_results')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('customers.name')}</TableHead>
                                    <TableHead className="text-end">{t('delivery.balance')}</TableHead>
                                    <TableHead className="text-end">{t('receivables.b30')}</TableHead>
                                    <TableHead className="text-end">{t('receivables.b60')}</TableHead>
                                    <TableHead className="text-end">{t('receivables.b90')}</TableHead>
                                    <TableHead className="text-end">{t('receivables.b90p')}</TableHead>
                                    <TableHead>{t('receivables.last_payment')}</TableHead>
                                    {canReceipt && <TableHead />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((row) => (
                                    <TableRow key={row.customer.id}>
                                        <TableCell>
                                            <div className="font-medium">{row.customer.name}</div>
                                            <div className="text-muted-foreground text-xs">
                                                {row.customer.code}
                                                {row.customer.group && <span className="ms-2">{row.customer.group.name}</span>}
                                                {row.overdue && (
                                                    <Badge
                                                        variant="outline"
                                                        className="ms-2 border-red-300 text-[10px] text-red-700 dark:text-red-400"
                                                    >
                                                        {t('receivables.overdue')}
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-end text-base font-bold tabular-nums">{fmtAmount(row.balance)}</TableCell>
                                        <TableCell className="text-muted-foreground text-end tabular-nums">
                                            {parseFloat(row.buckets.b30) > 0 ? fmtAmount(row.buckets.b30) : ''}
                                        </TableCell>
                                        <TableCell className="text-end text-amber-700 tabular-nums dark:text-amber-400">
                                            {parseFloat(row.buckets.b60) > 0 ? fmtAmount(row.buckets.b60) : ''}
                                        </TableCell>
                                        <TableCell className="text-end text-orange-700 tabular-nums dark:text-orange-400">
                                            {parseFloat(row.buckets.b90) > 0 ? fmtAmount(row.buckets.b90) : ''}
                                        </TableCell>
                                        <TableCell className="text-end font-semibold text-red-600 tabular-nums dark:text-red-400">
                                            {parseFloat(row.buckets.b90p) > 0 ? fmtAmount(row.buckets.b90p) : ''}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {row.last_payment ?? '—'}
                                        </TableCell>
                                        {canReceipt && (
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route('receipts.index', { search: row.customer.code })}>
                                                            <HandCoins className="size-4" />
                                                            {t('receivables.take_payment')}
                                                        </Link>
                                                    </Button>
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={route('statements.show', row.customer.id)}>
                                                            <FileText className="size-4" />
                                                            {t('statements.open')}
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {/* Totals row */}
                        <div className="bg-muted/50 flex flex-wrap justify-between gap-2 border-t p-3 text-sm font-semibold">
                            <span>{t('common.total')}</span>
                            <span className="tabular-nums">
                                {fmtAmount(totals.balance)} {t('common.currency')}
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
