import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount, fmtInt } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign, Plus } from 'lucide-react';
import { useState } from 'react';

interface RunRow {
    id: number;
    period: string;
    status: string;
    total_net: string;
    lines_count: number;
    paid_at: string | null;
}

const statusColors: Record<string, string> = {
    draft: '',
    reviewed: 'border-blue-300 text-blue-700 dark:text-blue-400',
    approved: 'border-amber-300 text-amber-700 dark:text-amber-400',
    paid: 'border-emerald-300 text-emerald-700 dark:text-emerald-400',
};

export default function PayrollIndex({ runs, suggestedPeriod }: { runs: Paginated<RunRow>; suggestedPeriod: string }) {
    const { t } = useTrans();
    const [period, setPeriod] = useState(suggestedPeriod);

    const create = () => {
        if (period) router.post(route('payroll.store'), { period });
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('payroll.title'), href: '/payroll' }]}>
            <Head title={t('payroll.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('payroll.title')}
                    actions={
                        <div className="flex items-center gap-2">
                            <Input type="month" dir="ltr" className="w-40" value={period} onChange={(e) => setPeriod(e.target.value)} />
                            <Button onClick={create}>
                                <Plus className="size-4" /> {t('payroll.create')}
                            </Button>
                        </div>
                    }
                />

                {runs.data.length === 0 ? (
                    <EmptyState icon={BadgeDollarSign} message={t('payroll.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('payroll.period')}</TableHead>
                                    <TableHead>{t('payroll.employee')}</TableHead>
                                    <TableHead className="text-end">{t('payroll.total_net')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {runs.data.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell>
                                            <Link href={route('payroll.show', run.id)} className="font-medium tabular-nums hover:underline" dir="ltr">
                                                {run.period}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">{fmtInt(run.lines_count)}</TableCell>
                                        <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(run.total_net)}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className={statusColors[run.status]}>
                                                {t(`payroll.status.${run.status}`)}
                                                {run.paid_at && (
                                                    <span className="ms-1 tabular-nums" dir="ltr">
                                                        ({run.paid_at})
                                                    </span>
                                                )}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={runs} />
            </div>
        </AppLayout>
    );
}
