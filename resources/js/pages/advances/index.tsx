import { Field, NativeSelect } from '@/components/field';
import { PageHeader } from '@/components/page-header';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { fmtAmount } from '@/lib/format';
import { useTrans } from '@/lib/i18n';
import { type Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, LoaderCircle, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface AdvanceRow {
    id: number;
    employee: { id: number; name_ar: string } | null;
    date: string;
    amount: string;
    recovered: string;
    remaining: string;
    plan: string;
    installment: string | null;
    paid_from: string;
    status: string;
    notes: string | null;
}

interface ChargeRow {
    id: number;
    employee: { id: number; name_ar: string } | null;
    date: string;
    type: string;
    amount: string;
    remaining: string;
    status: string;
    notes: string | null;
}

interface EmployeeOption {
    id: number;
    name_ar: string;
    basic_salary: string;
}

interface AdvancesPageProps {
    advances: Paginated<AdvanceRow>;
    charges: ChargeRow[];
    employees: EmployeeOption[];
    outstandingTotal: string;
    canApprove: boolean;
}

export default function AdvancesIndex({ advances, charges, employees, outstandingTotal, canApprove }: AdvancesPageProps) {
    const { t } = useTrans();
    const [advanceOpen, setAdvanceOpen] = useState(false);
    const [chargeOpen, setChargeOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={[{ title: t('advances.title'), href: '/advances' }]}>
            <Head title={t('advances.title')} />
            <div className="flex flex-col gap-4 p-4">
                <PageHeader
                    title={t('advances.title')}
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => setChargeOpen(true)}>
                                <Plus className="size-4" /> {t('advances.charge_new')}
                            </Button>
                            <Button onClick={() => setAdvanceOpen(true)}>
                                <Plus className="size-4" /> {t('advances.create')}
                            </Button>
                        </div>
                    }
                />

                <div className="bg-accent/40 max-w-sm rounded-xl border p-4">
                    <div className="text-3xl font-bold tabular-nums">{fmtAmount(outstandingTotal)}</div>
                    <div className="text-muted-foreground mt-1 text-sm">
                        {t('advances.outstanding_total')} ({t('common.currency')})
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('payroll.employee')}</TableHead>
                                <TableHead>{t('common.date')}</TableHead>
                                <TableHead className="text-end">{t('expenses.amount')}</TableHead>
                                <TableHead className="text-end">{t('advances.recovered')}</TableHead>
                                <TableHead className="text-end">{t('advances.remaining')}</TableHead>
                                <TableHead>{t('advances.plan')}</TableHead>
                                <TableHead>{t('common.status')}</TableHead>
                                {canApprove && <TableHead className="w-14" />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {advances.data.map((advance) => (
                                <TableRow key={advance.id}>
                                    <TableCell className="font-medium">{advance.employee?.name_ar}</TableCell>
                                    <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                        {advance.date}
                                    </TableCell>
                                    <TableCell className="text-end tabular-nums">{fmtAmount(advance.amount)}</TableCell>
                                    <TableCell className="text-end text-emerald-700 tabular-nums dark:text-emerald-400">
                                        {fmtAmount(advance.recovered)}
                                    </TableCell>
                                    <TableCell className="text-end font-semibold tabular-nums">{fmtAmount(advance.remaining)}</TableCell>
                                    <TableCell className="text-muted-foreground text-xs">
                                        {t(`advances.plan.${advance.plan}`)}
                                        {advance.installment && <span className="ms-1 tabular-nums">({fmtAmount(advance.installment)})</span>}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={advance.status === 'settled' ? 'secondary' : 'outline'}
                                            className={advance.status === 'pending' ? 'border-amber-300 text-amber-700 dark:text-amber-400' : ''}
                                        >
                                            {t(`advances.status.${advance.status}`)}
                                        </Badge>
                                    </TableCell>
                                    {canApprove && (
                                        <TableCell>
                                            {advance.status === 'pending' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-emerald-600"
                                                    onClick={() => router.post(route('advances.approve', advance.id), {}, { preserveScroll: true })}
                                                >
                                                    <Check className="size-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Pagination paginator={advances} />

                <section className="flex flex-col gap-2">
                    <div>
                        <h2 className="font-semibold">{t('advances.charges')}</h2>
                        <p className="text-muted-foreground text-xs">{t('advances.shortage_note')}</p>
                    </div>
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('payroll.employee')}</TableHead>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead>{t('expenses.category')}</TableHead>
                                    <TableHead className="text-end">{t('expenses.amount')}</TableHead>
                                    <TableHead className="text-end">{t('advances.remaining')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="w-14" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {charges.map((charge) => (
                                    <TableRow key={charge.id}>
                                        <TableCell className="font-medium">{charge.employee?.name_ar}</TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums" dir="ltr">
                                            {charge.date}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{t(`advances.charge.${charge.type}`)}</Badge>
                                        </TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(charge.amount)}</TableCell>
                                        <TableCell className="text-end tabular-nums">{fmtAmount(charge.remaining)}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={charge.status === 'settled' ? 'secondary' : 'outline'}
                                                className={charge.status === 'pending' ? 'border-amber-300 text-amber-700 dark:text-amber-400' : ''}
                                            >
                                                {t(`advances.status.${charge.status}`) !== `advances.status.${charge.status}`
                                                    ? t(`advances.status.${charge.status}`)
                                                    : t(`expenses.status.${charge.status}`)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {charge.status === 'pending' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-emerald-600"
                                                    onClick={() =>
                                                        router.post(route('advances.charges.approve', charge.id), {}, { preserveScroll: true })
                                                    }
                                                >
                                                    <Check className="size-4" />
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <AdvanceDialog open={advanceOpen} onOpenChange={setAdvanceOpen} employees={employees} />
                <ChargeDialog open={chargeOpen} onOpenChange={setChargeOpen} employees={employees} />
            </div>
        </AppLayout>
    );
}

function AdvanceDialog({ open, onOpenChange, employees }: { open: boolean; onOpenChange: (o: boolean) => void; employees: EmployeeOption[] }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '' as number | '',
        advance_date: new Date().toISOString().slice(0, 10),
        amount: '',
        paid_from: 'counter_cash',
        plan: 'full',
        installment_amount: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('advances.store'), {
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
                    <DialogTitle>{t('advances.create')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('payroll.employee')} htmlFor="adv_emp" error={errors.employee_id}>
                        <NativeSelect
                            id="adv_emp"
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
                        <Field label={t('expenses.amount')} htmlFor="adv_amount" error={errors.amount}>
                            <Input
                                id="adv_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('common.date')} htmlFor="adv_date" error={errors.advance_date}>
                            <Input
                                id="adv_date"
                                type="date"
                                dir="ltr"
                                value={data.advance_date}
                                onChange={(e) => setData('advance_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('expenses.paid_from')} htmlFor="adv_source" error={errors.paid_from}>
                            <NativeSelect id="adv_source" value={data.paid_from} onChange={(e) => setData('paid_from', e.target.value)}>
                                <option value="counter_cash">{t('expenses.paid_from.counter_cash')}</option>
                                <option value="bank">{t('expenses.paid_from.bank')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('advances.plan')} htmlFor="adv_plan" error={errors.plan}>
                            <NativeSelect id="adv_plan" value={data.plan} onChange={(e) => setData('plan', e.target.value)}>
                                <option value="full">{t('advances.plan.full')}</option>
                                <option value="installment">{t('advances.plan.installment')}</option>
                            </NativeSelect>
                        </Field>
                        {data.plan === 'installment' && (
                            <Field label={t('advances.installment')} htmlFor="adv_inst" error={errors.installment_amount}>
                                <Input
                                    id="adv_inst"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    dir="ltr"
                                    value={data.installment_amount}
                                    onChange={(e) => setData('installment_amount', e.target.value)}
                                />
                            </Field>
                        )}
                    </div>
                    <Field label={t('common.notes')} htmlFor="adv_notes" error={errors.notes} optional>
                        <Input id="adv_notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ChargeDialog({ open, onOpenChange, employees }: { open: boolean; onOpenChange: (o: boolean) => void; employees: EmployeeOption[] }) {
    const { t } = useTrans();
    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '' as number | '',
        charge_date: new Date().toISOString().slice(0, 10),
        type: 'fine',
        amount: '',
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('advances.charges.store'), {
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
                    <DialogTitle>{t('advances.charge_new')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-3">
                    <Field label={t('payroll.employee')} htmlFor="chg_emp" error={errors.employee_id}>
                        <NativeSelect
                            id="chg_emp"
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
                        <Field label={t('expenses.category')} htmlFor="chg_type" error={errors.type}>
                            <NativeSelect id="chg_type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                <option value="fine">{t('advances.charge.fine')}</option>
                                <option value="damage">{t('advances.charge.damage')}</option>
                                <option value="shortage">{t('advances.charge.shortage')}</option>
                            </NativeSelect>
                        </Field>
                        <Field label={t('expenses.amount')} htmlFor="chg_amount" error={errors.amount}>
                            <Input
                                id="chg_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                dir="ltr"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label={t('common.date')} htmlFor="chg_date" error={errors.charge_date}>
                            <Input
                                id="chg_date"
                                type="date"
                                dir="ltr"
                                value={data.charge_date}
                                onChange={(e) => setData('charge_date', e.target.value)}
                            />
                        </Field>
                        <Field label={t('common.notes')} htmlFor="chg_notes" error={errors.notes} optional>
                            <Input id="chg_notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                        </Field>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="size-4 animate-spin" />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
