import { EmptyState } from '@/components/empty-state';
import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtInt, localToday } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarOff, LoaderCircle, Plus, Undo2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface LeaveRow {
    id: number;
    employee: { id: number; name_ar: string; job: string } | null;
    start_date: string;
    expected_return: string;
    actual_return: string | null;
    overdue: boolean;
    away: boolean;
    notes: string | null;
}

interface LeavesPageProps {
    leaves: LeaveRow[];
    employees: { id: number; name_ar: string }[];
    awayCount: number;
    overdueCount: number;
}

export default function LeavesIndex({ leaves, employees, awayCount, overdueCount }: LeavesPageProps) {
    const { t } = useTrans();
    const [open, setOpen] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);

    const markReturned = (leave: LeaveRow) => {
        setActionError(null);
        router.post(
            route('leaves.return', leave.id),
            { actual_return: localToday() },
            { preserveScroll: true, onError: (e) => setActionError(Object.values(e)[0] as string) },
        );
    };

    return (
        <AppLayout breadcrumbs={[{ title: t('leaves.title'), href: '/leaves' }]}>
            <Head title={t('leaves.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('leaves.title')}
                    actions={
                        <Button onClick={() => setOpen(true)}>
                            <Plus className="size-4" /> {t('leaves.create')}
                        </Button>
                    }
                />

                <div className="grid max-w-sm grid-cols-2 gap-3">
                    <div className="rounded-xl border p-4">
                        <div className="text-2xl font-bold tabular-nums">{fmtInt(awayCount)}</div>
                        <div className="text-muted-foreground text-xs">{t('leaves.away_now')}</div>
                    </div>
                    <div className={`rounded-xl border p-4 ${overdueCount > 0 ? 'border-red-300 dark:border-red-800' : ''}`}>
                        <div className={`text-2xl font-bold tabular-nums ${overdueCount > 0 ? 'text-red-600 dark:text-red-400' : ''}`}>
                            {fmtInt(overdueCount)}
                        </div>
                        <div className="text-muted-foreground text-xs">{t('leaves.overdue_count')}</div>
                    </div>
                </div>

                {actionError && (
                    <div className="rounded-lg border border-red-300 bg-red-100/60 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/20 dark:text-red-400">
                        {actionError}
                    </div>
                )}

                {leaves.length === 0 ? (
                    <EmptyState icon={CalendarOff} message={t('leaves.empty')} />
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('payroll.employee')}</TableHead>
                                    <TableHead>{t('leaves.start')}</TableHead>
                                    <TableHead>{t('leaves.expected')}</TableHead>
                                    <TableHead>{t('leaves.actual')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-28" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {leaves.map((leave) => (
                                    <TableRow key={leave.id}>
                                        <TableCell>
                                            <span className="font-medium">{leave.employee?.name_ar}</span>
                                            {leave.employee?.job && (
                                                <span className="text-muted-foreground ms-2 text-xs">{t(`employees.job.${leave.employee.job}`)}</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {leave.start_date}
                                        </TableCell>
                                        <TableCell className="tabular-nums" dir="ltr">
                                            {leave.expected_return}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {leave.actual_return ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {leave.overdue ? (
                                                <Badge variant="outline" className="border-red-300 text-red-700 dark:text-red-400">
                                                    {t('leaves.overdue')}
                                                </Badge>
                                            ) : leave.away ? (
                                                <Badge variant="outline" className="border-amber-300 text-amber-700 dark:text-amber-400">
                                                    {t('leaves.away_now')}
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary" className="text-emerald-700 dark:text-emerald-400">
                                                    {t('leaves.returned')}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {leave.away && (
                                                <Button variant="outline" size="sm" onClick={() => markReturned(leave)}>
                                                    <Undo2 className="size-4" /> {t('leaves.mark_return')}
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <LeaveDialog open={open} onOpenChange={setOpen} employees={employees} />
            </div>
        </AppLayout>
    );
}

function LeaveDialog({
    open,
    onOpenChange,
    employees,
}: {
    open: boolean;
    onOpenChange: (o: boolean) => void;
    employees: { id: number; name_ar: string }[];
}) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '' as number | '',
        start_date: localToday(),
        expected_return: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('leaves.store'), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('leaves.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('payroll.employee')} htmlFor="lv_emp" error={errors.employee_id}>
                        <NativeSelect
                            id="lv_emp"
                            value={data.employee_id === '' ? '' : String(data.employee_id)}
                            onChange={(e) => setData('employee_id', e.target.value === '' ? '' : Number(e.target.value))}
                        >
                            <option value="">—</option>
                            {employees.map((emp) => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.name_ar}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('leaves.start')} htmlFor="lv_start" error={errors.start_date}>
                            <Input
                                id="lv_start"
                                type="date"
                                dir="ltr"
                                value={data.start_date}
                                onChange={(e) => setData('start_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('leaves.expected')} htmlFor="lv_expected" error={errors.expected_return}>
                            <Input
                                id="lv_expected"
                                type="date"
                                dir="ltr"
                                value={data.expected_return}
                                onChange={(e) => setData('expected_return', e.target.value)}
                                required
                            />
                        </Field>
                    </div>
                    <Field label={t('common.notes')} htmlFor="lv_notes" error={errors.notes} optional>
                        <Input id="lv_notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing || data.employee_id === '' || !data.expected_return}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
