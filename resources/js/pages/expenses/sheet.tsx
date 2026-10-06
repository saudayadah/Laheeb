import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { ExpenseDialog } from '@/pages/expenses/index';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Download } from 'lucide-react';
import { useMemo, useState } from 'react';

interface SheetCategory {
    id: number;
    name_ar: string;
    kind: string;
}

interface SheetProps {
    month: string;
    daysInMonth: number;
    categories: SheetCategory[];
    cells: Record<string, string>;
    notes: Record<string, string>;
}

export default function ExpensesSheet({ month, daysInMonth, categories, cells, notes }: SheetProps) {
    const { t } = useTrans();
    const [dialogCell, setDialogCell] = useState<{ date: string; category: number } | null>(null);

    const days = useMemo(() => Array.from({ length: daysInMonth }, (_, i) => i + 1), [daysInMonth]);

    const rowTotal = (day: number) => categories.reduce((sum, c) => sum + parseFloat(cells[`${day}:${c.id}`] ?? '0'), 0);
    const colTotal = (categoryId: number) => days.reduce((sum, d) => sum + parseFloat(cells[`${d}:${categoryId}`] ?? '0'), 0);
    const grand = categories.reduce((sum, c) => sum + colTotal(c.id), 0);

    const changeMonth = (value: string) => {
        if (value) router.get(route('expenses.sheet'), { month: value }, { preserveState: false });
    };

    // Extra props available on the page for the dialog.
    const page = usePage().props as unknown as { suppliers?: { id: number; name: string }[]; drivers?: { id: number; name: string }[] };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('expenses.title'), href: '/expenses' },
                { title: t('expenses.sheet'), href: '#' },
            ]}
        >
            <Head title={t('expenses.sheet')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={`${t('expenses.sheet')} — ${month}`}
                    description={t('expenses.sheet_hint')}
                    actions={
                        <div className="flex items-center gap-2">
                            <Input type="month" dir="ltr" className="w-40" value={month} onChange={(e) => changeMonth(e.target.value)} />
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('expenses.sheet.export', { month })}>
                                    <Download className="size-4" /> {t('expenses.export')}
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('expenses.index')}>
                                    <ArrowRight className="size-4" /> {t('common.back')}
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="overflow-auto rounded-xl border" style={{ maxHeight: 'calc(100vh - 230px)' }}>
                    <table className="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                <th className="bg-muted sticky start-0 top-0 z-30 border-e border-b px-2 py-2 text-start">{t('common.date')}</th>
                                {categories.map((c) => (
                                    <th
                                        key={c.id}
                                        className="bg-muted sticky top-0 z-20 min-w-20 border-b px-1 py-2 text-center font-medium whitespace-nowrap"
                                    >
                                        {c.name_ar}
                                    </th>
                                ))}
                                <th className="bg-muted sticky top-0 z-20 border-s border-b px-2 py-2 text-center font-semibold">
                                    {t('common.total')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {days.map((day) => {
                                const total = rowTotal(day);
                                return (
                                    <tr key={day} className="group">
                                        <td className="bg-background group-hover:bg-muted/50 sticky start-0 z-10 border-e border-b px-2 py-1 font-medium tabular-nums">
                                            {day}
                                            {notes[String(day)] && (
                                                <div className="text-muted-foreground max-w-32 truncate text-[10px]" title={notes[String(day)]}>
                                                    {notes[String(day)]}
                                                </div>
                                            )}
                                        </td>
                                        {categories.map((c) => {
                                            const value = cells[`${day}:${c.id}`];
                                            return (
                                                <td key={c.id} className="border-b p-0">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setDialogCell({
                                                                date: `${month}-${String(day).padStart(2, '0')}`,
                                                                category: c.id,
                                                            })
                                                        }
                                                        className="hover:bg-accent/50 h-8 w-full px-1 text-center tabular-nums"
                                                    >
                                                        {value ? fmtAmount(value) : ''}
                                                    </button>
                                                </td>
                                            );
                                        })}
                                        <td className="text-muted-foreground border-s border-b px-2 py-1 text-center font-medium tabular-nums">
                                            {total > 0 ? fmtAmount(total) : ''}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td className="bg-muted sticky start-0 bottom-0 z-30 border-e border-t px-2 py-2 font-semibold">
                                    {t('common.total')}
                                </td>
                                {categories.map((c) => {
                                    const total = colTotal(c.id);
                                    return (
                                        <td
                                            key={c.id}
                                            className="bg-muted sticky bottom-0 z-20 border-t px-1 py-2 text-center font-semibold tabular-nums"
                                        >
                                            {total > 0 ? fmtAmount(total) : ''}
                                        </td>
                                    );
                                })}
                                <td className="bg-muted sticky bottom-0 z-20 border-s border-t px-2 py-2 text-center font-bold tabular-nums">
                                    {fmtAmount(grand)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {dialogCell && (
                    <ExpenseDialog
                        open
                        onOpenChange={(o) => !o && setDialogCell(null)}
                        categories={categories}
                        suppliers={page.suppliers ?? []}
                        drivers={page.drivers ?? []}
                        presetDate={dialogCell.date}
                        presetCategory={dialogCell.category}
                    />
                )}
            </div>
        </AppLayout>
    );
}
