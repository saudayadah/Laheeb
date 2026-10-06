import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Printer } from 'lucide-react';

interface StatementRow {
    id: number;
    date: string;
    description: string | null;
    purchase: string;
    payment: string;
    balance: string;
}

interface SupplierShowProps {
    supplier: { id: number; name: string; phone: string | null; payable: string };
    entries: StatementRow[];
}

export default function SupplierShow({ supplier, entries }: SupplierShowProps) {
    const { t } = useTrans();

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('suppliers.title'), href: '/suppliers' },
                { title: supplier.name, href: '#' },
            ]}
        >
            <Head title={supplier.name} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 print:max-w-none">
                <div className="print:hidden">
                    <PageHeader
                        title={`${t('suppliers.statement')} — ${supplier.name}`}
                        actions={
                            <div className="flex gap-2">
                                <Button size="sm" onClick={() => window.print()}>
                                    <Printer className="size-4" /> {t('orders.print')}
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={route('suppliers.index')}>
                                        <ArrowRight className="size-4" /> {t('common.back')}
                                    </Link>
                                </Button>
                            </div>
                        }
                    />
                </div>

                <div className="hidden text-center print:block">
                    <h1 className="text-lg font-bold">
                        {t('suppliers.statement')} — {supplier.name}
                    </h1>
                </div>

                <div className="bg-accent/40 max-w-xs rounded-xl border p-4 print:border-black">
                    <div className={`text-2xl font-bold tabular-nums ${parseFloat(supplier.payable) > 0 ? 'text-red-600 dark:text-red-400' : ''}`}>
                        {fmtAmount(supplier.payable)}
                    </div>
                    <div className="text-muted-foreground text-xs">
                        {t('suppliers.payable')} ({t('common.currency')})
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('common.date')}</TableHead>
                                <TableHead>{t('common.notes')}</TableHead>
                                <TableHead className="text-end">{t('suppliers.purchase')}</TableHead>
                                <TableHead className="text-end">{t('suppliers.payment')}</TableHead>
                                <TableHead className="text-end">{t('suppliers.balance')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {entries.map((entry) => (
                                <TableRow key={entry.id}>
                                    <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                        {entry.date}
                                    </TableCell>
                                    <TableCell>{entry.description}</TableCell>
                                    <TableCell className="text-end tabular-nums">
                                        {parseFloat(entry.purchase) > 0 ? fmtAmount(entry.purchase) : ''}
                                    </TableCell>
                                    <TableCell className="text-end text-emerald-700 tabular-nums dark:text-emerald-400">
                                        {parseFloat(entry.payment) > 0 ? fmtAmount(entry.payment) : ''}
                                    </TableCell>
                                    <TableCell className="text-end font-medium tabular-nums">{fmtAmount(entry.balance)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
