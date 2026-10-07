import { ConfirmDelete } from '@/components/confirm-delete';
import { NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck, CheckCheck, HandCoins, Printer, ReceiptText, Undo2, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface PayrollLineData {
    id: number;
    employee: { id: number | null; name_ar: string | null; job: string | null };
    basic: string;
    overtime: string;
    leave_allowance: string;
    additions: string;
    absence_days: string;
    absence_amount: string;
    deductions: string;
    advance_recovery: string;
    charges_recovery: string;
    net: string;
    payment_method: string | null;
    paid_at: string | null;
}

export interface PayrollRunData {
    id: number;
    period: string;
    status: string;
    paid_at: string | null;
    lines: PayrollLineData[];
}

export default function PayrollShow({ run, canApprove }: { run: PayrollRunData; canApprove: boolean }) {
    const { t } = useTrans();
    const [payOpen, setPayOpen] = useState(false);

    const editable = run.status === 'draft' || run.status === 'reviewed';
    const totalNet = useMemo(() => run.lines.reduce((sum, line) => sum + parseFloat(line.net), 0), [run.lines]);

    const action = (name: string) => router.post(route(`payroll.${name}`, run.id), {}, { preserveScroll: true });

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('payroll.title'), href: '/payroll' },
                { title: run.period, href: '#' },
            ]}
        >
            <Head title={`${t('payroll.title')} ${run.period}`} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={`${t('payroll.title')} — ${run.period}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{t(`payroll.status.${run.status}`)}</Badge>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('payroll.sheet', run.id)}>
                                    <Printer className="size-4" /> {t('payroll.sheet')}
                                </Link>
                            </Button>
                            {editable && (
                                <Button variant="outline" size="sm" onClick={() => action('sync')}>
                                    <UserPlus className="size-4" /> {t('payroll.sync_employees')}
                                </Button>
                            )}
                            {run.status === 'draft' && (
                                <Button size="sm" onClick={() => action('review')}>
                                    <BadgeCheck className="size-4" /> {t('payroll.review')}
                                </Button>
                            )}
                            {run.status === 'reviewed' && canApprove && (
                                <Button size="sm" onClick={() => action('approve')}>
                                    <CheckCheck className="size-4" /> {t('payroll.approve')}
                                </Button>
                            )}
                            {run.status === 'approved' && canApprove && (
                                <Button size="sm" onClick={() => setPayOpen(true)}>
                                    <HandCoins className="size-4" /> {t('payroll.pay')}
                                </Button>
                            )}
                            {(run.status === 'reviewed' || run.status === 'approved') && (
                                <Button variant="outline" size="sm" onClick={() => action('reopen')}>
                                    <Undo2 className="size-4" /> {t('payroll.reopen')}
                                </Button>
                            )}
                            {run.status !== 'paid' && <ConfirmDelete url={route('payroll.destroy', run.id)} />}
                        </div>
                    }
                />

                <div className="border-primary/30 bg-primary/10 dark:bg-primary/15 max-w-xs rounded-xl border p-4">
                    <div className="text-3xl font-bold tabular-nums">{fmtAmount(totalNet)}</div>
                    <div className="text-muted-foreground mt-1 text-sm">
                        {t('payroll.total_net')} ({t('common.currency')})
                    </div>
                </div>

                {run.lines.length === 0 && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                        {t('payroll.no_lines')}
                    </div>
                )}

                <div className="overflow-auto rounded-xl border">
                    <table className="w-full border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr>
                                {[
                                    t('payroll.employee'),
                                    t('payroll.basic'),
                                    t('payroll.overtime'),
                                    t('payroll.leave_allowance'),
                                    t('payroll.additions'),
                                    t('payroll.absence_days'),
                                    t('payroll.absence'),
                                    t('payroll.deductions'),
                                    t('payroll.advance_recovery'),
                                    t('payroll.charges_recovery'),
                                    t('payroll.net'),
                                    '',
                                ].map((heading, i) => (
                                    <th key={i} className="bg-muted sticky top-0 border-b px-2 py-2 text-start font-medium whitespace-nowrap">
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {run.lines.map((line) => (
                                <tr key={line.id} className="group">
                                    <td className="border-b px-2 py-1">
                                        <div className="font-medium whitespace-nowrap">{line.employee.name_ar}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {line.employee.job ? t(`employees.job.${line.employee.job}`) : ''}
                                        </div>
                                    </td>
                                    <td className="border-b px-2 py-1 text-end tabular-nums">{fmtAmount(line.basic)}</td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="overtime" editable={editable} label={t('payroll.overtime')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="leave_allowance" editable={editable} label={t('payroll.leave_allowance')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="additions" editable={editable} label={t('payroll.additions')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="absence_days" editable={editable} label={t('payroll.absence_days')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="absence_amount" editable={editable} label={t('payroll.absence')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell line={line} field="deductions" editable={editable} label={t('payroll.deductions')} />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell
                                            line={line}
                                            field="advance_recovery"
                                            editable={editable}
                                            label={t('payroll.advance_recovery')}
                                        />
                                    </td>
                                    <td className="border-b p-0">
                                        <EditableCell
                                            line={line}
                                            field="charges_recovery"
                                            editable={editable}
                                            label={t('payroll.charges_recovery')}
                                        />
                                    </td>
                                    <td className="border-b px-2 py-1 text-end font-bold tabular-nums">{fmtAmount(line.net)}</td>
                                    <td className="border-b px-1 py-1">
                                        <Button variant="ghost" size="icon" className="size-7" asChild>
                                            <Link href={route('payroll.payslip', line.id)}>
                                                <ReceiptText className="size-4" />
                                                <span className="sr-only">{t('payroll.payslip')}</span>
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colSpan={10} className="bg-muted border-t px-2 py-2 font-semibold">
                                    {t('payroll.total_net')}
                                </td>
                                <td className="bg-muted border-t px-2 py-2 text-end font-bold tabular-nums">{fmtAmount(totalNet)}</td>
                                <td className="bg-muted border-t" />
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <PayDialog open={payOpen} onOpenChange={setPayOpen} run={run} />
            </div>
        </AppLayout>
    );
}

function EditableCell({ line, field, editable, label }: { line: PayrollLineData; field: keyof PayrollLineData; editable: boolean; label: string }) {
    const raw = String(line[field] ?? '0');
    const [value, setValue] = useState(parseFloat(raw) > 0 ? raw : '');

    const commit = () => {
        const current = parseFloat(raw) || 0;
        const next = parseFloat(value) || 0;
        if (current === next) return;
        router.patch(route('payroll.lines.update', line.id), { [field]: next }, { preserveScroll: true });
    };

    if (!editable) {
        return <div className="px-2 py-1 text-end tabular-nums">{parseFloat(raw) > 0 ? fmtAmount(raw) : ''}</div>;
    }

    return (
        <input
            key={raw}
            type="text"
            inputMode="decimal"
            dir="ltr"
            aria-label={label}
            className="focus:bg-accent h-9 w-24 border-0 bg-transparent px-2 text-end tabular-nums outline-none"
            defaultValue={parseFloat(raw) > 0 ? raw : ''}
            onChange={(e) => setValue(e.target.value)}
            onFocus={(e) => e.target.select()}
            onBlur={commit}
            onKeyDown={(e) => e.key === 'Enter' && (e.target as HTMLInputElement).blur()}
        />
    );
}

function PayDialog({ open, onOpenChange, run }: { open: boolean; onOpenChange: (o: boolean) => void; run: PayrollRunData }) {
    const { t } = useTrans();
    const [methods, setMethods] = useState<Record<number, string>>(() =>
        Object.fromEntries(run.lines.map((line) => [line.id, line.payment_method ?? 'bank'])),
    );
    const [saving, setSaving] = useState(false);

    const submit = () => {
        setSaving(true);
        router.post(
            route('payroll.pay', run.id),
            { methods },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {t('payroll.pay')} — {run.period}
                    </DialogTitle>
                </DialogHeader>
                <div className="flex flex-col gap-2">
                    {run.lines.map((line) => (
                        <div key={line.id} className="flex items-center gap-2">
                            <span className="min-w-0 flex-1 truncate text-sm">{line.employee.name_ar}</span>
                            <span className="w-24 text-end text-sm font-semibold tabular-nums">{fmtAmount(line.net)}</span>
                            <NativeSelect
                                className="w-32"
                                aria-label={`${t('payroll.method')} — ${line.employee.name_ar ?? ''}`}
                                value={methods[line.id]}
                                onChange={(e) => setMethods((m) => ({ ...m, [line.id]: e.target.value }))}
                            >
                                <option value="bank">{t('payroll.method.bank')}</option>
                                <option value="cash">{t('payroll.method.cash')}</option>
                            </NativeSelect>
                        </div>
                    ))}
                </div>
                <DialogFooter className="gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common.cancel')}
                    </Button>
                    <Button onClick={submit} disabled={saving}>
                        {t('payroll.pay')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
