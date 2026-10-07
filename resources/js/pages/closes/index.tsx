import { EmptyState } from '@/components/empty-state';
import { NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type IdName, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Lock, Store } from 'lucide-react';
import { useState } from 'react';

interface CloseRow {
    id: number;
    date: string;
    type: string;
    closeable_id: number;
    driver: string | null;
    expected: string;
    counted: string;
    variance: string;
    status: string;
}

interface ClosesPageProps {
    closes: Paginated<CloseRow>;
    isApprover: boolean;
    drivers: IdName[];
    selfId: number;
}

export default function ClosesIndex({ closes, isApprover, drivers, selfId }: ClosesPageProps) {
    const { t } = useTrans();
    const [driverId, setDriverId] = useState('');
    const today = new Date().toISOString().slice(0, 10);

    return (
        <AppLayout breadcrumbs={[{ title: t('closes.title'), href: '/closes' }]}>
            <Head title={t('closes.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('closes.title')}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {isApprover ? (
                                <>
                                    <NativeSelect className="w-44" value={driverId} onChange={(e) => setDriverId(e.target.value)}>
                                        <option value="">{t('closes.driver')}...</option>
                                        {drivers.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <Button
                                        disabled={!driverId}
                                        onClick={() => router.get(route('closes.create'), { type: 'driver', id: driverId, date: today })}
                                    >
                                        <Lock className="size-4" /> {t('closes.new_driver')}
                                    </Button>
                                    <Button variant="outline" onClick={() => router.get(route('closes.create'), { type: 'counter', date: today })}>
                                        <Store className="size-4" /> {t('closes.new_counter')}
                                    </Button>
                                </>
                            ) : (
                                <Button asChild>
                                    <Link href={route('closes.create', { type: 'driver', id: selfId, date: today })}>
                                        <Lock className="size-4" /> {t('closes.my_close')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                {closes.data.length === 0 ? (
                    <EmptyState icon={Lock} message={t('common.no_results')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>
                                        {t('closes.driver')} / {t('closes.counter')}
                                    </TableHead>
                                    <TableHead className="text-end">{t('closes.expected')}</TableHead>
                                    <TableHead className="text-end">{t('closes.counted')}</TableHead>
                                    <TableHead className="text-end">{t('closes.variance')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {closes.data.map((close) => {
                                    const varianceNum = parseFloat(close.variance);
                                    return (
                                        <TableRow key={close.id}>
                                            <TableCell>
                                                <Link
                                                    href={route('closes.create', {
                                                        type: close.type,
                                                        ...(close.type === 'driver' ? { id: close.closeable_id } : {}),
                                                        date: close.date,
                                                    })}
                                                    className="tabular-nums hover:underline"
                                                    dir="ltr"
                                                >
                                                    {close.date}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {close.type === 'driver' ? close.driver : t('closes.counter')}
                                            </TableCell>
                                            <TableCell className="text-end tabular-nums">{fmtAmount(close.expected)}</TableCell>
                                            <TableCell className="text-end tabular-nums">{fmtAmount(close.counted)}</TableCell>
                                            <TableCell
                                                className={`text-end font-semibold tabular-nums ${
                                                    varianceNum < 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : varianceNum > 0
                                                          ? 'text-amber-700 dark:text-amber-400'
                                                          : 'text-emerald-700 dark:text-emerald-400'
                                                }`}
                                            >
                                                {fmtAmount(close.variance)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={close.status === 'approved' ? 'secondary' : 'outline'}>
                                                    {t(`closes.status.${close.status}`)}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={closes} />
            </div>
        </AppLayout>
    );
}
